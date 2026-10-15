<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcelDelivery extends Model
{
    /** Deliveries still in progress. */
    public const OPEN = ['assigned', 'out_for_delivery'];

    /** Up to 3 attempts per parcel (A13). */
    public const MAX_ATTEMPTS = 3;

    /** Reasons a rider can give for a failed attempt; "Refused" returns the parcel at once. */
    public const FAILURE_REASONS = ['Buyer not home', 'Wrong address', 'Refused'];

    /** Wrong OTP entries before OTP entry is locked, and for how long (A32). */
    public const OTP_MAX_TRIES   = 5;
    public const OTP_LOCK_MINUTES = 15;

    public const STATUS_LABELS = [
        'assigned'         => 'Assigned',
        'out_for_delivery' => 'Out for Delivery',
        'delivered'        => 'Delivered',
        'failed'           => 'Failed Delivery',
        'returned'         => 'Return to Sender',
    ];

    protected $fillable = [
        'parcel_id', 'rider_id', 'area_id', 'assigned_by',
        'status', 'remarks',
        'picked_up_at', 'delivered_at',
        'attempt', 'failure_reason', 'otp_code', 'otp_attempts', 'otp_locked_until', 'pod_path', 'cod_amount', 'cod_collected',
    ];

    protected $casts = [
        'picked_up_at'     => 'datetime',
        'delivered_at'     => 'datetime',
        'otp_locked_until' => 'datetime',
        'attempt'          => 'integer',
        'otp_attempts'     => 'integer',
        'cod_amount'       => 'decimal:2',
        'cod_collected'    => 'decimal:2',
    ];

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucwords(str_replace('_', ' ', $this->status));
    }

    public function otpLocked(): bool
    {
        return $this->otp_locked_until && $this->otp_locked_until->isFuture();
    }

    public function parcel(): BelongsTo     { return $this->belongsTo(Parcel::class, 'parcel_id'); }
    public function rider(): BelongsTo      { return $this->belongsTo(Rider::class, 'rider_id'); }
    public function area(): BelongsTo       { return $this->belongsTo(DeliveryArea::class, 'area_id'); }
    public function assignedBy(): BelongsTo { return $this->belongsTo(User::class, 'assigned_by'); }
}
