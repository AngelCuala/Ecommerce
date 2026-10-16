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

        $sortingCenters = self::sortingCenterChoices(auth()->user());

        return view('seller.orders.show', compact('order', 'parcel', 'sortingCenters'));
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

    /** The seller got a Return-to-Sender parcel back: the order is cancelled and its stock restored (A3). */
    public function returnedReceived(int $id)
    {
        $order  = $this->ownOrder($id);
        $parcel = \App\Models\Parcel::where('order_id', $order->id)->where('seller_id', auth()->id())
            ->where(fn ($q) => $q->where('status', 'return_to_sender')
                ->orWhere(fn ($r) => $r->where('status', 'returned')->whereNotNull('returned_at')->whereNull('returned_received_at')))
            ->first();
        if (! $parcel) {
            return back()->with('error', 'There is no returning parcel to confirm for this order.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($parcel, $order) {
            \App\Models\ParcelDelivery::where('parcel_id', $parcel->id)->latest('id')->first()?->update(['status' => 'returned']);
            $broughtBack = $parcel->status === 'returned';   // a rider already handed it back; the order is cancelled
            $parcel->update(['status' => 'returned', 'returned_received_at' => now()] + ($broughtBack ? [] : ['returned_at' => now()]));
            if ($broughtBack && $order->fresh()->status === Order::CANCELLED) {
                $order->restock();   // stock comes back only now (A3)
            } else {
                // Every parcel back with the seller → the order is cancelled; Order::booted() restocks it.
                app(\App\Services\ParcelService::class)->syncOrder($parcel->fresh());
            }
        });
        \App\Models\ActivityLog::change('Return to Sender', 'Returned to seller', $parcel->failure_reason);
        \App\Models\ActivityLog::note('Returned parcel ' . $parcel->tracking_number . ' received', $parcel);

        return back()->with('success', 'Returned parcel received. Its stock is back in your inventory.');
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
        \App\Models\ActivityLog::change('Return requested', $approved ? 'Return approved' : 'Return rejected', $data['seller_note'] ?? null);

        if ($approved && $return->isPartial()) {
            // A8: only these items are refunded; the rest of the order goes on as delivered.
            \Illuminate\Support\Facades\DB::transaction(function () use ($order, $return) {
                foreach (OrderItem::where('order_id', $order->id)->whereIn('id', $return->item_ids)->get() as $item) {
                    $item->forceFill(['refunded_at' => now(), 'refund_amount' => \App\Models\ReturnRequest::itemRefund($item)])->save();
                }
                $order->forceFill(['status' => Order::DELIVERED])->saveQuietly();
                $order->events()->create(['status' => Order::DELIVERED, 'note' => 'Partial refund of ₱' . number_format($return->refund_amount, 2) . ' approved', 'created_at' => now()]);
                if ($order->isPaidOnline()) {
                    app(\App\Services\PaymentService::class)->refund($order, 'Partial return approved', (float) $return->refund_amount);
                } else {
                    $order->forceFill(['refunded_amount' => (float) $order->refunded_amount + (float) $return->refund_amount])->saveQuietly();
                }
                \App\Models\UserNotification::create(['user_id' => $order->user_id, 'title' => 'Partial refund approved', 'type' => 'success',
                    'body' => 'The seller approved your return for order ' . $order->number() . '. ₱' . number_format($return->refund_amount, 2)
                        . ($order->isPaidOnline() ? ' goes back to your payment method.' : ' goes to your refund account.'),
                    'link' => route('profile.orders.show', $order->id, false)]);
            });

            return back()->with('success', 'Partial return approved. The buyer will be refunded ₱' . number_format($return->refund_amount, 2) . '.');
        }

        // Approved: refunded. Rejected: back to Delivered, so the buyer can still confirm it.
        $order->update(['status' => $approved ? Order::REFUNDED : Order::DELIVERED]);

        return back()->with('success', $approved ? 'Return approved. The buyer will be refunded.' : 'Return rejected. The buyer was told why.');
    }

    /** Schedule courier pickup & create/update delivery record */
    /** Logistics companies a seller can choose for the pickup: those covering the seller's city first. */
    public static function sortingCenterChoices(\App\Models\User $seller): \Illuminate\Support\Collection
    {
        $origin = app(\App\Services\DeliveryRoutingService::class)->sellerOrigin($seller);
        $names = \App\Models\SortingCenterApplication::where('status', 'approved')->pluck('business_name', 'user_id');

        return \App\Models\User::where('role', 'sorting_center')->where('approval_status', 'approved')->get()
            ->map(function ($sc) use ($origin, $names) {
                $sc->display_name = $names[$sc->id] ?? $sc->name;
                $sc->covers_seller = $origin && $origin->municipality_code && $sc->assigned_municipality_code === $origin->municipality_code;
                $sc->rider_count = \App\Models\Rider::where('sorting_center_id', $sc->id)->where('application_status', 'approved')->where('is_active', true)->count();
                return $sc;
            })
            ->sortBy(fn ($sc) => [$sc->covers_seller ? 0 : 1, $sc->rider_count > 0 ? 0 : 1, $sc->display_name])
            ->values();
    }

    /**
     * Ready for Pickup: the seller chooses the logistics company (sorting center); that company's
     * riders pick the parcel up. The job goes straight to its Rider Dispatch and is offered to
     * an Online rider right away.
     */
    public function handover(Request $request, int $id)
    {
        $request->validate([
            'sorting_center_id'   => ['required', 'integer', \Illuminate\Validation\Rule::exists('users', 'id')->where('role', 'sorting_center')->where('approval_status', 'approved')],
            'pickup_scheduled_at' => 'required|date',
            'notes'               => 'nullable|string|max:500',
        ], [
            'sorting_center_id.required' => 'Choose the logistics company whose rider will pick up the parcel.',
            'sorting_center_id.exists'   => 'Choose one of the listed logistics companies.',
            'pickup_scheduled_at.required' => 'Choose the pickup date and time.',
        ]);

        $order = $this->ownOrder($id);
        // Steps can't be skipped: New → Accepted → Packed → Ready for Pickup.
        $step = $order->sellerStep();
        if ($step === 'new' || $step === 'accepted') {
            return back()->with('error', $step === 'new' ? 'Accept the order first, then pack it, before booking the pickup.' : 'Mark the order as packed before booking the pickup.');
        }
        if ($order->status !== Order::PROCESSING) {
            return back()->with('error', 'This order is no longer waiting for pickup.');
        }
        // Older orders booked with a direct courier keep that courier.
        if (\App\Models\Delivery::where('order_id', $order->id)->whereIn('status', \App\Models\Delivery::OPEN_STATUSES)->exists()) {
            return back()->with('error', 'This order is already booked with a direct courier.');
        }
        $existing = \App\Models\Parcel::where('order_id', $order->id)->where('seller_id', auth()->id())->first();
        if ($existing && \App\Models\ParcelPickup::where('parcel_id', $existing->id)->whereIn('status', \App\Models\ParcelPickup::OPEN)->exists()) {
            return back()->with('error', 'A rider is already assigned to pick up this parcel. Contact the logistics company to change it.');
        }
        if ($existing && ! in_array($existing->status, ['pending_pickup', 'pickup_approved', 'pickup_rejected', 'cancelled'], true)) {
            return back()->with('error', "{$existing->tracking_number} is already {$existing->statusLabel()}.");
        }

        $sc = \App\Models\User::findOrFail($request->sorting_center_id);
        $svc = app(\App\Services\ParcelService::class);

        $offer = \Illuminate\Support\Facades\DB::transaction(function () use ($order, $sc, $request, $svc) {
            $parcel = $svc->createForSeller($order, auth()->user(), $request->pickup_scheduled_at, $request->notes, $sc);
            // The seller chose the company, so the pickup is confirmed for it right away.
            $parcel->update(['status' => 'pickup_approved', 'current_sorting_center_id' => $sc->id, 'verified_at' => now(), 'failure_reason' => null]);
            $svc->syncOrder($parcel);
            $svc->notify($sc->id, 'New pickup', "{$parcel->tracking_number} from " . auth()->user()->storeName() . ' is ready for pickup'
                . ' (' . \Carbon\Carbon::parse($request->pickup_scheduled_at)->format('M j, g:i A') . ').', 'info', route('sc.dispatch'));

            return app(\App\Services\RiderDispatch::class)->offer($parcel->fresh());
        });

        $name = \App\Models\SortingCenterApplication::where('user_id', $sc->id)->where('status', 'approved')->value('business_name') ?: $sc->name;
        \App\Models\ActivityLog::note('Order ' . $order->number() . ' ready for pickup by ' . $name, $order);

        return back()->with('success', "Ready for Pickup. A rider from {$name} will pick it up"
            . ($offer ? ' (offered to ' . $offer->rider->full_name . ').' : '; the job is waiting in their dispatch for an available rider.'));
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
