<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Parcel extends Model
{
    protected $fillable = [
        'tracking_number',
        'seller_id', 'pickup_address', 'dropoff_address',
        'receiver_name', 'receiver_phone',
        'weight_kg', 'size', 'notes',
        'area_id', 'status',
        'verified_by', 'verified_at',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'weight_kg'   => 'decimal:2',
    ];

    public function seller(): BelongsTo      { return $this->belongsTo(User::class, 'seller_id'); }
    public function area(): BelongsTo        { return $this->belongsTo(DeliveryArea::class, 'area_id'); }
    public function verifiedBy(): BelongsTo  { return $this->belongsTo(User::class, 'verified_by'); }

    public function parcelDelivery(): HasOne
    {
        return $this->hasOne(ParcelDelivery::class, 'parcel_id');
    }

    /** Generate a unique tracking number */
    public static function generateTracking(): string
    {
        do {
            $number = 'ALVY-' . strtoupper(substr(md5(uniqid()), 0, 10));
        } while (static::where('tracking_number', $number)->exists());

        return $number;
    }
}
