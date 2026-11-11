<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A seller sent contact details in a buyer chat (App\Services\ContactStrikes). The seller can appeal it; an accepted
 * appeal means it no longer counts. Deleted after RETENTION_DAYS (contact-strikes:prune).
 */
class ContactStrike extends Model
{
    public const RETENTION_DAYS = 365;

    protected $fillable = ['user_id', 'message_id', 'thread', 'kinds', 'reviewed_by', 'reviewed_at',
        'appeal_status', 'appeal_reason', 'appealed_at', 'appeal_note', 'appeal_decided_by', 'appeal_decided_at'];

    protected $casts = ['reviewed_at' => 'datetime', 'appealed_at' => 'datetime', 'appeal_decided_at' => 'datetime'];

    public const KIND_LABELS = ['phone' => 'phone number', 'email' => 'email', 'link' => 'link', 'social' => 'social media handle'];

    public const APPEAL_LABELS = ['pending' => 'Appeal under review', 'accepted' => 'Appeal accepted: not counted', 'rejected' => 'Appeal rejected'];

    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function message(): BelongsTo  { return $this->belongsTo(Message::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class, 'reviewed_by'); }
    public function decider(): BelongsTo  { return $this->belongsTo(User::class, 'appeal_decided_by'); }

    /** Strikes that count (an accepted appeal cancels one). */
    public function scopeCounted($query)
    {
        return $query->where(fn ($q) => $q->whereNull('appeal_status')->orWhere('appeal_status', '!=', 'accepted'));
    }

    public function kindsLabel(): string
    {
        return implode(', ', array_map(fn ($k) => self::KIND_LABELS[$k] ?? $k, array_filter(explode(',', $this->kinds))));
    }

    public function appealLabel(): ?string
    {
        return $this->appeal_status ? (self::APPEAL_LABELS[$this->appeal_status] ?? ucfirst($this->appeal_status)) : null;
    }

    /** A seller may appeal once, while the strike still counts (within the counting window). */
    public function canAppeal(): bool
    {
        return $this->appeal_status === null && $this->created_at?->gte(now()->subDays(\App\Services\ContactStrikes::WINDOW_DAYS));
    }
}
