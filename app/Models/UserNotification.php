<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserNotification extends Model
{
    protected $fillable = [
        'user_id', 'title', 'body', 'type', 'link', 'read_at', 'dedupe_key',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    /** Every in-app notification also goes to the user's phones as a push notification (when Firebase is set up). */
    protected static function booted(): void
    {
        static::created(function (UserNotification $n) {
            if (! \App\Models\PushDevice::where('user_id', $n->user_id)->exists()) {
                return;
            }
            try {
                app(\App\Services\FcmPush::class)->notification($n);
            } catch (\Throwable $e) {
                report($e);   // a push failing never stops what made the notification
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUnread(): bool
    {
        return is_null($this->read_at);
    }

    /** Scope: unread notifications */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }
}
