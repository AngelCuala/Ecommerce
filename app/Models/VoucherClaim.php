<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A voucher a buyer saved to their wallet (My Vouchers). */
class VoucherClaim extends Model
{
    protected $fillable = ['user_id', 'voucher_id'];

    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function voucher(): BelongsTo { return $this->belongsTo(Voucher::class); }
}
