<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A step in a trip's history and audit log: who, when, old and new value, and the reason. Never edited. */
class LinehaulTripEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['linehaul_trip_id', 'courier_id', 'user_id', 'type', 'old_value', 'new_value', 'reason', 'photo_path', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function trip(): BelongsTo    { return $this->belongsTo(LinehaulTrip::class, 'linehaul_trip_id'); }
    public function courier(): BelongsTo { return $this->belongsTo(Courier::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
}
