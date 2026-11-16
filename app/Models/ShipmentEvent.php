<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One verified step in a shipment's tracking history. Append-only: rows are never edited or
 * deleted; a mistake is fixed by an authorized "correction" event that points to the original
 * (corrects_event_id). Written by App\Services\ShipmentTracker.
 */
class ShipmentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'order_id', 'parcel_id', 'event_type', 'from_status', 'to_status',
        'location_id', 'origin_id', 'destination_id', 'actor_id', 'actor_role',
        'transfer_id', 'pickup_id', 'delivery_id', 'corrects_event_id',
        'note', 'meta', 'dedupe_key', 'occurred_at',
    ];

    protected $casts = ['meta' => 'array', 'occurred_at' => 'datetime'];

    /**
     * What buyers and sellers see for each event ({location}, {origin}, {destination} are filled in).
     * Event types not listed here are internal (shown to logistics staff and admins only).
     */
    public const LABELS = [
        'order_placed'              => 'Order Placed',
        'seller_processing'         => 'Seller Processing',
        'package_packed'            => 'Preparing Package — packed by the seller',
        'ready_for_pickup'          => 'Ready for Pickup',
        'pickup_confirmed'          => 'Pickup Confirmed by {location}',
        'pickup_rejected'           => 'Pickup Request Declined',
        'pickup_rider_assigned'     => 'Pickup Rider Assigned',
        'pickup_rider_arriving'     => 'Rider on the Way to the Seller',
        'picked_up_from_seller'     => 'Picked Up from Seller',
        'arrived_at_sorting_center' => 'Arrived at Sorting Center: {location}',
        'arrived_at_destination_center' => 'At Destination Sorting Center: {location}',
        'sorting_started'           => 'Sorting in Progress at {location}',
        'sorted'                    => 'Sorted and Ready for Dispatch',
        'transfer_dispatched'       => 'Dispatched from {origin} — In Transit to Sorting Center {destination}',
        'transfer_delayed'          => 'Transfer Delayed',
        'delivery_rider_assigned'   => 'Delivery Rider Assigned',
        'delivery_rider_accepted'   => 'Rider Accepted the Delivery',
        'rider_unassigned'          => 'Waiting for a Delivery Rider',
        'delivery_rescheduled'      => 'Delivery Rescheduled',
        'out_for_delivery'          => 'Out for Delivery',
        'delivered'                 => 'Delivered',
        'received_by_buyer'         => 'Received by Buyer',
        'delivery_failed'           => 'Delivery Attempt Failed',
        'return_to_sender'          => 'Returning to Seller',
        'returned'                  => 'Returned to Seller',
        'cancelled'                 => 'Shipment Cancelled',
        'lost'                      => 'Reported Lost',
        'damaged'                   => 'Reported Damaged',
        'exception_reported'        => 'Shipment Under Review',
        'exception_resolved'        => 'Review Completed',
        'correction'                => 'Tracking Record Corrected',
    ];

    /** Internal-only steps (staff and admins see them; buyers and sellers don't). */
    public const INTERNAL = ['transfer_created', 'transfer_cancelled', 'delivery_rider_declined', 'pickup_rider_declined', 'status_changed'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Shipment events are append-only; record a correction instead.'));
        static::deleting(fn () => throw new \LogicException('Shipment events are append-only; record a correction instead.'));
    }

    public function parcel(): BelongsTo      { return $this->belongsTo(Parcel::class); }
    public function order(): BelongsTo       { return $this->belongsTo(Order::class); }
    public function location(): BelongsTo    { return $this->belongsTo(User::class, 'location_id'); }
    public function origin(): BelongsTo      { return $this->belongsTo(User::class, 'origin_id'); }
    public function destination(): BelongsTo { return $this->belongsTo(User::class, 'destination_id'); }
    public function actor(): BelongsTo       { return $this->belongsTo(User::class, 'actor_id'); }
    public function transfer(): BelongsTo    { return $this->belongsTo(ParcelTransfer::class, 'transfer_id'); }
    public function corrects(): BelongsTo    { return $this->belongsTo(self::class, 'corrects_event_id'); }

    public function isPublic(): bool
    {
        return isset(self::LABELS[$this->event_type]);
    }

    /** Label for buyers and sellers (sorting centers by their business name). */
    public function label(): string
    {
        $text = self::LABELS[$this->event_type] ?? ucwords(str_replace('_', ' ', $this->event_type));
        $name = fn (?User $u) => $u ? (\App\Support\SortingCenters::displayName($u)) : 'the sorting center';

        return strtr($text, [
            '{location}'    => $name($this->location),
            '{origin}'      => $name($this->origin),
            '{destination}' => $name($this->destination),
        ]);
    }
}
