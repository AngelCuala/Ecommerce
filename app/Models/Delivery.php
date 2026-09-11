<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    protected $fillable = [
        'order_id', 'courier_id', 'courier_name', 'tracking_number',
        'delivery_fee', 'status',
        'pickup_scheduled_at', 'handed_over_at',
        'accepted_at', 'picked_up_at', 'delivered_at', 'notes',
    ];

    protected $casts = [
        'accepted_at'         => 'datetime',
        'picked_up_at'        => 'datetime',
        'delivered_at'        => 'datetime',
        'pickup_scheduled_at' => 'datetime',
        'handed_over_at'      => 'datetime',
        'delivery_fee'        => 'decimal:2',
    ];

    public function order(): BelongsTo   { return $this->belongsTo(Order::class); }
    public function courier(): BelongsTo { return $this->belongsTo(Courier::class); }

    public function statusColor(): array
    {
        return match($this->status) {
            'delivered'  => ['bg'=>'#ECFDF5','text'=>'#059669'],
            'in_transit' => ['bg'=>'#FFF7ED','text'=>'#C2410C'],
            'picked_up'  => ['bg'=>'rgba(200,169,138,.15)','text'=>'#6B4C3B'],
            'accepted'   => ['bg'=>'#EEF2FF','text'=>'#4338CA'],
            'failed'     => ['bg'=>'#FEF2F2','text'=>'#DC2626'],
            default      => ['bg'=>'#FFFBEB','text'=>'#B45309'], // available
        };
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'available'  => 'Awaiting Courier',
            'accepted'   => 'Courier Assigned',
            'picked_up'  => 'Picked Up',
            'in_transit' => 'In Transit',
            'delivered'  => 'Delivered',
            'failed'     => 'Failed',
            default      => ucfirst($this->status),
        };
    }
}
