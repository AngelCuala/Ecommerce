<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A hub's remittance to ALVY (PAYMENTS_SPEC §3.1–3.2): the item price of the COD orders its riders delivered (cash the
 * hub holds), net of the shipping fees ALVY owes the hub for prepaid orders, with one line per delivery.
 *   submitted → confirmed (ALVY received the amount and every line matches its order) or flagged (a mismatch)
 */
class HubRemittance extends Model
{
    public const STATUS_LABELS = ['submitted' => 'Waiting for ALVY', 'confirmed' => 'Confirmed', 'flagged' => 'Flagged'];

    protected $fillable = ['sorting_center_id', 'amount', 'status', 'deposit_reference', 'received_amount', 'note', 'submitted_by', 'confirmed_by', 'confirmed_at'];

    protected $casts = ['amount' => 'decimal:2', 'received_amount' => 'decimal:2', 'confirmed_at' => 'datetime'];

    public function sortingCenter(): BelongsTo { return $this->belongsTo(User::class, 'sorting_center_id'); }
    public function submitter(): BelongsTo { return $this->belongsTo(User::class, 'submitted_by'); }
    public function confirmer(): BelongsTo { return $this->belongsTo(User::class, 'confirmed_by'); }
    public function items(): HasMany { return $this->hasMany(HubRemittanceItem::class); }

    public function reference(): string
    {
        return 'HR-' . $this->created_at->copy()->tz(config('app.business_timezone'))->format('ymd') . '-' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    public function statusLabel(): string { return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status); }
}
