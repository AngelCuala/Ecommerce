<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Parcel;
use App\Models\ShipmentEvent;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The shipment tracking history: one structured, append-only ShipmentEvent per verified logistics
 * step, and the buyer / seller notifications that go with it.
 *
 * Parcel status changes are recorded automatically (Parcel model events); steps that don't change
 * the status (rider on the way, transfer delayed, exception, correction…) are recorded by the
 * action with record(). Notifications are sent after the database commit and can never undo or
 * corrupt the recorded event: a failure is logged and the event stays.
 */
class ShipmentTracker
{
    /**
     * Who is told about each event type, besides what older actions already send (those keep their
     * own, more specific messages: out for delivery, pickup confirmations, lost/damaged…).
     */
    public const NOTIFY = [
        'seller_processing'             => ['buyer'],
        'ready_for_pickup'              => ['buyer'],
        'pickup_rider_arriving'         => ['seller'],
        'arrived_at_sorting_center'     => ['buyer', 'seller'],
        'arrived_at_destination_center' => ['buyer', 'seller'],
        'transfer_dispatched'           => ['buyer', 'seller'],
        'transfer_delayed'              => ['buyer', 'seller'],
        'delivery_rider_assigned'       => ['buyer', 'seller'],
        'out_for_delivery'              => ['seller'],
        'delivered'                     => ['buyer', 'seller'],
        'delivery_failed'               => ['seller'],
        'exception_reported'            => ['seller'],
        'exception_resolved'            => ['seller'],
    ];

    /** A parcel's status changed (called by the Parcel model). */
    public function statusChanged(Parcel $parcel, ?string $from, string $to): ?ShipmentEvent
    {
        $context = $parcel->tracking;
        $parcel->tracking = [];

        return $this->record($parcel, $context['type'] ?? $this->typeFor($parcel, $from, $to),
            $context + ['from_status' => $from, 'to_status' => $to]);
    }

    private function typeFor(Parcel $parcel, ?string $from, string $to): string
    {
        return match ($to) {
            'pending_pickup'    => 'ready_for_pickup',
            'pickup_approved'   => $from === null ? 'ready_for_pickup' : 'pickup_confirmed',
            'pickup_rejected'   => 'pickup_rejected',
            'in_transit_to_hub' => 'picked_up_from_seller',
            'picked_up'         => $this->atDestinationCenter($parcel) ? 'arrived_at_destination_center' : 'arrived_at_sorting_center',
            'sorting'           => 'sorting_started',
            'sorted'            => match ($from) { 'failed' => 'delivery_rescheduled', 'assigned' => 'rider_unassigned', default => 'sorted' },
            'in_transfer'       => 'transfer_dispatched',
            'assigned'          => 'delivery_rider_assigned',
            'in_transit'        => 'out_for_delivery',
            'delivered'         => 'delivered',
            'failed'            => 'delivery_failed',
            'return_to_sender'  => 'return_to_sender',
            'returned'          => 'returned',
            'cancelled'         => 'cancelled',
            'lost'              => 'lost',
            'damaged'           => 'damaged',
            default             => 'status_changed',
        };
    }

    /** The parcel's sorting center serves the buyer's municipality (its last hub before the rider). */
    public function atDestinationCenter(Parcel $parcel): bool
    {
        $sc = $parcel->current_sorting_center_id ? User::find($parcel->current_sorting_center_id) : null;
        return $sc !== null && app(ParcelService::class)->coversDestination($parcel, $sc);
    }

    /**
     * Record one step. Options: note, meta, location_id, origin_id, destination_id, transfer_id,
     * pickup_id, delivery_id, corrects_event_id, from_status, to_status, dedupe_key (the same key
     * twice records and notifies once), notify (override the recipients), occurred_at.
     */
    public function record(Parcel|Order $subject, string $type, array $o = []): ?ShipmentEvent
    {
        $parcel = $subject instanceof Parcel ? $subject : null;
        $orderId = $parcel ? $parcel->order_id : $subject->id;

        if (! empty($o['dedupe_key']) && ($existing = ShipmentEvent::where('dedupe_key', $o['dedupe_key'])->first())) {
            return $existing;
        }

        $actor = auth()->user();
        $event = ShipmentEvent::create([
            'order_id'          => $orderId,
            'parcel_id'         => $parcel?->id,
            'event_type'        => $type,
            'from_status'       => $o['from_status'] ?? null,
            'to_status'         => $o['to_status'] ?? null,
            'location_id'       => array_key_exists('location_id', $o) ? $o['location_id'] : $parcel?->current_sorting_center_id,
            'origin_id'         => $o['origin_id'] ?? null,
            'destination_id'    => $o['destination_id'] ?? null,
            'actor_id'          => $o['actor_id'] ?? $actor?->id,
            'actor_role'        => $o['actor_role'] ?? $actor?->role,
            'transfer_id'       => $o['transfer_id'] ?? null,
            'pickup_id'         => $o['pickup_id'] ?? null,
            'delivery_id'       => $o['delivery_id'] ?? null,
            'corrects_event_id' => $o['corrects_event_id'] ?? null,
            'note'              => $o['note'] ?? (in_array($type, ['delivery_failed', 'return_to_sender', 'cancelled', 'pickup_rejected', 'lost', 'damaged'], true) ? $parcel?->failure_reason : null),
            'meta'              => $o['meta'] ?? null,
            'dedupe_key'        => $o['dedupe_key'] ?? null,
            'occurred_at'       => $o['occurred_at'] ?? now(),
        ]);

        $recipients = $o['notify'] ?? (self::NOTIFY[$type] ?? []);
        if ($recipients) {
            DB::afterCommit(fn () => $this->notify($event, $recipients));
        }

        return $event;
    }

    /** Tell the buyer and/or seller. Never throws: a failed notification leaves the event as recorded. */
    public function notify(ShipmentEvent $event, array $recipients): void
    {
        try {
            $event->loadMissing('order', 'parcel', 'location', 'origin', 'destination');
            $order = $event->order;
            if (! $order) {
                return;
            }
            $ref = $order->number() . ' (' . ($event->parcel?->tracking_number ?? $order->tracking_number) . ')';
            $body = $ref . ': ' . $event->label() . ($event->note ? ' — ' . $event->note : '') . '. ' . $event->occurred_at->format('M j, g:i A') . '.';

            $to = [
                'buyer'  => [$order->user_id, route('profile.orders.show', $order->id)],
                'seller' => [$event->parcel?->seller_id ?? $order->seller_id, route('seller.orders.show', $order->id)],
            ];
            foreach ($recipients as $role) {
                [$userId, $link] = $to[$role] ?? [null, null];
                if (! $userId) {
                    continue;
                }
                $text = $body . ($role === 'buyer' && $event->event_type === 'delivered' ? ' Please confirm with "Order Received" to complete your order.' : '');
                UserNotification::firstOrCreate(
                    ['user_id' => $userId, 'dedupe_key' => 'shipment-event:' . $event->id],
                    ['title' => $event->label(), 'body' => $text, 'type' => in_array($event->event_type, ['transfer_delayed', 'delivery_failed', 'exception_reported'], true) ? 'warning' : 'info', 'link' => $link],
                );
            }
        } catch (\Throwable $e) {
            Log::warning("Shipment notification for event {$event->id} failed: " . $e->getMessage());
        }
    }

    /**
     * The tracking timeline for an order: its verified events, oldest first. Buyers and sellers
     * see only public steps; staff and admins also see internal ones.
     */
    public function timeline(Order $order, bool $internal = false)
    {
        return ShipmentEvent::where('order_id', $order->id)
            ->with('location', 'origin', 'destination', 'corrects')
            ->orderBy('occurred_at')->orderBy('id')->get()
            ->filter(fn (ShipmentEvent $e) => $internal || $e->isPublic())
            ->values();
    }
}
