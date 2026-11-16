<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scan of a driver's Handover QR (or 6-digit code) by a hub: at loading (origin) or at arrival (destination).
 * Confirmed when the hub finishes: loading seals the truck, arrival closes receiving. See App\Services\TripHandover.
 */
class LinehaulHandover extends Model
{
    public const STAGES = ['load' => 'Loading', 'receive' => 'Arrival'];

    protected $fillable = [
        'linehaul_trip_id', 'stage', 'hub_id', 'courier_id', 'plate_number', 'scanned_by', 'method', 'token_hash',
        'expected', 'scanned', 'weight_kg', 'note', 'verified_at', 'confirmed_at',
    ];

    protected $casts = ['verified_at' => 'datetime', 'confirmed_at' => 'datetime', 'weight_kg' => 'decimal:2'];

    public function trip(): BelongsTo    { return $this->belongsTo(LinehaulTrip::class, 'linehaul_trip_id'); }
    public function hub(): BelongsTo     { return $this->belongsTo(LogisticsHub::class, 'hub_id'); }
    public function courier(): BelongsTo { return $this->belongsTo(Courier::class); }
    public function scanner(): BelongsTo { return $this->belongsTo(User::class, 'scanned_by'); }

    public function isLoad(): bool { return $this->stage === 'load'; }

    public function stageLabel(): string { return self::STAGES[$this->stage] ?? ucfirst($this->stage); }
}
