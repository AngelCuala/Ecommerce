<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyer's return, carried like a pickup (App\Services\ReturnShipments):
 *   requested → pickup_assigned (a rider) → picked_up (photo + waybill scan at the buyer) → at_hub (the hub received it)
 *   → to_seller (a rider brings it back) → returned (handed to the seller).  Cancelled if the return is withdrawn before pickup.
 */
class ReturnShipment extends Model
{
    public const OPEN = ['requested', 'pickup_assigned', 'picked_up', 'at_hub', 'to_seller'];

    public const STATUS_LABELS = [
        'requested'       => 'Waiting for a rider',
        'pickup_assigned' => 'Rider going to the buyer',
        'picked_up'       => 'Picked up · on the way to the hub',
        'at_hub'          => 'At the hub · waiting for a rider to the seller',
        'to_seller'       => 'On the way to the seller',
        'returned'        => 'Returned to the seller',
        'cancelled'       => 'Cancelled',
    ];

    protected $fillable = [
        'return_request_id', 'order_id', 'parcel_id', 'sorting_center_id', 'status',
        'pickup_rider_id', 'pickup_assigned_at', 'acknowledged_at', 'picked_up_at', 'pickup_proof_path',
        'received_at', 'received_by', 'seller_rider_id', 'seller_assigned_at', 'returned_at', 'returned_proof_path', 'cancel_reason',
    ];

    protected $casts = [
        'pickup_assigned_at' => 'datetime', 'acknowledged_at' => 'datetime', 'picked_up_at' => 'datetime',
        'received_at' => 'datetime', 'seller_assigned_at' => 'datetime', 'returned_at' => 'datetime',
    ];

    public function returnRequest(): BelongsTo { return $this->belongsTo(ReturnRequest::class); }
    public function order(): BelongsTo         { return $this->belongsTo(Order::class); }
    public function parcel(): BelongsTo        { return $this->belongsTo(Parcel::class); }
    public function sortingCenter(): BelongsTo { return $this->belongsTo(User::class, 'sorting_center_id'); }
    public function pickupRider(): BelongsTo   { return $this->belongsTo(Rider::class, 'pickup_rider_id'); }
    public function sellerRider(): BelongsTo   { return $this->belongsTo(Rider::class, 'seller_rider_id'); }

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
