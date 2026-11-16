<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One recorded step of a returned item at a hub or on a trip (who, where, when, and what was found). Never edited. */
class ReturnScanEvent extends Model
{
    public const UPDATED_AT = null;

    public const LABELS = [
        'arrived'   => 'Arrived at the hub',
        'sorted'    => 'Sorted',
        'checked'   => 'Checked',
        'scanned'   => 'Waybill scanned for the record',
        'loaded'    => 'Put on a hub-to-hub trip',
        'departed'  => 'Left on the trip',
        'received'  => 'Received from the trip',
        'to_seller' => 'Given to a rider for the seller',
        'missing'   => 'Missing when the trip arrived',
        'removed'   => 'Taken off the trip',
    ];

    protected $fillable = ['return_shipment_id', 'sorting_center_id', 'hub_id', 'type', 'note', 'condition', 'photo_path', 'actor_id', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    public function hub(): BelongsTo   { return $this->belongsTo(LogisticsHub::class, 'hub_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }

    public function label(): string { return self::LABELS[$this->type] ?? ucfirst($this->type); }
}
