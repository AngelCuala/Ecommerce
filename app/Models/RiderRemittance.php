<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** COD cash a rider hands to their logistics company; only the company can confirm it. */
class RiderRemittance extends Model
{
    protected $fillable = ['rider_id', 'sorting_center_id', 'amount', 'expected_amount', 'status', 'request_token', 'confirmed_by', 'confirmed_at'];

    protected $casts = ['amount' => 'decimal:2', 'expected_amount' => 'decimal:2', 'confirmed_at' => 'datetime'];

    public function rider(): BelongsTo { return $this->belongsTo(Rider::class); }

    /** Less cash than the COD balance at the time. */
    public function shortfall(): float
    {
        return round(max(0, (float) $this->expected_amount - (float) $this->amount), 2);
    }
}
