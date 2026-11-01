<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A buyer asked to be told before a flash sale starts. */
class FlashSaleReminder extends Model
{
    protected $fillable = ['flash_sale_id', 'user_id'];

    public function flashSale(): BelongsTo { return $this->belongsTo(FlashSale::class); }
    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
}
