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

    protected $fillable = [
        'order_id', 'user_id', 'seller_id', 'reason', 'details', 'evidence_path',
        'status', 'seller_note', 'refund_amount', 'resolved_at',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'resolved_at'   => 'datetime',
    ];

    public function order(): BelongsTo  { return $this->belongsTo(Order::class); }
    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function seller(): BelongsTo { return $this->belongsTo(User::class, 'seller_id'); }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'approved' => 'Approved — refund on the way',
            'rejected' => 'Rejected by the seller',
            default    => 'Waiting for the seller',
        };
    }
}
