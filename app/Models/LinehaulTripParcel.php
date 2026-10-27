<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One parcel on a linehaul trip: pending → loaded → received (or missing, damaged, removed). */
class LinehaulTripParcel extends Model
{
    protected $fillable = ['linehaul_trip_id', 'parcel_id', 'status', 'loaded_at', 'received_at', 'damage_photo_path'];

    protected $casts = ['loaded_at' => 'datetime', 'received_at' => 'datetime'];

    public function trip(): BelongsTo   { return $this->belongsTo(LinehaulTrip::class, 'linehaul_trip_id'); }
    public function parcel(): BelongsTo { return $this->belongsTo(Parcel::class); }
}
