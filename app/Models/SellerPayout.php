<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A seller asking ALVY to send their available earnings. */
class SellerPayout extends Model
{
    public const MINIMUM = 100.00;

    protected $fillable = ['seller_id', 'amount', 'status', 'request_token', 'reference', 'paid_at'];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];

    public function seller(): BelongsTo { return $this->belongsTo(User::class, 'seller_id'); }
}
