<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An emailed set-password link for a rider or courier made by a logistics company. Only the token's hash is kept. */
class AccountActivation extends Model
{
    public const HOURS = 24;

    protected $fillable = ['user_id', 'token_hash', 'expires_at', 'used_at'];

    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public static function findByToken(string $token): ?self
    {
        return self::where('token_hash', hash('sha256', $token))->first();
    }

    public function isExpired(): bool { return $this->expires_at->isPast(); }
}
