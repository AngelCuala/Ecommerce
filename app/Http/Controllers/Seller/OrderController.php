<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Orders with this seller's products, by tab (New, To Ship, Shipping, Completed, Cancelled,
     * Return/Refund), with a date filter and search by order number.
     */
    public function index(Request $request)
    {
        $request->validate([
            'tab'       => 'nullable|string',
            'date_from' => 'nullable|date',
            'date_to'   => 'nullable|date|after_or_equal:date_from',
            'q'         => 'nullable|string|max:30',
        ], ['date_to.after_or_equal' => 'The end date must be on or after the start date.']);

        $bookIds = Product::withTrashed()->where('seller_id', auth()->id())->pluck('id');
        $tab     = array_key_exists($request->input('tab'), Order::SELLER_TABS) ? $request->input('tab') : 'all';

        // Online orders the buyer hasn't paid yet don't reach the seller.
        $base = Order::whereHas('items', fn ($q) => $q->whereIn('product_id', $bookIds))
            ->where('status', '!=', Order::AWAITING_PAYMENT)
            ->when($request->filled('date_from'), fn ($q) => $q->where('created_at', '>=', \Carbon\Carbon::parse($request->date_from)->startOfDay()))
            ->when($request->filled('date_to'), fn ($q) => $q->where('created_at', '<=', \Carbon\Carbon::parse($request->date_to)->endOfDay()))
            ->when($request->filled('q'), function ($q) use ($request) {
                $digits = ltrim(preg_replace('/\D/', '', $request->q), '0');
                $q->where('id', $digits === '' ? 0 : (int) $digits);
            });

        $counts = ['all' => (clone $base)->count()];
        foreach (Order::SELLER_TABS as $key => $def) {
            $counts[$key] = (clone $base)->whereIn('status', $def['statuses'])->count();
        }

        $orders = (clone $base)
            ->when($tab !== 'all', fn ($q) => $q->whereIn('status', Order::SELLER_TABS[$tab]['statuses']))
            ->with(['items' => fn ($q) => $q->whereIn('product_id', $bookIds)->with('product')])
            ->latest()->paginate(20)->withQueryString();

        // Attach only this seller's items as a virtual property
        $orders->getCollection()->each(function ($order) use ($bookIds) {
            $order->sellerItems = $order->items->whereIn('product_id', $bookIds->toArray())->values();
        });

        return view('seller.orders.index', compact('orders', 'tab', 'counts'));
    }

    public function show(int $id)
    {
        $bookIds = Product::withTrashed()->where('seller_id', auth()->id())->pluck('id');

        // Make sure this seller actually has items in the order
        $hasItems = OrderItem::where('order_id', $id)
            ->whereIn('product_id', $bookIds)
            ->exists();

        if (! $hasItems) {
            abort(403);
        }

        $order = Order::with(['items' => function ($q) use ($bookIds) {
            $q->whereIn('product_id', $bookIds)->with('product');
        }, 'delivery', 'returnRequests', 'events'])->findOrFail($id);

        $order->sellerItems = $order->items->whereIn('product_id', $bookIds->toArray())->values();

        // This seller's sorting-center pickup request for the order (if any)
        $parcel = \App\Models\Parcel::with('currentSortingCenter')
            ->where('order_id', $order->id)
            ->where('seller_id', auth()->id())
            ->where('status', '!=', 'cancelled') // closed duplicates are kept for history, not shown
            ->first();

        return view('seller.orders.show', compact('order', 'parcel'));
    }

    /** The order, if it has this seller's products. */
    private function ownOrder(int $id): Order
    {
        $bookIds = Product::withTrashed()->where('seller_id', auth()->id())->pluck('id');
        abort_unless(OrderItem::where('order_id', $id)->whereIn('product_id', $bookIds)->exists(), 403);

        return Order::findOrFail($id);
    }

    /** Step 1: New → Accepted. */
    public function accept(int $id)
    {
        $order = $this->ownOrder($id);
        if ($order->sellerStep() !== 'new') {
            return back()->with('error', 'Only a new order can be accepted.');
        }
        $order->update(['status' => Order::PROCESSING, 'accepted_at' => now()]);
        \App\Models\ActivityLog::note('Order ' . $order->number() . ' accepted', $order);

        return back()->with('success', 'Order ' . $order->number() . ' accepted. Pack it next.');
    }

    /** Step 2: Accepted → Packed. */
    public function pack(int $id)
    {
        $order = $this->ownOrder($id);
        if ($order->sellerStep() !== 'accepted') {
            return back()->with('error', $order->sellerStep() === 'new' ? 'Accept the order before packing it.' : 'This order can no longer be marked as packed.');
        }
        $order->update(['packed_at' => now()]);
        $order->events()->create(['status' => $order->status, 'note' => 'Packed by the seller', 'created_at' => now()]);
        \App\Models\ActivityLog::note('Order ' . $order->number() . ' packed', $order);

        return back()->with('success', 'Order ' . $order->number() . ' packed. Book the pickup to mark it Ready for Pickup.');
    }

    /** The seller cancels (until the parcel is picked up) with a reason; the buyer is told why. */
    public function cancel(Request $request, int $id)
    {
        $order = $this->ownOrder($id);
        if (! $order->isCancellableBySeller()) {
            return back()->with('error', 'This order has already been handed to the courier, so it can no longer be cancelled.');
        }
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:200'],
            'details' => 'nullable|string|max:300',
        ], ['reason.required' => 'Please choose a reason for cancelling.']);

        $reason = 'Cancelled by the seller: ' . $data['reason'] . (! empty($data['details']) ? ' — ' . $data['details'] : '');

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $reason) {
            // Restock, vouchers back, online payment refunded and the buyer notified: see Order::booted().
            $order->update(['status' => Order::CANCELLED, 'cancellation_reason' => $reason]);

            // An open courier job or sorting-center pickup is no longer needed.
            $delivery = \App\Models\Delivery::where('order_id', $order->id)->whereIn('status', ['available', 'accepted'])->with('courier')->first();
            if ($delivery) {
                $delivery->update(['status' => 'failed', 'notes' => 'Order cancelled by the seller.']);
                app(\App\Services\DeliveryService::class)->notifyCourier($delivery, 'Delivery cancelled',
                    'Order ' . $order->number() . ' was cancelled by the seller. No pickup needed.', 'warning');
            }
            \App\Models\Parcel::where('order_id', $order->id)->whereIn('status', ['pending_pickup', 'pickup_approved', 'pickup_rejected'])
                ->update(['status' => 'cancelled', 'failure_reason' => 'Order cancelled by the seller.']);
        });
        \App\Models\ActivityLog::note('Order ' . $order->number() . ' · ' . $reason, $order);

        return back()->with('success', 'Order ' . $order->number() . ' cancelled. Stock was put back and the buyer was told why'
            . ($order->fresh()->isPaidOnline() ? '; their payment is being refunded in full.' : '.'));
    }

    /** Printable waybill for the parcel. */
    public function waybill(int $id)
    {
        $order = $this->ownOrder($id);
        if (! in_array($order->sellerStep(), ['packed', 'ready', 'done'], true) || $order->status === Order::CANCELLED) {
            return redirect()->route('seller.orders.show', $id)->with('error', 'Pack the order before printing its waybill.');
        }
        $bookIds = Product::withTrashed()->where('seller_id', auth()->id())->pluck('id');
        $items   = $order->items()->whereIn('product_id', $bookIds)->with('product')->get();
        $seller  = auth()->user()->loadMissing('sellerApplication');
        $parcel  = \App\Models\Parcel::where('order_id', $order->id)->where('status', '!=', 'cancelled')->first();
        $delivery = \App\Models\Delivery::where('order_id', $order->id)->first();
        $tracking = $parcel->tracking_number ?? $delivery->tracking_number ?? ('ALVY-' . str_pad((string) $order->id, 8, '0', STR_PAD_LEFT));
        $weight   = $order->shipping_weight_kg ?? $items->sum(fn ($i) => (float) ($i->product->weight_kg ?? 0) * $i->quantity);

        return view('seller.orders.waybill', compact('order', 'items', 'seller', 'tracking', 'weight'));
    }

    /** Approve or reject the buyer's return / refund request. */
    public function decideReturn(Request $request, int $id)
    {
        $bookIds = Product::withTrashed()->where('seller_id', auth()->id())->pluck('id');
        abort_unless(OrderItem::where('order_id', $id)->whereIn('product_id', $bookIds)->exists(), 403);

        $order  = Order::findOrFail($id);
        $return = $order->returnRequests()->where('status', 'requested')->first();
        if (! $return || $order->status !== Order::RETURN_REQUESTED) {
            return back()->with('error', 'There is no return request waiting for a decision.');
        }

        $data = $request->validate([
            'decision'    => 'required|in:approve,reject',
            'seller_note' => 'required_if:decision,reject|nullable|string|min:10|max:300',
        ], [
            'seller_note.required_if' => 'Please tell the buyer why the return is rejected.',
            'seller_note.min'         => 'The reason must be 10 to 300 characters.',
            'seller_note.max'         => 'The reason must be 10 to 300 characters.',
        ]);

        $approved = $data['decision'] === 'approve';
        $return->update([
            'status'      => $approved ? 'approved' : 'rejected',
            'seller_note' => $data['seller_note'] ?? null,
            'resolved_at' => now(),
        ]);
        // Approved: refunded. Rejected: back to Delivered, so the buyer can still confirm it.
        $order->update(['status' => $approved ? Order::REFUNDED : Order::DELIVERED]);

        return back()->with('success', $approved ? 'Return approved. The buyer will be refunded.' : 'Return rejected. The buyer was told why.');
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

        $order   = $this->ownOrder($id);
        // Steps can't be skipped: New → Accepted → Packed → Ready for Pickup.
        $step = $order->sellerStep();
        if ($step === 'new' || $step === 'accepted') {
            return back()->with('error', $step === 'new' ? 'Accept the order first, then pack it, before booking the pickup.' : 'Mark the order as packed before booking the pickup.');
        }
        if ($order->status !== Order::PROCESSING) {
            return back()->with('error', 'This order is no longer waiting for pickup.');
        }
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
        $bookIds  = Product::withTrashed()->where('seller_id', auth()->id())->pluck('id');
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
