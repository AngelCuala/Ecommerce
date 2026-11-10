<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryArea extends Model
{
    protected $fillable = [
        'name', 'code', 'description',
        'sorting_center_id', 'municipality', 'region_code', 'province_code', 'municipality_code', 'barangay_code',
        'latitude', 'longitude', 'located_at',   // map location (App\Services\NearestRiders)
    ];

    protected $casts = ['latitude' => 'float', 'longitude' => 'float', 'located_at' => 'datetime'];

    public function sortingCenter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'sorting_center_id');
    }

    /** Riders who cover this barangay (as their main barangay or an extra one). */
    public function riders(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Rider::class, 'rider_areas', 'area_id', 'rider_id');
    }

    public function parcels(): HasMany
    {
        return $this->hasMany(Parcel::class, 'area_id');
    }

    public function parcelDeliveries(): HasMany
    {
        return $this->hasMany(ParcelDelivery::class, 'area_id');
    }
}
