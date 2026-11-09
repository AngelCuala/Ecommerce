<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message between the two sorting centers of a linehaul trip (delays, problems, handover details). */
class LinehaulTripMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['linehaul_trip_id', 'user_id', 'body'];

    public function trip(): BelongsTo { return $this->belongsTo(LinehaulTrip::class, 'linehaul_trip_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
