<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A buyer's return / refund request for a delivered order, decided by the seller. */
class ReturnRequest extends Model
{
    public const REASONS = [
        'Damaged item', 'Wrong item received', 'Item not as described', 'Missing parts or items', 'Changed my mind',
    ];

    /** A31: 1 to 5 photos; the seller has 3 days to answer; a rejection can be escalated within 3 days. */
    public const MAX_PHOTOS    = 5;
    public const RESPOND_DAYS  = 3;
    public const ESCALATE_DAYS = 3;

    protected $fillable = [
        'order_id', 'user_id', 'seller_id', 'reason', 'details', 'evidence_path', 'evidence_paths',
        'status', 'seller_note', 'refund_amount', 'resolved_at', 'escalated_at', 'dispute_note',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'resolved_at'   => 'datetime',
        'escalated_at'  => 'datetime',
        'evidence_paths' => 'array',
    ];

    /** Every evidence photo (older requests have just one). */
    public function photos(): array
    {
        return $this->evidence_paths ?: array_filter([$this->evidence_path]);
    }

    public function order(): BelongsTo  { return $this->belongsTo(Order::class); }
    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function seller(): BelongsTo { return $this->belongsTo(User::class, 'seller_id'); }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'approved' => 'Approved — refund on the way',
            'rejected' => 'Rejected by the seller',
            'disputed' => 'In dispute — ALVY is reviewing it',
            'closed'   => 'Closed by ALVY (no refund)',
            default    => 'Waiting for the seller',
        };
    }
}
