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
        'order_id', 'user_id', 'seller_id', 'reason', 'details', 'evidence_path', 'evidence_paths', 'item_ids',
        'status', 'seller_note', 'refund_amount', 'resolved_at', 'escalated_at', 'dispute_note',
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'resolved_at'   => 'datetime',
        'escalated_at'  => 'datetime',
        'evidence_paths' => 'array',
        'item_ids'       => 'array',
    ];

    /** Only some items of the order are returned (A8). */
    public function isPartial(): bool
    {
        return ! empty($this->item_ids);
    }

    /** What the buyer paid for an item: its price minus its share of the promotions and vouchers. */
    public static function itemRefund(OrderItem $item): float
    {
        return round(max(0, $item->quantity * (float) $item->price - (float) $item->promo_discount
            - (float) $item->seller_discount - (float) $item->platform_discount), 2);
    }

    /** Every evidence photo (older requests have just one). */
    public function photos(): array
    {
        return $this->evidence_paths ?: array_filter([$this->evidence_path]);
    }

    protected static function booted(): void
    {
        // The buyer asked: the hub collects the item right away (Return Items), like a seller pickup.
        static::created(fn (ReturnRequest $r) => app(\App\Services\ReturnShipments::class)->open($r));

        static::updated(function (ReturnRequest $r) {
            if (! $r->wasChanged('status')) {
                return;
            }
            // Rejected or closed before the rider collected it: no trip.
            if (in_array($r->status, ['rejected', 'closed'], true)) {
                app(\App\Services\ReturnShipments::class)->cancelIfNotCollected($r, 'Return ' . $r->status . '.');
            }
            // The seller hears when the buyer is refunded (unless the seller just did it).
            if ($r->status === 'approved' && $r->seller_id && (int) auth()->id() !== (int) $r->seller_id) {
                UserNotification::create(['user_id' => $r->seller_id, 'title' => 'Return refunded',
                    'body' => ($r->order?->number() ?? 'An order') . ': the buyer was refunded ₱' . number_format((float) $r->refund_amount, 2) . '. ' . ($r->seller_note ?: ''),
                    'type' => 'warning', 'link' => route('seller.orders.show', $r->order_id)]);
            }
        });
    }

    public function shipment(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(ReturnShipment::class); }

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
