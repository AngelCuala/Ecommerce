<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A seller's product at a flash price for a time slot, for a limited quantity.
 * While it runs and units are left, the flash price wins over the store sale price;
 * once the flash quantity is sold, the normal (sale) price applies again (assumption A5).
 */
class FlashSale extends Model
{
    /** Buyers who asked for a reminder get it this many minutes before the start. */
    public const REMIND_MINUTES_BEFORE = 15;

    protected $fillable = ['product_id', 'seller_id', 'flash_price', 'quantity', 'sold', 'starts_at', 'ends_at', 'reminders_sent_at'];

    protected $casts = [
        'flash_price'       => 'float',
        'quantity'          => 'integer',
        'sold'              => 'integer',
        'starts_at'         => 'datetime',
        'ends_at'           => 'datetime',
        'reminders_sent_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Product::forgetFlashSales());
        static::updated(fn () => Product::forgetFlashSales());   // increment() fires "updated", not "saved"
        static::deleted(fn () => Product::forgetFlashSales());
    }

    public function product(): BelongsTo   { return $this->belongsTo(Product::class)->withTrashed(); }
    public function seller(): BelongsTo    { return $this->belongsTo(User::class, 'seller_id'); }
    public function reminders(): HasMany   { return $this->hasMany(FlashSaleReminder::class); }

    public function scopeRunning(Builder $q): Builder
    {
        return $q->where('starts_at', '<=', now())->where('ends_at', '>', now())->whereColumn('sold', '<', 'quantity');
    }

    public function scopeUpcoming(Builder $q): Builder
    {
        return $q->where('starts_at', '>', now());
    }

    public function remaining(): int
    {
        return max(0, $this->quantity - $this->sold);
    }

    public function isRunning(): bool
    {
        return $this->starts_at->isPast() && $this->ends_at->isFuture() && $this->remaining() > 0;
    }

    public function stateLabel(): string
    {
        return match (true) {
            $this->starts_at->isFuture() => 'Upcoming',
            $this->ends_at->isPast()     => 'Ended',
            $this->remaining() === 0     => 'Sold out',
            default                      => 'Running',
        };
    }
}
