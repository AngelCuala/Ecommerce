<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A rate the logistics team set for a zone pair ("NCR-NCR"); it overrides config/shipping.php. */
class ShippingRate extends Model
{
    protected $fillable = ['route', 'first_kg', 'additional_kg', 'updated_by'];

    protected $casts = ['first_kg' => 'float', 'additional_kg' => 'float'];

    protected static function booted(): void
    {
        static::saved(fn () => app(\App\Services\ShippingService::class)->forget());
        static::deleted(fn () => app(\App\Services\ShippingService::class)->forget());
    }
}
