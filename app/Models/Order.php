<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'full_name', 'phone', 'email',
        'address_line', 'city', 'province', 'zip_code',
        'municipality_code', 'province_code', // PSGC codes of the delivery address (used for routing)
        'shipping_address',
        'subtotal', 'shipping_fee', 'total_price',
        'payment_method', 'payment_status',
        'status', 'cancellation_reason',
    ];

    /** Buyer can still cancel only when Pending or Processing */
    public function isCancellableByBuyer(): bool
    {
        return in_array($this->status, ['Pending', 'Processing']);
    }

    protected $casts = [
        'subtotal'     => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'total_price'  => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    /** Sorting-center parcels for this order (one per seller). */
    public function parcels(): HasMany
    {
        return $this->hasMany(Parcel::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** Human-readable full shipping address */
    public function fullAddress(): string
    {
        // address_line often already contains the barangay/city/province, so
        // drop any part that repeats an earlier one (case-insensitive).
        $parts = [];
        foreach ([$this->address_line, $this->city, $this->province, $this->zip_code] as $chunk) {
            foreach (array_map('trim', explode(',', (string) $chunk)) as $part) {
                if ($part !== '' && ! in_array(mb_strtolower($part), array_map('mb_strtolower', $parts), true)) {
                    $parts[] = $part;
                }
            }
        }

        return implode(', ', $parts);
    }

    public function statusColor(): array
    {
        return match(strtolower($this->status)) {
            'delivered'  => ['bg'=>'#ECFDF5','text'=>'#059669'],
            'shipped'    => ['bg'=>'#FFF7ED','text'=>'#C2410C'],
            'processing' => ['bg'=>'rgba(200,169,138,.15)','text'=>'#6B4C3B'],
            'cancelled'  => ['bg'=>'#FEF2F2','text'=>'#DC2626'],
            default      => ['bg'=>'#FFFBEB','text'=>'#B45309'],   // Pending
        };
    }
}
