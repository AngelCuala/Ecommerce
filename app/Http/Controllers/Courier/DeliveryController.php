<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Delivery;
use App\Services\DeliveryService;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(private DeliveryService $deliveries) {}

    /** The signed-in user's approved courier profile (403 otherwise, e.g. an admin with no profile). */
    public static function currentCourier(): Courier
    {
        $courier = auth()->user()->courier;
        abort_unless($courier && $courier->isApproved(), 403, 'An approved courier profile is required to use the courier portal.');
        return $courier;
    }

    private function myCourier(): Courier
    {
        return self::currentCourier();
    }

    /** Accept an available delivery — first come, first served. */
    public function accept(int $deliveryId)
    {
        $courier = $this->myCourier();

        // Atomic claim: only succeeds if nobody else took it first and the order is still live.
        $claimed = Delivery::where('id', $deliveryId)
            ->where('status', 'available')
            ->whereNull('courier_id')
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', \App\Models\Order::LOGISTICS_DONE))
            // One route per order: never claim an order the sorting center is handling.
            ->whereDoesntHave('order.parcels', fn ($q) => $q->whereNotIn('status', \App\Models\Parcel::CLOSED_STATUSES))
            ->update([
                'courier_id'   => $courier->id,
                'courier_name' => $courier->fullName(),
                'status'       => 'accepted',
                'accepted_at'  => now(),
                'updated_at'   => now(),
            ]);

        if (! $claimed) {
            return back()->with('error', 'This delivery request is no longer available.');
        }

        $delivery = Delivery::with('order.items.product', 'courier')->findOrFail($deliveryId);
        $this->deliveries->advanceOrder($delivery->order, 'Processing');

        $ref = $this->deliveries->ref($delivery);
        $this->deliveries->notifyCourier($delivery, 'New delivery assigned',
            "You accepted {$ref}. Pick it up from {$delivery->pickupName()}.");
        $this->deliveries->notifySellers($delivery, 'Courier assigned',
            "{$courier->fullName()} will pick up {$ref}.");
        $this->deliveries->notifyBuyer($delivery, 'Courier assigned',
            "A courier has been assigned to your {$ref}.");

        return back()->with('success', 'Delivery accepted! Proceed to the seller\'s location to pick up the order.');
    }

    /** Confirm item pickup from seller. */
    public function pickup(int $deliveryId)
    {
        $delivery = $this->getCourierDelivery($deliveryId, 'accepted');

        $delivery->update([
            'status'       => 'picked_up',
            'picked_up_at' => now(),
        ]);
        $this->deliveries->advanceOrder($delivery->order, 'Shipped');

        $ref = $this->deliveries->ref($delivery);
        $this->deliveries->notifyCourier($delivery, 'Pickup confirmed', "{$ref} is now with you. Start the delivery when you're on the way.");
        $this->deliveries->notifySellers($delivery, 'Parcel picked up', "The courier picked up {$ref}.");
        $this->deliveries->notifyBuyer($delivery, 'Order picked up', "Your {$ref} has been picked up by the courier.");

        return back()->with('success', 'Pickup confirmed! Now deliver to the buyer.');
    }

    /** Mark out for delivery. */
    public function inTransit(int $deliveryId)
    {
        $delivery = $this->getCourierDelivery($deliveryId, 'picked_up');
        $delivery->update(['status' => 'in_transit']);
        $this->deliveries->advanceOrder($delivery->order, 'Shipped');

        $this->deliveries->notifyBuyer($delivery, 'Out for delivery',
            'Your ' . $this->deliveries->ref($delivery) . ' is on its way.');

        return back()->with('success', 'Status updated to Out for Delivery.');
    }

    /** Complete delivery. */
    public function complete(Request $request, int $deliveryId)
    {
        $request->validate(['notes' => 'nullable|string|max:500']);

        $delivery = $this->getCourierDelivery($deliveryId, 'in_transit');

        $delivery->update([
            'status'       => 'delivered',
            'delivered_at' => now(),
            // Keep the seller's pickup notes unless the courier adds delivery notes.
            'notes'        => $request->filled('notes') ? $request->input('notes') : $delivery->notes,
        ]);

        $this->deliveries->advanceOrder($delivery->order, 'Delivered');

        // Running total kept in sync; the portal itself sums delivered records.
        $this->myCourier()->increment('total_earnings', $delivery->delivery_fee);

        $ref = $this->deliveries->ref($delivery);
        $this->deliveries->notify(auth()->id(), 'Delivery completed',
            $ref . ' delivered. ₱' . number_format($delivery->delivery_fee, 2) . ' added to your earnings.',
            'info', route('courier.profit'));
        $this->deliveries->notifySellers($delivery, 'Order delivered', "{$ref} was delivered to the buyer.");
        $this->deliveries->notifyBuyer($delivery, 'Order delivered', "Your {$ref} has been delivered.");

        return back()->with('success', 'Delivery completed! ₱' . number_format($delivery->delivery_fee, 2) . ' added to your earnings.');
    }

    /** View delivery history with optional filters (status, order id, date). */
    /** Status groups on Delivery History: [label, pill class]. */
    public const HISTORY_STATUSES = [
        'in_progress' => ['In progress', 'cx-pill-orange'],
        'delivered'   => ['Delivered', 'cx-pill-green'],
        'failed'      => ['Failed / returned', 'cx-pill-red'],
    ];

    /**
     * Delivery History: the rider's delivery jobs from their logistics company (parcel_deliveries, every
     * attempt) plus any jobs from the older courier marketplace (deliveries), newest first, filterable by
     * order number or tracking number, status and day.
     */
    public function history(Request $request)
    {
        $request->validate([
            'q'      => 'nullable|string|max:40',
            'status' => 'nullable|in:' . implode(',', array_keys(self::HISTORY_STATUSES)),
            'date'   => 'nullable|date_format:Y-m-d',
        ]);
        $rider   = \App\Models\Rider::where('user_id', auth()->id())->where('application_status', 'approved')->first();
        $courier = auth()->user()->courier;
        abort_unless($rider || ($courier && $courier->isApproved()), 403, 'An approved courier profile is required to use the courier portal.');

        $rows = collect();
        if ($rider) {
            $rows = \App\Models\ParcelDelivery::where('rider_id', $rider->id)->with(['parcel.order', 'parcel.seller', 'area'])->get()
                ->map(function (\App\Models\ParcelDelivery $d) {
                    $done = $d->status === 'delivered';
                    return (object) [
                        'order'    => $d->parcel?->order?->number() ?? '—',
                        'tracking' => $d->parcel?->tracking_number,
                        'buyer'    => $d->parcel?->receiver_name ?? $d->parcel?->order?->full_name,
                        'place'    => collect([$d->area?->name, $d->parcel?->destination_municipality])->filter()->implode(', '),
                        'seller'   => $d->parcel?->seller?->storeName(),
                        'group'    => match ($d->status) { 'delivered' => 'delivered', 'failed', 'returned' => 'failed', default => 'in_progress' },
                        'label'    => match ($d->status) {
                            'assigned' => 'Assigned', 'out_for_delivery' => 'Out for delivery', 'delivered' => 'Delivered',
                            'returned' => 'Returned', default => 'Failed',
                        },
                        'note'     => in_array($d->status, ['failed', 'returned'], true) ? ($d->failure_reason ?: $d->remarks) : null,
                        'attempt'  => (int) $d->attempt,
                        'at'       => $done ? ($d->delivered_at ?? $d->updated_at) : $d->updated_at,
                        'cod'      => (float) ($done ? $d->cod_collected : $d->cod_amount),
                        'pay'      => $done ? \App\Services\RiderDispatch::PAY_DELIVERY : 0.0,
                        'url'      => route('courier.rider.deliveries.show', $d->id),
                    ];
                });
        }
        if ($courier && $courier->isApproved()) {
            $rows = $rows->concat(Delivery::where('courier_id', $courier->id)->with('order')->get()->map(fn (Delivery $d) => (object) [
                'order'    => '#' . str_pad((string) $d->order_id, 6, '0', STR_PAD_LEFT),
                'tracking' => $d->order?->tracking_number,
                'buyer'    => $d->order?->full_name,
                'place'    => $d->order?->city,
                'seller'   => $d->pickupName(),
                'group'    => match ($d->status) { 'delivered' => 'delivered', 'failed' => 'failed', default => 'in_progress' },
                'label'    => $d->courierStatusLabel(),
                'note'     => null,
                'attempt'  => 1,
                'at'       => $d->delivered_at ?? $d->updated_at,
                'cod'      => 0.0,
                'pay'      => $d->status === 'delivered' ? (float) $d->delivery_fee : 0.0,
                'url'      => route('courier.deliveries.show', $d->id),
            ]));
        }
        $rows = $rows->sortByDesc(fn ($r) => $r->at?->timestamp ?? 0)->values();

        // Totals cover everything, so the cards don't change with the filters.
        $summary = [
            'delivered'   => $rows->where('group', 'delivered')->count(),
            'in_progress' => $rows->where('group', 'in_progress')->count(),
            'failed'      => $rows->where('group', 'failed')->count(),
            'earned'      => round($rows->sum('pay'), 2),
        ];

        $q      = trim((string) $request->query('q'));
        $status = $request->query('status');
        $date   = $request->query('date');
        $needle = ltrim(strtolower($q), '#');
        $filtered = $rows
            // A number is an exact order number (66, 000066 or #000066). Other text matches part of the tracking
            // number; a short number never does, or "66" would also find every tracking number containing 66.
            ->when($needle !== '', fn ($c) => $c->filter(fn ($r) => (ctype_digit($needle) && (int) ltrim($r->order, '#') === (int) $needle)
                || ((! ctype_digit($needle) || strlen($needle) >= 6) && $r->tracking && str_contains(strtolower($r->tracking), $needle))))
            ->when($status, fn ($c) => $c->where('group', $status))
            ->when($date, fn ($c) => $c->filter(fn ($r) => $r->at?->toDateString() === $date))
            ->values();

        $page = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $deliveries = new \Illuminate\Pagination\LengthAwarePaginator($filtered->forPage($page, 15)->values(), $filtered->count(), 15, $page,
            ['path' => $request->url(), 'query' => $request->query()]);

        return view('courier.history', compact('deliveries', 'summary', 'q', 'status', 'date'));
    }

    /** View a single delivery detail (only the courier's own jobs). */
    public function show(int $deliveryId)
    {
        $courier  = $this->myCourier();
        $delivery = Delivery::where('id', $deliveryId)
            ->where('courier_id', $courier->id)
            ->with(['order.user', 'order.items.product'])
            ->firstOrFail();

        return view('courier.delivery-show', compact('delivery'));
    }

    /** A job owned by this courier in the expected status; 404 for anyone else's job. */
    private function getCourierDelivery(int $id, string $expectedStatus): Delivery
    {
        return Delivery::where('id', $id)
            ->where('courier_id', $this->myCourier()->id)
            ->where('status', $expectedStatus)
            ->with(['order.items.product', 'courier'])
            ->firstOrFail();
    }
}
