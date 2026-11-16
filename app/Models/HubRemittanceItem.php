<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One delivery on a hub's remittance report: tracking number, COD expected and collected, shipping fee, amount due. */
class HubRemittanceItem extends Model
{
    protected $fillable = ['hub_remittance_id', 'parcel_delivery_id', 'parcel_id', 'order_id', 'tracking_number', 'cod', 'cod_expected', 'cod_collected',
        'shipping_fee', 'amount_due', 'mismatch'];

    protected $casts = ['cod' => 'boolean', 'mismatch' => 'boolean', 'cod_expected' => 'decimal:2', 'cod_collected' => 'decimal:2',
        'shipping_fee' => 'decimal:2', 'amount_due' => 'decimal:2'];

    public function remittance(): BelongsTo { return $this->belongsTo(HubRemittance::class, 'hub_remittance_id'); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function delivery(): BelongsTo { return $this->belongsTo(ParcelDelivery::class, 'parcel_delivery_id'); }
}
