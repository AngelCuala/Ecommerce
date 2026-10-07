<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        // Get all order IDs that contain this seller's books
        $bookIds = Product::where('seller_id', auth()->id())->pluck('id');

        $orderIds = OrderItem::whereIn('product_id', $bookIds)
            ->pluck('order_id')
            ->unique();

        $orders = Order::whereIn('id', $orderIds)
            ->with(['items' => function ($q) use ($bookIds) {
                $q->whereIn('product_id', $bookIds)->with('book');
            }])
            ->latest()
            ->get();

        // Attach only this seller's items as a virtual property
        $orders->each(function ($order) use ($bookIds) {
            $order->sellerItems = $order->items->whereIn('product_id', $bookIds->toArray())->values();
        });

        return view('seller.orders.index', compact('orders'));
    }

    public function show(int $id)
    {
        $bookIds = Product::where('seller_id', auth()->id())->pluck('id');

        // Make sure this seller actually has items in the order
        $hasItems = OrderItem::where('order_id', $id)
            ->whereIn('product_id', $bookIds)
            ->exists();

        if (! $hasItems) {
            abort(403);
        }

        $order = Order::with(['items' => function ($q) use ($bookIds) {
            $q->whereIn('product_id', $bookIds)->with('book');
        }, 'delivery'])->findOrFail($id);

        $order->sellerItems = $order->items->whereIn('product_id', $bookIds->toArray())->values();

        // This seller's sorting-center pickup request for the order (if any)
        $parcel = \App\Models\Parcel::with('currentSortingCenter')
            ->where('order_id', $order->id)
            ->where('seller_id', auth()->id())
            ->where('status', '!=', 'cancelled') // closed duplicates are kept for history, not shown
            ->first();

        return view('seller.orders.show', compact('order', 'parcel'));
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:Pending,Processing,Shipped,Delivered',
        ]);

        // Only update if this seller owns at least one book in the order
        $bookIds  = Product::where('seller_id', auth()->id())->pluck('id');
        $hasItems = OrderItem::where('order_id', $id)
            ->whereIn('product_id', $bookIds)
            ->exists();

        if (! $hasItems) {
            abort(403);
        }

        Order::findOrFail($id)->update(['status' => $request->status]);

        return back()->with('success', 'Order status updated to ' . $request->status . '.');
    }

    /** Schedule courier pickup & create/update delivery record */
    public function handover(Request $request, int $id)
    {
        $request->validate([
            'courier_name'        => 'required|string|max:100',
            'tracking_number'     => 'nullable|string|max:100',
            'pickup_scheduled_at' => 'required|date',
            'notes'               => 'nullable|string|max:500',
        ]);

        $bookIds  = Product::where('seller_id', auth()->id())->pluck('id');
        $hasItems = OrderItem::where('order_id', $id)->whereIn('product_id', $bookIds)->exists();
        if (! $hasItems) abort(403);

        $order   = Order::findOrFail($id);
        $routing = app(\App\Services\DeliveryRoutingService::class);

        // Records created before the routing rule may have both workflows open — never add to that.
        if ($routing->hasConflictingRoutes($order)) {
            return back()->with('error', 'This order has both a courier delivery and a sorting-center request open. Please contact the admin to close the duplicate before rescheduling.');
        }

        // ONE route per order: keep an existing route; otherwise decide from the PSGC codes.
        $route    = $routing->activeRoute($order);
        $decision = null;
        if (! $route) {
            $decision = $routing->decide($order, auth()->user());
            $route    = $decision['route'];
            if (! $route) {
                return back()->with('error', $decision['reason'] . ' The pickup was not scheduled.');
            }
        }

        $msg = $route === \App\Services\DeliveryRoutingService::COURIER
            ? $this->scheduleCourierRoute($request, $order)
            : $this->scheduleSortingCenterRoute($request, $order, $decision);

        // Advance order to Processing if still Pending
        if ($order->status === 'Pending') {
            $order->update(['status' => 'Processing']);
        }

        if ($decision) {
            $msg = $decision['reason'] . ' ' . $msg;
        }

        return back()->with('success', $msg);
    }

    /** SAME municipality: an "available" courier delivery that an approved courier can claim. */
    private function scheduleCourierRoute(Request $request, Order $order): string
    {
        $id = $order->id;

        // Ready for pickup: the delivery becomes "available" so an approved courier can claim it.
        // A job a courier already claimed keeps its courier and status; only the schedule changes.
        $delivery = \App\Models\Delivery::firstOrNew(['order_id' => $id]);
        $claimed  = $delivery->exists && (
            $delivery->status === 'delivered'
            || ($delivery->courier_id && in_array($delivery->status, ['accepted', 'picked_up', 'in_transit'], true))
        );

        $delivery->fill([
            'tracking_number'     => $request->tracking_number,
            'pickup_scheduled_at' => $request->pickup_scheduled_at,
            'notes'               => $request->notes,
        ]);
        if (! $claimed) {
            $delivery->fill([
                'courier_id'   => null,
                'courier_name' => $request->courier_name,
                'status'       => 'available',
                'delivery_fee' => 50,
                'accepted_at'  => null,
            ]);
        }
        $newlyAvailable = ! $claimed && ($delivery->isDirty('status') || ! $delivery->exists);
        $delivery->save();

        $deliveryService = app(\App\Services\DeliveryService::class);
        if ($newlyAvailable) {
            $deliveryService->notifyAvailableCouriers($delivery);
        } elseif ($claimed && $delivery->status !== 'delivered') {
            $delivery->load('courier');
            $deliveryService->notifyCourier($delivery, 'Pickup schedule updated',
                $deliveryService->ref($delivery) . ' pickup is now set for '
                . \Carbon\Carbon::parse($request->pickup_scheduled_at)->format('M d, Y h:i A') . '.');
        }

        return 'Courier pickup scheduled for ' . \Carbon\Carbon::parse($request->pickup_scheduled_at)->format('M d, Y h:i A')
            . '. The request is open to couriers.';
    }

    /** DIFFERENT municipalities: a sorting-center pickup request (no courier delivery is created). */
    private function scheduleSortingCenterRoute(Request $request, Order $order, ?array $decision): string
    {
        $isNew = ! \App\Models\Parcel::where('order_id', $order->id)->where('seller_id', auth()->id())->exists();

        $parcel = app(\App\Services\ParcelService::class)->createForSeller(
            $order, auth()->user(), $request->pickup_scheduled_at, $request->notes
        );

        if ($parcel->current_sorting_center_id) {
            return "Pickup request {$parcel->tracking_number} sent to {$parcel->currentSortingCenter->name}.";
        }

        // Needs a sorting center, but none is assigned to the seller's or buyer's municipality yet.
        $where = $decision
            ? "{$decision['origin']} or {$decision['destination']}"
            : "the seller's or buyer's municipality";
        if ($isNew) {
            \Illuminate\Support\Facades\Log::warning("Order #{$order->id}: parcel {$parcel->tracking_number} needs sorting-center handling but no sorting center covers {$where}.");
            $svc = app(\App\Services\ParcelService::class);
            \App\Models\User::where('role', 'admin')->pluck('id')->each(fn ($adminId) => $svc->notify(
                (int) $adminId, 'Unrouted sorting-center pickup',
                "Order #" . str_pad($order->id, 6, '0', STR_PAD_LEFT) . " ({$parcel->tracking_number}) needs a sorting center, but none is assigned to {$where}.",
                'warning'
            ));
        }

        return "Pickup request {$parcel->tracking_number} created. No sorting center is assigned to {$where} yet, so the request is open to all sorting centers.";
    }

    /** Mark as handed over to courier → status becomes Shipped */
    public function markHandedOver(int $id)
    {
        $bookIds  = Product::where('seller_id', auth()->id())->pluck('id');
        $hasItems = OrderItem::where('order_id', $id)->whereIn('product_id', $bookIds)->exists();
        if (! $hasItems) abort(403);

        $order = Order::findOrFail($id);

        // Only a job a courier has accepted can be handed over.
        $delivery = \App\Models\Delivery::where('order_id', $id)
            ->where('status', 'accepted')->whereNotNull('courier_id')
            ->with('courier')->first();
        if (! $delivery) {
            return back()->with('error', 'No courier has accepted this delivery yet.');
        }

        // Handed over = the courier now has the parcel (same state as the courier's "Confirm Pickup").
        $delivery->update([
            'status'         => 'picked_up',
            'handed_over_at' => now(),
            'picked_up_at'   => now(),
        ]);

        $deliveryService = app(\App\Services\DeliveryService::class);
        $deliveryService->advanceOrder($order, 'Shipped');
        $deliveryService->notifyCourier($delivery, 'Parcel handed over',
            'The seller handed over ' . $deliveryService->ref($delivery) . '. Start the delivery when you\'re on the way.');

        return back()->with('success', 'Order marked as handed over to courier. Status updated to Shipped.');
    }
}
