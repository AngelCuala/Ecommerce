<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line in a rider's wallet ledger (PAYMENTS_SPEC §4, §6). The balance is the sum of the lines; a line is never
 * overwritten with a new balance.
 *   pickup_credit        +30% of the parcel's shipping fee once the hub received the parcel        available
 *   delivery_credit      +30% once delivered (prepaid), or once the hub confirmed the COD remittance  available / held
 *                        (held while a remittance shortage is open)
 *   shortage_adjustment  − a remittance shortage the hub took from the rider's earnings             available
 *   cashout              − a withdrawal to GCash, Maya or a bank: pending (processing) → paid (completed) or
 *                        rejected (failed: the amount is back in the wallet)
 *   pay                  older pay the hub sent with "Send pay" (before the ledger)
 */
class RiderWalletEntry extends Model
{
    public const TYPE_LABELS = [
        'pickup_credit' => 'Pickup', 'delivery_credit' => 'Delivery', 'shortage_adjustment' => 'Remittance shortage',
        'cashout' => 'Withdrawal', 'pay' => 'Pay from the hub',
    ];

    public const STATUS_LABELS = [
        'available' => 'Available', 'held' => 'On hold (remittance short)',
        'pending' => 'Processing', 'paid' => 'Completed', 'rejected' => 'Failed',
    ];

    protected $fillable = ['rider_id', 'sorting_center_id', 'type', 'source_type', 'source_id', 'parcel_id', 'earned_at', 'status', 'amount', 'jobs',
        'note', 'gcash_number', 'gcash_name', 'payout_method', 'bank_name', 'reference', 'reject_reason', 'created_by', 'processed_by', 'processed_at'];

    protected $casts = ['amount' => 'decimal:2', 'processed_at' => 'datetime', 'earned_at' => 'datetime'];

    public function rider(): BelongsTo { return $this->belongsTo(Rider::class); }
    public function parcel(): BelongsTo { return $this->belongsTo(Parcel::class); }
    public function sortingCenter(): BelongsTo { return $this->belongsTo(User::class, 'sorting_center_id'); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function processor(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }

    /** Lines in the available balance: not a failed withdrawal, not held by a shortage. */
    public function scopeCounted($query)
    {
        return $query->where(fn ($q) => $q->whereNull('status')->orWhereNotIn('status', ['rejected', 'held']));
    }

    public function typeLabel(): string { return self::TYPE_LABELS[$this->type] ?? ucfirst($this->type); }

    public function statusLabel(): ?string { return $this->status ? (self::STATUS_LABELS[$this->status] ?? ucfirst($this->status)) : null; }

    /** Where a withdrawal went: "GCash 0917… (Juan Cruz)". */
    public function destination(): ?string
    {
        if ($this->type !== 'cashout') {
            return null;
        }
        $method = $this->payout_method ?? 'gcash';
        $where = $method === 'bank' ? ($this->bank_name ?: 'Bank') : (PayoutAccount::METHODS[$method] ?? 'GCash');

        return trim("$where {$this->gcash_number}" . ($this->gcash_name ? " ({$this->gcash_name})" : ''));
    }
}
