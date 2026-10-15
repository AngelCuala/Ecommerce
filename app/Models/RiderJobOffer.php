<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A job (pickup or delivery of a parcel) offered to one rider for 60 seconds. */
class RiderJobOffer extends Model
{
    public const PICKUP   = 'pickup';
    public const DELIVERY = 'delivery';

    protected $fillable = ['parcel_id', 'kind', 'attempt', 'rider_id', 'sorting_center_id', 'status', 'expires_at', 'responded_at'];

    protected $casts = ['expires_at' => 'datetime', 'responded_at' => 'datetime', 'attempt' => 'integer'];

    public function parcel(): BelongsTo { return $this->belongsTo(Parcel::class); }
    public function rider(): BelongsTo  { return $this->belongsTo(Rider::class); }

    public function isOpen(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isFuture();
    }

    public function secondsLeft(): int
    {
        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }
}
