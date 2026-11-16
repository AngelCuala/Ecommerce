<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line in a seller's wallet ledger (PAYMENTS_SPEC §5): one per delivered order.
 *   order_credit     + the seller's 90% of the order: on_hold (return window) → available (completed) or reversed (refunded)
 *   return_reversal  − an order refunded after its money was already available
 * Withdrawals are SellerPayout rows; the balance is the lines minus the withdrawals that are processing or completed.
 */
class SellerWalletEntry extends Model
{
    public const STATUS_LABELS = ['on_hold' => 'On hold', 'available' => 'Available', 'reversed' => 'Reversed'];

    protected $fillable = ['seller_id', 'type', 'order_id', 'amount', 'status', 'release_at', 'earned_at', 'note'];

    protected $casts = ['amount' => 'decimal:2', 'release_at' => 'datetime', 'earned_at' => 'datetime'];

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }

    public function statusLabel(): string { return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status); }
}
