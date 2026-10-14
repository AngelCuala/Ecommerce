<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends Model
{
    protected $fillable = [
        'user_id',
        'label',        // e.g. Home, Work, Other
        'full_name',
        'phone',
        'address_line',
        'barangay',
        'city',
        'province',
        'zip',
        'country',
        'region',
        'province_code',      // PSGC codes from the address dropdowns (shipping zone, delivery routing)
        'municipality_code',
        'barangay_code',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Human-readable one-liner for display */
    public function getFullAddressAttribute(): string
    {
        return collect([
            $this->address_line,
            $this->barangay,
            $this->city,
            $this->province,
            $this->region,
            $this->zip,
        ])->filter()->unique()->implode(', ');
    }
}
