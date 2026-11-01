<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One use of a voucher on an order. Removed again if the order is cancelled. */
class VoucherRedemption extends Model
{
    protected $fillable = ['voucher_id', 'user_id', 'order_id', 'discount_amount'];

    protected $casts = ['discount_amount' => 'float'];

    public function voucher(): BelongsTo { return $this->belongsTo(Voucher::class); }
    public function order(): BelongsTo   { return $this->belongsTo(Order::class); }
}
