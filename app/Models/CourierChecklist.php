<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A pre-trip checklist a courier sent to go On Duty (Tires, Lights, Brakes, Fuel, Cargo door lock). */
class CourierChecklist extends Model
{
    public const ITEMS = ['Tires', 'Lights', 'Brakes', 'Fuel', 'Cargo door lock'];

    protected $fillable = ['courier_id', 'items', 'passed', 'note', 'lat', 'lng'];

    protected $casts = ['items' => 'array', 'passed' => 'boolean'];

    public function courier(): BelongsTo { return $this->belongsTo(Courier::class); }
}
