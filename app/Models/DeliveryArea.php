<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryArea extends Model
{
    protected $fillable = [
        'name', 'code', 'description',
        'sorting_center_id', 'municipality', 'municipality_code', 'barangay_code',
    ];

    public function sortingCenter(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'sorting_center_id');
    }

    public function riders(): HasMany
    {
        return $this->hasMany(Rider::class, 'area_id');
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
