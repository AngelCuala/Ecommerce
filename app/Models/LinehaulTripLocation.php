<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Where the courier was during a trip (sent by the courier app while In Transit). */
class LinehaulTripLocation extends Model
{
    public $timestamps = false;

    protected $fillable = ['linehaul_trip_id', 'lat', 'lng', 'recorded_at'];

    protected $casts = ['recorded_at' => 'datetime', 'lat' => 'float', 'lng' => 'float'];
}
