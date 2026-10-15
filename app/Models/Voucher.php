<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A discount code. "platform" vouchers (ALVY admins) work on any seller's products and are
 * paid for by ALVY; "seller" vouchers only work on that seller's products and are paid for
 * by the seller.
 */
class Voucher extends Model
{
    public const SCOPE_PLATFORM = 'platform';
    public const SCOPE_SELLER   = 'seller';
    /** ALVY-funded discount on the shipping fees ("FREESHIP"). */
    public const SCOPE_SHIPPING = 'shipping';

    protected $fillable = [
        'code', 'name', 'scope', 'seller_id', 'product_ids', 'discount_type', 'discount_value', 'max_discount',
        'min_spend', 'starts_at', 'ends_at', 'usage_limit', 'per_user_limit', 'is_active', 'is_claimable', 'created_by',
    ];

    protected $casts = [
        'discount_value' => 'float',
        'max_discount'   => 'float',
        'min_spend'      => 'float',
        'starts_at'      => 'datetime',
        'ends_at'        => 'datetime',
        'usage_limit'    => 'integer',
        'per_user_limit' => 'integer',
        'used_count'     => 'integer',
        'is_active'      => 'boolean',
        'is_claimable'   => 'boolean',
        'product_ids'    => 'array',
    ];

    /** A store voucher limited to some products works only on those; otherwise on all the store's items. */
    public function appliesToProduct(int $productId): bool
    {
        return empty($this->product_ids) || in_array($productId, array_map('intval', $this->product_ids), true);
    }

    public function seller(): BelongsTo        { return $this->belongsTo(User::class, 'seller_id'); }
    public function redemptions(): HasMany     { return $this->hasMany(VoucherRedemption::class); }

    public function isPlatform(): bool { return $this->scope === self::SCOPE_PLATFORM; }
    public function isShipping(): bool { return $this->scope === self::SCOPE_SHIPPING; }
    public function claims(): HasMany  { return $this->hasMany(VoucherClaim::class); }

    /**
     * End the voucher (A27): it stops working, and buyers who claimed but never used it
     * no longer see it in their wallet. Orders already placed keep their discount.
     */
    public function end(bool $now = false): void
    {
        $this->update(['is_active' => false] + ($now ? ['ends_at' => now()] : []));
        $usedBy = $this->redemptions()->pluck('user_id')->all();
        $this->claims()->whereNotIn('user_id', $usedBy ?: [0])->delete();
    }

    /** Codes are stored and matched in upper case. */
    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = strtoupper(trim((string) $value));
    }

    /** Active, started, not ended and not used up. */
    public function scopeRunning(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->where(fn ($q) => $q->whereNull('usage_limit')->orWhereColumn('used_count', '<', 'usage_limit'));
    }

    /** "Running", "Scheduled", "Ended", "Used up" or "Off", for lists. */
    public function stateLabel(): string
    {
        return match (true) {
            ! $this->is_active                                            => 'Off',
            $this->usage_limit !== null && $this->used_count >= $this->usage_limit => 'Used up',
            $this->ends_at && $this->ends_at->isPast()                    => 'Ended',
            $this->starts_at && $this->starts_at->isFuture()              => 'Scheduled',
            default                                                       => 'Running',
        };
    }

    /** "₱150 off" or "10% off (up to ₱200)". */
    public function discountLabel(): string
    {
        if ($this->isShipping()) {
            return 'Shipping ₱' . number_format($this->discount_value, 0) . ' off';
        }
        if ($this->discount_type === 'percent') {
            return rtrim(rtrim(number_format($this->discount_value, 2), '0'), '.') . '% off'
                . ($this->max_discount ? ' (up to ₱' . number_format($this->max_discount, 0) . ')' : '');
        }

        return '₱' . number_format($this->discount_value, 0) . ' off';
    }

    /** Discount this voucher gives on an amount, never more than the amount itself. */
    public function discountFor(float $amount): float
    {
        $discount = $this->discount_type === 'percent'
            ? $amount * $this->discount_value / 100
            : $this->discount_value;

        if ($this->discount_type === 'percent' && $this->max_discount) {
            $discount = min($discount, $this->max_discount);
        }

        return round(max(0, min($discount, $amount)), 2);
    }
}
