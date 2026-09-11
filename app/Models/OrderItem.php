<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'book_id', 'quantity', 'price',
        'commission_rate', 'commission_amount', 'seller_earning',
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

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    /** Line total the buyer paid */
    public function lineTotal(): float
    {
        return round($this->quantity * $this->price, 2);
    }
}
