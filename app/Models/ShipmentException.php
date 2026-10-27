<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A problem with a shipment that needs an authorized resolution (it is never silently fixed). */
class ShipmentException extends Model
{
    public const TYPES = [
        'damaged_on_arrival' => 'Damaged on arrival',
        'wrong_center'       => 'Arrived at the wrong sorting center',
        'delayed_transfer'   => 'Transfer not confirmed in time',
        'no_route'           => 'No sorting center serves the destination',
        'missing'            => 'Missing / not found',
        'other'              => 'Other',
    ];

    protected $fillable = [
        'parcel_id', 'transfer_id', 'type', 'details', 'sorting_center_id', 'reported_by',
        'status', 'resolution', 'resolved_by', 'resolved_at',
    ];

    protected $casts = ['resolved_at' => 'datetime'];

    public function parcel(): BelongsTo       { return $this->belongsTo(Parcel::class); }
    public function transfer(): BelongsTo     { return $this->belongsTo(ParcelTransfer::class, 'transfer_id'); }
    public function sortingCenter(): BelongsTo { return $this->belongsTo(User::class, 'sorting_center_id'); }
    public function reporter(): BelongsTo     { return $this->belongsTo(User::class, 'reported_by'); }
    public function resolver(): BelongsTo     { return $this->belongsTo(User::class, 'resolved_by'); }

    public function isOpen(): bool { return $this->status === 'open'; }

    public function typeLabel(): string { return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type)); }
}
