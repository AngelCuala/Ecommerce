<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = ['order_id', 'thread_key', 'sender_id', 'receiver_id', 'body', 'is_read'];

    public function sender(): BelongsTo   { return $this->belongsTo(User::class, 'sender_id'); }
    public function receiver(): BelongsTo { return $this->belongsTo(User::class, 'receiver_id'); }
    public function order(): BelongsTo    { return $this->belongsTo(Order::class); }

    /** Generate a stable thread key from two user IDs */
    public static function threadKey(int $a, int $b): string
    {
        return min($a, $b) . '_' . max($a, $b);
    }
}
