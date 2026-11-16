<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyer's return, carried like a pickup (App\Services\ReturnShipments):
 *   requested → pickup_assigned (a rider) → picked_up (photo + waybill scan at the buyer) → at_hub
 *   → (in_transit on a hub-to-hub trip → at_hub at the next hub …) → to_seller (a rider brings it back) → returned.
 * At each hub the item is 1 sorted (next stop), 2 checked (condition), 3 scanned for the record, 4 sent on
 * (stepAtHub()). Cancelled if the return is withdrawn before pickup; missing if a trip arrives without it.
 */
class ReturnShipment extends Model
{
    public const OPEN = ['requested', 'pickup_assigned', 'picked_up', 'at_hub', 'in_transit', 'to_seller'];

    /** The condition the hub records at the check. */
    public const CONDITIONS = ['good' => 'Good', 'damaged' => 'Damaged', 'opened' => 'Opened / tampered', 'wrong_item' => 'Wrong item'];

    public const STATUS_LABELS = [
        'requested'       => 'Waiting for a rider',
        'pickup_assigned' => 'Rider going to the buyer',
        'picked_up'       => 'Picked up · on the way to the hub',
        'at_hub'          => 'At the hub · being sorted and checked',
        'in_transit'      => 'On a hub-to-hub trip',
        'to_seller'       => 'On the way to the seller',
        'missing'         => 'Missing in transit',
        'returned'        => 'Returned to the seller',
        'cancelled'       => 'Cancelled',
    ];

    protected $fillable = [
        'return_request_id', 'order_id', 'parcel_id', 'sorting_center_id', 'status',
        'pickup_rider_id', 'pickup_assigned_at', 'acknowledged_at', 'picked_up_at', 'pickup_proof_path',
        'received_at', 'received_by', 'seller_rider_id', 'seller_assigned_at', 'returned_at', 'returned_proof_path', 'cancel_reason',
        'current_hub_id', 'next_stop', 'next_hub_id', 'hub_sorted_at', 'hub_checked_at', 'hub_condition', 'hub_check_note', 'hub_check_photo',
        'hub_scanned_at', 'linehaul_trip_id',
    ];

    protected $casts = [
        'pickup_assigned_at' => 'datetime', 'acknowledged_at' => 'datetime', 'picked_up_at' => 'datetime',
        'received_at' => 'datetime', 'seller_assigned_at' => 'datetime', 'returned_at' => 'datetime',
        'hub_sorted_at' => 'datetime', 'hub_checked_at' => 'datetime', 'hub_scanned_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Back with the seller (or lost on the way): the buyer's refund can go (App\Services\Refunds).
        static::updated(function (ReturnShipment $rs) {
            if ($rs->wasChanged('status') && in_array($rs->status, ['returned', 'missing', 'cancelled'], true)) {
                app(\App\Services\Refunds::class)->shipmentMoved($rs);
            }
        });
    }

    public function returnRequest(): BelongsTo { return $this->belongsTo(ReturnRequest::class); }
    public function order(): BelongsTo         { return $this->belongsTo(Order::class); }
    public function parcel(): BelongsTo        { return $this->belongsTo(Parcel::class); }
    public function sortingCenter(): BelongsTo { return $this->belongsTo(User::class, 'sorting_center_id'); }
    public function pickupRider(): BelongsTo   { return $this->belongsTo(Rider::class, 'pickup_rider_id'); }
    public function sellerRider(): BelongsTo   { return $this->belongsTo(Rider::class, 'seller_rider_id'); }
    public function currentHub(): BelongsTo    { return $this->belongsTo(LogisticsHub::class, 'current_hub_id'); }
    public function nextHub(): BelongsTo       { return $this->belongsTo(LogisticsHub::class, 'next_hub_id'); }
    public function trip(): BelongsTo          { return $this->belongsTo(LinehaulTrip::class, 'linehaul_trip_id'); }
    public function scans(): \Illuminate\Database\Eloquent\Relations\HasMany { return $this->hasMany(ReturnScanEvent::class)->orderBy('created_at')->orderBy('id'); }

    /**
     * The step waiting at the hub that has the item: sort → check → scan → send (null when it isn't at a hub, or is
     * already on its way: on a trip, or with the rider to the seller). A picked-up item still coming in starts at sort.
     */
    public function stepAtHub(): ?string
    {
        if (! in_array($this->status, ['picked_up', 'at_hub'], true) || $this->linehaul_trip_id) {
            return null;   // not at a hub, or already on a trip waiting to leave
        }

        return match (true) {
            ! $this->hub_sorted_at  => 'sort',
            ! $this->hub_checked_at => 'check',
            ! $this->hub_scanned_at => 'scan',
            default                 => 'send',
        };
    }

    public function isOpen(): bool { return in_array($this->status, self::OPEN, true); }

    public function statusLabel(): string { return self::STATUS_LABELS[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status)); }

    /** What the rider scans: the waybill on the delivered parcel's label (or its tracking number). */
    public function code(): string
    {
        return $this->parcel?->waybill_number ?: ($this->parcel?->tracking_number ?: ($this->order?->tracking_number ?: 'RET-' . $this->order_id));
    }

    /** Where the rider collects it: the buyer's delivery address. */
    public function buyerAddress(): string
    {
        return $this->parcel?->dropoff_address ?: ($this->order?->fullAddress() ?: (string) $this->order?->shipping_address);
    }

    /** Where it goes back to: the seller's pickup address. */
    public function sellerAddress(): string
    {
        return (string) ($this->parcel?->pickup_address ?: '—');
    }
}
