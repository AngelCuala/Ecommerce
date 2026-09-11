<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    protected $fillable = [
        'admin_id', 'title', 'body', 'audience', 'type',
        'is_active', 'published_at', 'expires_at',
    ];

    protected $casts = [
        'is_active'    => 'boolean',
        'published_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function typeColor(): array
    {
        return match($this->type) {
            'warning'     => ['bg'=>'#FFFBEB','border'=>'#FDE68A','text'=>'#92400E','icon'=>'⚠️'],
            'maintenance' => ['bg'=>'#EFF6FF','border'=>'#BFDBFE','text'=>'#1E40AF','icon'=>'🔧'],
            'promo'       => ['bg'=>'#FFF0E6','border'=>'#FFD6B8','text'=>'#C2410C','icon'=>'🎉'],
            default       => ['bg'=>'#F0FDF4','border'=>'#BBF7D0','text'=>'#166534','icon'=>'ℹ️'],
        };
    }

    /** Active announcements for a given audience */
    public static function activeFor(string $audience): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_active', true)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn($q) => $q->where('audience', 'all')->orWhere('audience', $audience))
            ->latest()
            ->get();
    }
}
