<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The money of an approved return on its way to the buyer. See App\Services\Refunds and the refunds migration. */
class Refund extends Model
{
    public const STATUS_LABELS = [
        'awaiting_details' => 'Waiting for the buyer\'s details',
        'waiting_for_item' => 'Waiting for the item to reach the seller',
        'ready_to_send'    => 'Ready to send',
        'sent'             => 'Sent',
    ];

    public const METHODS = ['gcash' => 'GCash', 'maya' => 'Maya', 'bank' => 'Bank account', 'original' => 'Original payment'];

    public const BANKS = [
        'BDO Unibank', 'BPI', 'Metrobank', 'Land Bank of the Philippines', 'PNB', 'Security Bank', 'UnionBank',
        'RCBC', 'China Bank', 'EastWest Bank', 'Maybank', 'GoTyme Bank', 'SeaBank', 'CIMB Bank', 'Maya Bank', 'Other bank',
    ];

    protected $fillable = [
        'order_id', 'return_request_id', 'user_id', 'amount', 'status', 'method', 'account_name', 'account_number', 'bank_name',
        'details_at', 'item_back_at', 'ready_at', 'sent_at', 'sent_by', 'reference', 'admin_note',
    ];

    protected $casts = [
        'amount' => 'decimal:2', 'account_number' => 'encrypted',
        'details_at' => 'datetime', 'item_back_at' => 'datetime', 'ready_at' => 'datetime', 'sent_at' => 'datetime',
    ];

    public function order(): BelongsTo         { return $this->belongsTo(Order::class); }
    public function returnRequest(): BelongsTo { return $this->belongsTo(ReturnRequest::class); }
    public function user(): BelongsTo          { return $this->belongsTo(User::class); }
    public function sender(): BelongsTo        { return $this->belongsTo(User::class, 'sent_by'); }

    public function isSent(): bool { return $this->status === 'sent'; }

    public function statusLabel(): string { return self::STATUS_LABELS[$this->status] ?? $this->status; }

    /** "GCash · 0917•••4567", "BDO Unibank · •••• 1234", "Back to your Credit / Debit card". */
    public function destination(bool $full = false): string
    {
        if ($this->method === 'original') {
            return 'Back to your ' . ($this->order?->paymentMethodLabel() ?? 'payment method');
        }
        if (! $this->method) {
            return 'Not given yet';
        }
        $n = (string) $this->account_number;
        $shown = $full ? $n : (strlen($n) > 4 ? str_repeat('•', min(6, strlen($n) - 4)) . substr($n, -4) : $n);

        return ($this->method === 'bank' ? $this->bank_name : self::METHODS[$this->method]) . ' · ' . $shown;
    }
}
