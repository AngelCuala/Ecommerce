<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryArea extends Model
{
    protected $fillable = ['name', 'code', 'description'];

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
