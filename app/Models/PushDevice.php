<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A phone signed in to the app that gets push notifications (App\Services\FcmPush). */
class PushDevice extends Model
{
    protected $fillable = ['user_id', 'token', 'platform', 'access_token_id', 'last_seen_at'];

    protected $casts = ['last_seen_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
