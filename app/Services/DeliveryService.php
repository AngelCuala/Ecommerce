<?php

namespace App\Services;

use App\Models\Courier;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\UserNotification;

/**
 * Shared rules for the courier delivery workflow (the `deliveries` table):
 *
 *   available (seller scheduled pickup, waiting for a courier)
 *   -> accepted (a courier claimed it)  -> picked_up (courier has the parcel)
 *   -> in_transit (out for delivery)    -> delivered
 *   failed = the job can no longer be completed (e.g. buyer cancelled before pickup)
 *
 * Used by the courier portal, the seller order page and the buyer order actions.
 */
class DeliveryService
{
    /** Order status rank — the marketplace order only ever moves forward. */
    private const ORDER_RANK = ['Pending' => 0, 'Processing' => 1, 'Shipped' => 2, 'Delivered' => 3];

    /** Statuses in which a courier still owns an active job. */
    public const ACTIVE = ['accepted', 'picked_up', 'in_transit'];

    /** Move the order forward to $status (never backwards, never out of Cancelled/Delivered). */
    public function advanceOrder(?Order $order, string $status): void
    {
        if (! $order || in_array($order->status, ['Cancelled', 'Delivered'], true)) {
            return;
        }

        if ((self::ORDER_RANK[$status] ?? 0) > (self::ORDER_RANK[$order->status] ?? 0)) {
            $data = ['status' => $status];
            // Cash on delivery: payment is collected when the parcel is delivered.
            if ($status === 'Delivered' && strtoupper((string) $order->payment_method) === 'COD') {
                $data['payment_status'] = 'Paid';
            }
            $order->update($data);
        }
    }

    // ── Notifications ─────────────────────────────────────────

    public function notifyCourier(Delivery $delivery, string $title, string $body, string $type = 'info'): void
    {
        $userId = $delivery->courier?->user_id;
        if ($userId) {
            $this->notify($userId, $title, $body, $type, route('courier.deliveries.show', $delivery->id));
        }
    }

    public function notifyBuyer(Delivery $delivery, string $title, string $body, string $type = 'info'): void
    {
        if ($delivery->order?->user_id) {
            $this->notify($delivery->order->user_id, $title, $body, $type, route('profile.orders'));
        }
    }

    /** Every seller with items in the delivery's order. */
    public function notifySellers(Delivery $delivery, string $title, string $body, string $type = 'info'): void
    {
        $order = $delivery->order;
        if (! $order) return;

        $order->loadMissing('items.book');
        $order->items->pluck('book.seller_id')->filter()->unique()
            ->each(fn ($sellerId) => $this->notify((int) $sellerId, $title, $body, $type, route('seller.orders.show', $order->id)));
    }

    /** Tell every approved courier that a new delivery request can be claimed. */
    public function notifyAvailableCouriers(Delivery $delivery): void
    {
        $userIds = Courier::where('status', 'approved')
            ->whereHas('user', fn ($q) => $q->where('role', 'courier'))
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $this->notify((int) $userId, 'New delivery request',
                $this->ref($delivery) . ' is ready for pickup. Accept it from your dashboard.',
                'info', route('courier.dashboard'));
        }
    }

    public function notify(int $userId, string $title, string $body, string $type = 'info', ?string $link = null): void
    {
        UserNotification::create([
            'user_id' => $userId,
            'title'   => $title,
            'body'    => $body,
            'type'    => $type,
            'link'    => $link,
        ]);
    }

    public function ref(Delivery $delivery): string
    {
        return 'Order #' . str_pad($delivery->order_id, 6, '0', STR_PAD_LEFT);
    }
}
