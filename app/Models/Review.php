<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyer's rating of one item from a completed order. One review per order item; it can be
 * written within REVIEW_DAYS of completion and edited once in that time. The seller can reply.
 */
class Review extends Model
{
    public const REVIEW_DAYS = 30;
    public const MAX_EDITS   = 1;

    protected $fillable = [
        'product_id', 'user_id', 'order_id', 'order_item_id', 'rating', 'comment', 'photo_path',
        'variation_name', 'edit_count', 'seller_reply', 'seller_replied_at',
    ];

    protected $casts = [
        'rating'            => 'integer',
        'edit_count'        => 'integer',
        'seller_replied_at' => 'datetime',
    ];

    public function product(): BelongsTo   { return $this->belongsTo(Product::class)->withTrashed(); }
    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function order(): BelongsTo     { return $this->belongsTo(Order::class); }
    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }

    public function canBeEdited(): bool
    {
        return $this->edit_count < self::MAX_EDITS && $this->order?->reviewWindowOpen();
    }
}
