<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A delivery zone added for a province no zone covered (e.g. Batanes), with its own rate. */
class ShippingZone extends Model
{
    protected $fillable = ['name', 'province_code', 'province_name', 'first_kg', 'additional_kg', 'created_by'];

    protected $casts = ['first_kg' => 'float', 'additional_kg' => 'float'];

    protected static function booted(): void
    {
        static::saved(fn () => app(\App\Services\ShippingService::class)->forget());
        static::deleted(fn () => app(\App\Services\ShippingService::class)->forget());
    }
}
