<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Parcel extends Model
{
    protected $fillable = [
        'tracking_number', 'order_id',
        'seller_id', 'pickup_address', 'dropoff_address',
        'destination_municipality', 'destination_province',
        'receiver_name', 'receiver_phone',
        'weight_kg', 'size', 'notes', 'pickup_scheduled_at',
        'area_id', 'status', 'transfer_status',
        'current_sorting_center_id',
        'verified_by', 'verified_at', 'received_at', 'sorted_at', 'failure_reason',
        'rts_rider_id', 'rts_assigned_at', 'returned_at', 'returned_received_at', 'exception_photo_path',
    ];

    protected $casts = [
        'unassigned_at'       => 'datetime',
        'verified_at'         => 'datetime',
        'received_at'         => 'datetime',
        'sorted_at'           => 'datetime',
        'rts_assigned_at'     => 'datetime',
        'returned_at'         => 'datetime',
        'returned_received_at' => 'datetime',
        'pickup_scheduled_at' => 'datetime',
        'weight_kg'           => 'decimal:2',
    ];

    /** Human labels for each pipeline status (sorting-center wording). */
    public const STATUS_LABELS = [
        'pending_pickup'  => 'Ready for Pickup',
        'pickup_approved' => 'Pickup Confirmed',
        'in_transit_to_hub' => 'Picked Up by Rider',
        'pickup_rejected' => 'Pickup Rejected',
        'picked_up'       => 'At Sorting Center',
        'sorted'          => 'Sorted',
        'assigned'        => 'Assigned to Rider',
        'in_transit'      => 'Out for Delivery',
        'delivered'       => 'Delivered',
        'failed'          => 'Delivery Failed',
        'return_to_sender' => 'Return to Sender',
        'returned'        => 'Returned to Seller',
        'cancelled'       => 'Cancelled',
        'lost'            => 'Lost',
        'damaged'         => 'Damaged',
    ];

    /** Statuses after which the sorting-center workflow is finished (no longer an active route). */
    public const CLOSED_STATUSES = ['delivered', 'returned', 'cancelled', 'lost', 'damaged'];

    /** Dashboard / shipment-list groups (L1): every status is in exactly one. */
    public const BUCKETS = [
        'pending'    => ['pending_pickup', 'pickup_approved'],
        'in_transit' => ['in_transit_to_hub', 'picked_up', 'sorted', 'assigned', 'in_transit'],
        'delivered'  => ['delivered'],
        'failed'     => ['failed', 'return_to_sender', 'lost', 'damaged'],
        'returned'   => ['returned', 'pickup_rejected', 'cancelled'],
    ];

    public function rtsRider(): BelongsTo { return $this->belongsTo(Rider::class, 'rts_rider_id'); }

    /** Shipment steps on the buyer's order timeline (rider assignment is written by the dispatcher). */
    protected static function booted(): void
    {
        static::updated(function (Parcel $parcel) {
            if ($parcel->wasChanged('status') && $parcel->order_id && $parcel->status !== 'assigned') {
                $parcel->timeline($parcel->statusLabel() . ($parcel->status === 'picked_up' && $parcel->received_at ? ' — received ' . $parcel->received_at->format('M j, g:i A') : ''));
            }
        });
    }

    /** Add a shipment step to the order timeline. */
    public function timeline(string $note): void
    {
        OrderStatusEvent::create(['order_id' => $this->order_id, 'status' => 'Shipment', 'note' => $this->tracking_number . ': ' . $note, 'created_at' => now()]);
    }

    public function order(): BelongsTo       { return $this->belongsTo(Order::class); }
    public function seller(): BelongsTo      { return $this->belongsTo(User::class, 'seller_id'); }
    public function area(): BelongsTo        { return $this->belongsTo(DeliveryArea::class, 'area_id'); }
    public function verifiedBy(): BelongsTo  { return $this->belongsTo(User::class, 'verified_by'); }
    public function currentSortingCenter(): BelongsTo { return $this->belongsTo(User::class, 'current_sorting_center_id'); }

    /** Latest delivery assignment (a parcel can be re-assigned after a failed attempt). */
    public function parcelDelivery(): HasOne
    {
        return $this->hasOne(ParcelDelivery::class, 'parcel_id')->latestOfMany();
    }

    /** Every delivery attempt for this parcel. */
    public function deliveries(): HasMany
    {
        return $this->hasMany(ParcelDelivery::class, 'parcel_id');
    }

    /** First-mile rider trips (seller → sorting center). */
    public function pickups(): HasMany
    {
        return $this->hasMany(ParcelPickup::class, 'parcel_id');
    }

    public function pickup(): HasOne
    {
        return $this->hasOne(ParcelPickup::class, 'parcel_id')->latestOfMany();
    }

    public function jobOffers(): HasMany
    {
        return $this->hasMany(RiderJobOffer::class, 'parcel_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(ParcelTransfer::class, 'parcel_id');
    }

    public function pendingTransfer(): HasOne
    {
        return $this->hasOne(ParcelTransfer::class, 'parcel_id')->where('status', 'pending');
    }

    public function isBeingTransferred(): bool
    {
        return $this->transfer_status === 'outgoing';
    }

    public function isIncomingTransfer(): bool
    {
        return $this->transfer_status === 'incoming';
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucwords(str_replace('_', ' ', $this->status));
    }

    /** Generate a unique tracking number */
    public static function generateTracking(): string
    {
        do {
            $number = 'ALVY-' . strtoupper(substr(md5(uniqid('', true)), 0, 10));
        } while (static::where('tracking_number', $number)->exists());

        return $number;
    }
}
