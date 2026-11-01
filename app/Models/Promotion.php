<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A seller-funded promotion applied at checkout:
 *  - bundle: buy at least min_qty units from product_ids (any mix of options) → discount_percent off them
 *  - bxgy:   for every min_qty units of the product in product_ids → free_qty of free_product_id free
 */
class Promotion extends Model
{
    public const BUNDLE = 'bundle';
    public const BXGY   = 'bxgy';

    protected $fillable = [
        'seller_id', 'name', 'type', 'product_ids', 'min_qty', 'discount_percent',
        'free_product_id', 'free_qty', 'starts_at', 'ends_at', 'is_active',
    ];

    protected $casts = [
        'product_ids'      => 'array',
        'min_qty'          => 'integer',
        'free_qty'         => 'integer',
        'discount_percent' => 'float',
        'starts_at'        => 'datetime',
        'ends_at'          => 'datetime',
        'is_active'        => 'boolean',
    ];

    public function seller(): BelongsTo      { return $this->belongsTo(User::class, 'seller_id'); }
    public function freeProduct(): BelongsTo { return $this->belongsTo(Product::class, 'free_product_id')->withTrashed(); }

    public function scopeRunning(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function label(): string
    {
        return $this->type === self::BUNDLE
            ? 'Buy ' . $this->min_qty . ', get ' . rtrim(rtrim(number_format($this->discount_percent, 2), '0'), '.') . '% off'
            : 'Buy ' . $this->min_qty . ', get ' . $this->free_qty . ' free';
    }
}
