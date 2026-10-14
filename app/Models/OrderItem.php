<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'variation_id', 'variation_name', 'flash_sale_id', 'quantity', 'price',
        'commission_rate', 'commission_amount', 'seller_earning',
        'seller_discount', 'platform_discount',   // this line's share of the order's vouchers
    ];

    protected $casts = [
        'price'             => 'decimal:2',
        'commission_rate'   => 'decimal:2',
        'commission_amount' => 'decimal:2',
        'seller_earning'    => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Includes deleted listings, so order history still shows what was bought. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id')->withTrashed();
    }

    public function review(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Review::class);
    }

    /** Line total the buyer paid */
    public function lineTotal(): float
    {
        return round($this->quantity * $this->price, 2);
    }
}
