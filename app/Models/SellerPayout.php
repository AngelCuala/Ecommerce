<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A seller's withdrawal (PAYMENTS_SPEC §7): pending (processing: the amount is locked) → paid (completed: ALVY sent
 * it from its bank, with the bank reference) or rejected (failed: the amount is back in the available balance).
 */
class SellerPayout extends Model
{
    /** No minimum (any amount above ₱0 up to the available balance) and no fee. */
    public const MINIMUM = 0.01;

    public const STATUS_LABELS = ['pending' => 'Processing', 'paid' => 'Completed', 'rejected' => 'Failed'];

    protected $fillable = ['seller_id', 'amount', 'status', 'method', 'bank_name', 'account_name', 'account_number', 'request_token', 'reference',
        'reject_reason', 'paid_at', 'processed_by', 'processed_at'];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'processed_at' => 'datetime'];

    public function seller(): BelongsTo { return $this->belongsTo(User::class, 'seller_id'); }
    public function processor(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }

    public function statusLabel(): string { return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status); }

    /** "GCash 0917… (Juan Cruz)"; older requests had no account. */
    public function destination(): ?string
    {
        if (! $this->account_number) {
            return null;
        }
        $where = $this->method === 'bank' ? ($this->bank_name ?: 'Bank') : (PayoutAccount::METHODS[$this->method] ?? 'GCash');

        return "$where {$this->account_number}" . ($this->account_name ? " ({$this->account_name})" : '');
    }
}
