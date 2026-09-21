<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParcelTransfer extends Model
{
    protected $fillable = [
        'parcel_id',
        'from_sorting_center_id',
        'to_sorting_center_id',
        'initiated_by',
        'received_by',
        'reason',
        'status',
        'received_at',
    ];

    protected $casts = [
        'received_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────
    public function parcel(): BelongsTo
    {
        return $this->belongsTo(Parcel::class);
    }

    public function fromSortingCenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_sorting_center_id');
    }

    public function toSortingCenter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_sorting_center_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    // ── Helpers ────────────────────────────────────────────────
    public function isPending(): bool  { return $this->status === 'pending'; }
    public function isReceived(): bool { return $this->status === 'received'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }
}
