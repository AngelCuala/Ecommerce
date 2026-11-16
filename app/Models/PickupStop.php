<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One pickup visit to one seller on one day, in a time window (PAYMENTS_SPEC §11): one rider collects all its parcels. */
class PickupStop extends Model
{
    /** A parcel still to be collected on this stop. */
    public const OPEN_PARCELS = ['pending_pickup', 'pickup_approved'];

    protected $fillable = ['sorting_center_id', 'seller_id', 'rider_id', 'pickup_date', 'window_start', 'window_end'];

    protected $casts = ['pickup_date' => 'date'];

    public function seller(): BelongsTo { return $this->belongsTo(User::class, 'seller_id'); }
    public function sortingCenter(): BelongsTo { return $this->belongsTo(User::class, 'sorting_center_id'); }
    public function rider(): BelongsTo { return $this->belongsTo(Rider::class); }
    public function parcels(): HasMany { return $this->hasMany(Parcel::class); }

    public function code(): string
    {
        return 'PS-' . $this->pickup_date->format('ymd') . '-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    /** "Today, 1–5 PM" / "Tomorrow, 9 AM–12 PM" / "Nov 20, 1–5 PM". */
    public function windowLabel(): string
    {
        return \App\Services\PickupStops::label($this->pickup_date, $this->window_start, $this->window_end);
    }
}
