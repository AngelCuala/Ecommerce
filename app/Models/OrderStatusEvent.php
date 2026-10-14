<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One step on an order's timeline (written whenever the order's status changes). */
class OrderStatusEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['order_id', 'status', 'note', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
