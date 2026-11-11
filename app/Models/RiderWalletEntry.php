<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line in a rider's e-wallet:
 *   pay      the hub sent the rider's pay in (+)
 *   cashout  the rider asked for money out to GCash (−): pending → paid (the hub sent it, with the GCash
 *            reference) or rejected (it never left, so it doesn't count against the wallet)
 */
class RiderWalletEntry extends Model
{
    public const TYPE_LABELS = ['pay' => 'Pay from the hub', 'cashout' => 'Cash out to GCash'];

    public const STATUS_LABELS = ['pending' => 'Waiting for the hub', 'paid' => 'Sent to GCash', 'rejected' => 'Rejected'];

    protected $fillable = ['rider_id', 'sorting_center_id', 'type', 'status', 'amount', 'jobs', 'note', 'gcash_number', 'gcash_name',
        'reference', 'reject_reason', 'created_by', 'processed_by', 'processed_at'];

    protected $casts = ['amount' => 'decimal:2', 'processed_at' => 'datetime'];

    public function rider(): BelongsTo { return $this->belongsTo(Rider::class); }
    public function sortingCenter(): BelongsTo { return $this->belongsTo(User::class, 'sorting_center_id'); }
    public function sender(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function processor(): BelongsTo { return $this->belongsTo(User::class, 'processed_by'); }

    /** Lines that count in the balance (a rejected cash-out never left the wallet). */
    public function scopeCounted($query)
    {
        return $query->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'rejected'));
    }

    public function typeLabel(): string { return self::TYPE_LABELS[$this->type] ?? ucfirst($this->type); }

    public function statusLabel(): ?string { return $this->status ? (self::STATUS_LABELS[$this->status] ?? ucfirst($this->status)) : null; }
}
