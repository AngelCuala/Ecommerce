<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Moving a parcel from one sorting center to another.
 *
 *   pending (Transfer Created / Awaiting Dispatch) → in_transit (Dispatched / In Transit) → received
 *   pending → cancelled            (before dispatch only: the parcel never left)
 *   in_transit → exception         (wrong center, missing…; resolved by an admin, never deleted)
 *   delayed_at set on in_transit   (not confirmed received in time: flagged for investigation)
 *   rejected                       (older records from the previous accept/reject handshake)
 */
class ParcelTransfer extends Model
{
    public const STATUS_LABELS = [
        'pending'    => 'Awaiting Dispatch',
        'in_transit' => 'In Transit',
        'received'   => 'Received',
        'cancelled'  => 'Cancelled',
        'exception'  => 'Exception',
        'rejected'   => 'Rejected',
    ];

    /** A parcel has at most one of these at a time. */
    public const ACTIVE = ['pending', 'in_transit', 'exception'];

    protected $fillable = [
        'reference', 'parcel_id', 'from_sorting_center_id', 'to_sorting_center_id',
        'initiated_by', 'received_by', 'reason', 'status', 'received_at',
        'dispatched_at', 'dispatched_by', 'transporter_rider_id', 'expected_by', 'delayed_at',
        'cancelled_at', 'cancelled_by', 'exception_reason',
    ];

    protected $casts = [
        'received_at'   => 'datetime',
        'dispatched_at' => 'datetime',
        'expected_by'   => 'datetime',
        'delayed_at'    => 'datetime',
        'cancelled_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::created(function (ParcelTransfer $t) {
            if (! $t->reference) {
                $t->forceFill(['reference' => 'TRF-' . now()->format('ymd') . '-' . str_pad((string) $t->id, 6, '0', STR_PAD_LEFT)])->saveQuietly();
            }
        });
    }

    // ── Relationships ──────────────────────────────────────────
    public function parcel(): BelongsTo            { return $this->belongsTo(Parcel::class); }
    public function fromSortingCenter(): BelongsTo { return $this->belongsTo(User::class, 'from_sorting_center_id'); }
    public function toSortingCenter(): BelongsTo   { return $this->belongsTo(User::class, 'to_sorting_center_id'); }
    public function initiatedBy(): BelongsTo       { return $this->belongsTo(User::class, 'initiated_by'); }
    public function receivedBy(): BelongsTo        { return $this->belongsTo(User::class, 'received_by'); }
    public function dispatchedBy(): BelongsTo      { return $this->belongsTo(User::class, 'dispatched_by'); }
    public function transporter(): BelongsTo       { return $this->belongsTo(Rider::class, 'transporter_rider_id'); }

    // ── Helpers ────────────────────────────────────────────────
    public function isPending(): bool   { return $this->status === 'pending'; }
    public function isInTransit(): bool { return $this->status === 'in_transit'; }
    public function isReceived(): bool  { return $this->status === 'received'; }
    public function isRejected(): bool  { return $this->status === 'rejected'; }
    public function isDelayed(): bool   { return $this->isInTransit() && $this->delayed_at !== null; }

    public function statusLabel(): string
    {
        return $this->isDelayed() ? 'Delayed' : (self::STATUS_LABELS[$this->status] ?? ucfirst($this->status));
    }
}
