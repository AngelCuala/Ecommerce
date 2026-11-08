<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A hub-to-hub trip driven by a courier of the company:
 * Scheduled → Assigned → Accepted → Loading → In Transit → Arrived → Completed (or Completed with Discrepancy, or Cancelled).
 */
class LinehaulTrip extends Model
{
    public const LABELS = [
        'scheduled'             => 'Unassigned',
        'assigned'              => 'Assigned',
        'accepted'              => 'Accepted',
        'loading'               => 'Loading',
        'in_transit'            => 'In Transit',
        'arrived'               => 'Arrived',
        'completed'             => 'Completed',
        'completed_discrepancy' => 'Completed with Discrepancy',
        'cancelled'             => 'Cancelled',
    ];

    /** A courier's trip that still needs them (one at a time). */
    public const ACTIVE = ['assigned', 'accepted', 'loading', 'in_transit', 'arrived'];

    /** Before departure: the trip can still be edited, reassigned or cancelled. */
    public const BEFORE_DEPARTURE = ['scheduled', 'assigned', 'accepted', 'loading'];

    public const DONE = ['completed', 'completed_discrepancy'];

    protected $fillable = [
        'code', 'sorting_center_id', 'origin_hub_id', 'destination_hub_id', 'courier_id', 'status',
        'destination_sorting_center_id', 'handover_status', 'handover_responded_at', 'handover_responded_by', 'handover_note',
        'scheduled_departure_at', 'assigned_at', 'accepted_at', 'loading_at', 'departed_at', 'arrived_at', 'completed_at',
        'seal_number', 'received_seal', 'seal_discrepancy', 'seal_note', 'eta_at', 'delayed', 'delay_reason',
        'needs_rescan', 'courier_note', 'needs_courier_note', 'pay', 'paid_courier_id',
    ];

    protected $casts = [
        'scheduled_departure_at' => 'datetime', 'assigned_at' => 'datetime', 'accepted_at' => 'datetime',
        'loading_at' => 'datetime', 'departed_at' => 'datetime', 'arrived_at' => 'datetime', 'completed_at' => 'datetime',
        'eta_at' => 'datetime', 'seal_discrepancy' => 'boolean', 'delayed' => 'boolean', 'needs_rescan' => 'boolean',
        'needs_courier_note' => 'boolean', 'pay' => 'decimal:2', 'handover_responded_at' => 'datetime',
    ];

    public function company(): BelongsTo     { return $this->belongsTo(User::class, 'sorting_center_id'); }
    public function origin(): BelongsTo      { return $this->belongsTo(LogisticsHub::class, 'origin_hub_id'); }
    public function destination(): BelongsTo { return $this->belongsTo(LogisticsHub::class, 'destination_hub_id'); }
    public function courier(): BelongsTo     { return $this->belongsTo(Courier::class); }
    public function paidCourier(): BelongsTo { return $this->belongsTo(Courier::class, 'paid_courier_id'); }
    public function items(): HasMany         { return $this->hasMany(LinehaulTripParcel::class); }
    public function events(): HasMany        { return $this->hasMany(LinehaulTripEvent::class)->orderBy('id'); }
    public function locations(): HasMany     { return $this->hasMany(LinehaulTripLocation::class)->orderBy('recorded_at'); }
    public function messages(): HasMany      { return $this->hasMany(LinehaulTripMessage::class)->orderBy('id'); }
    /** The sorting center that receives the trip (the origin company itself for its own hubs). */
    public function destinationCompany(): BelongsTo { return $this->belongsTo(User::class, 'destination_sorting_center_id'); }

    /** The receiving company's id (older trips without one go to the origin company's own hub). */
    public function receiverId(): int { return (int) ($this->destination_sorting_center_id ?: $this->sorting_center_id); }

    /** The trip goes to another logistics company's hub (that company accepts it before departure). */
    public function crossCompany(): bool { return $this->receiverId() !== (int) $this->sorting_center_id; }

    /** Still waiting for the receiving company to accept it (the truck cannot load or depart yet). */
    public function awaitingHandover(): bool { return $this->handover_status === 'pending'; }

    /** The trips a sorting center sends or receives. */
    public function scopeInvolving($q, int $scId)
    {
        return $q->where(fn ($w) => $w->where('sorting_center_id', $scId)->orWhere('destination_sorting_center_id', $scId));
    }

    /** The sorting center sends or receives this trip. */
    public function involves(int $scId): bool { return (int) $this->sorting_center_id === $scId || $this->receiverId() === $scId; }

    /** The parcels still on the trip (not taken off by Logistics). */
    public function onBoard(): HasMany       { return $this->items()->where('status', '!=', 'removed'); }

    public function label(): string { return self::LABELS[$this->status] ?? ucfirst($this->status); }

    public function route(): string { return ($this->origin?->code ?? '?') . ' → ' . ($this->destination?->code ?? '?'); }

    public function weightKg(): float
    {
        return round((float) Parcel::whereIn('id', $this->onBoard()->pluck('parcel_id'))->sum('weight_kg'), 2);
    }

    /** One line of the trip's history and audit log. */
    public function log(string $type, ?string $reason = null, array $extra = []): LinehaulTripEvent
    {
        return $this->events()->create($extra + [
            'type' => $type, 'reason' => $reason, 'courier_id' => $this->courier_id,
            'user_id' => auth()->id(), 'created_at' => now(),
        ]);
    }

    public static function nextCode(): string
    {
        return 'TRIP-' . str_pad((string) ((int) self::max('id') + 1), 6, '0', STR_PAD_LEFT);
    }
}
