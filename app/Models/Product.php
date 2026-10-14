<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    // Deleted listings are kept (soft-deleted) so order history still shows them.
    use SoftDeletes;

    /** Running flash sales for the current request: ['key' => request id, 'sales' => Collection]. */
    private static array $runningFlashSales = [];

    /** Forget the cached flash sales (after one is created or sells units). */
    public static function forgetFlashSales(): void
    {
        self::$runningFlashSales = [];
    }

    /** Most photos a single listing can have. */
    public const MAX_IMAGES = 15;

    protected $fillable = [
        'category_id', 'subcategory', 'seller_id',
        'product_code', 'brand',
        'title', 'author', 'isbn',
        'publisher', 'publication_year', 'edition', 'language', 'pages', 'format',
        'price', 'sale_price', 'discount_percent', 'voucher_code',
        'stock', 'sku', 'availability', 'status',
        'description', 'specs', 'image', 'video_path',
        'weight_kg', 'length_cm', 'width_cm', 'height_cm', 'address_id',
        'is_archived', 'archived_at',
        'approval_status', 'approval_note', 'approval_reviewed_at', 'approval_reviewed_by',
        'variation_labels', 'purchase_limit', 'sale_ends_at',
    ];

    protected $casts = [
        'price'            => 'float',
        'sale_price'       => 'float',
        'discount_percent' => 'float',
        'stock'            => 'integer',
        'pages'            => 'integer',
        'specs'            => 'array',
        'weight_kg'        => 'float',
        'length_cm'        => 'float',
        'width_cm'         => 'float',
        'height_cm'        => 'float',
        'is_archived'      => 'boolean',
        'archived_at'      => 'datetime',
        'approval_reviewed_at' => 'datetime',
        'sale_ends_at'     => 'datetime',
        'purchase_limit'   => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function approvalReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approval_reviewed_by');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class)->orderBy('sort_order');
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(UserAddress::class, 'address_id');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'product_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_id');
    }

    public function violations(): HasMany
    {
        return $this->hasMany(\App\Models\SellerViolation::class);
    }

    /** Gallery photos (up to 9), in display order. */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')->orderBy('sort_order')->orderBy('id');
    }

    // ── Accessors ─────────────────────────────────────────────
    public function getSlugAttribute(): string
    {
        return Str::slug($this->title) . '-' . $this->id;
    }

    /** Primary display image — fallback single image */
    public function getPrimaryImageAttribute(): ?string
    {
        if ($this->image) return asset('storage/' . $this->image);
        return null;
    }

    public function inStock(): bool
    {
        return $this->totalStock() > 0
            && $this->availability !== 'out_of_stock'
            && $this->status !== 'out_of_stock';
    }

    /** Total stock across variations, or the base stock when there are none. */
    public function totalStock(): int
    {
        if ($this->relationLoaded('variations') ? $this->variations->isNotEmpty() : $this->variations()->exists()) {
            return (int) $this->variations()->sum('stock');
        }
        return (int) $this->stock;
    }

    /** A sale with an end date stops on that date. */
    public function saleIsRunning(): bool
    {
        return ! $this->sale_ends_at || $this->sale_ends_at->isFuture();
    }

    /** Effective price: a running flash sale, else the sale price, else discount %, else the base price. */
    public function getEffectivePriceAttribute(): float
    {
        $normal = $this->priceFor((float) $this->price, true);
        $flash  = $this->runningFlashSale();

        return $flash ? min($normal, round($flash->flash_price, 2)) : $normal;
    }

    /** Price without the flash sale (after its flash quantity is sold out). */
    public function getRegularPriceAttribute(): float
    {
        return $this->priceFor((float) $this->price, true);
    }

    public function flashSales(): HasMany
    {
        return $this->hasMany(FlashSale::class);
    }

    /** The flash sale running now with units left (products without options only), or null. */
    public function runningFlashSale(): ?FlashSale
    {
        if (! $this->exists) {
            return null;
        }
        // Few flash sales run at once: load them all once per request, not one query per product card.
        $key = spl_object_id(request());
        if ((self::$runningFlashSales['key'] ?? null) !== $key) {
            self::$runningFlashSales = ['key' => $key, 'sales' => FlashSale::running()->orderBy('flash_price')->get()->groupBy('product_id')];
        }
        $sale = self::$runningFlashSales['sales']->get($this->id)?->first();

        // Re-check (the cached list may be from earlier in a long request, e.g. after a sale in it).
        return $sale && $sale->isRunning() ? $sale : null;
    }

    /** The next flash sale that hasn't started yet, or null. */
    public function upcomingFlashSale(): ?FlashSale
    {
        return $this->flashSales()->upcoming()->orderBy('starts_at')->first();
    }

    /**
     * What a buyer pays for a base price (the product's own, or a variant's) once the
     * product's sale is applied. A fixed sale price only applies to the product's own price.
     */
    public function priceFor(float $base, bool $ownPrice = false): float
    {
        if ($this->saleIsRunning()) {
            if ($ownPrice && $this->sale_price !== null && $this->sale_price > 0 && $this->sale_price < $base) {
                return round((float) $this->sale_price, 2);
            }
            if ($this->discount_percent > 0) {
                return round($base * (1 - $this->discount_percent / 100), 2);
            }
        }
        return round($base, 2);
    }

    public function hasDiscount(): bool
    {
        if ($this->runningFlashSale() && $this->runningFlashSale()->flash_price < $this->price) {
            return true;
        }
        return $this->saleIsRunning()
            && (($this->sale_price !== null && $this->sale_price > 0 && $this->sale_price < $this->price)
                || $this->discount_percent > 0);
    }

    /** Units this buyer may still buy under the per-buyer limit (null = no limit). */
    public function remainingAllowanceFor(?User $user): ?int
    {
        if (! $this->purchase_limit) {
            return null;
        }
        $bought = $user ? (int) OrderItem::where('product_id', $this->id)
            ->whereHas('order', fn ($q) => $q->where('user_id', $user->id)->whereNotIn('status', Order::VOID_STATUSES))
            ->sum('quantity') : 0;

        return max(0, $this->purchase_limit - $bought);
    }

    /** Variant groups for the product page: "Natural / M" names → [['Natural','Black'], ['S','M','L']]. */
    public function variationGroups(): array
    {
        $groups = [];
        foreach ($this->variations as $v) {
            foreach (array_map('trim', explode('/', $v->name)) as $i => $part) {
                if ($part !== '' && ! in_array($part, $groups[$i] ?? [], true)) {
                    $groups[$i][] = $part;
                }
            }
        }
        return $groups;
    }

    /** Names of the variant groups ("Color", "Size"), else "Variation" / "Option 1", "Option 2". */
    public function variationGroupLabels(): array
    {
        $groups = $this->variationGroups();
        $labels = array_values(array_filter(array_map('trim', explode(',', (string) $this->variation_labels))));

        return array_map(fn ($i) => $labels[$i] ?? (count($groups) > 1 ? 'Option ' . ($i + 1) : 'Variation'), array_keys($groups));
    }

    // ── Reviews ───────────────────────────────────────────────
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function getRatingAvgAttribute(): ?float
    {
        $avg = array_key_exists('reviews_avg_rating', $this->attributes)
            ? $this->attributes['reviews_avg_rating']
            : $this->reviews()->avg('rating');

        return $avg !== null ? round((float) $avg, 1) : null;
    }

    public function getRatingCountAttribute(): int
    {
        return (int) (array_key_exists('reviews_count', $this->attributes)
            ? $this->attributes['reviews_count']
            : $this->reviews()->count());
    }

    /** Average rating for product cards (needs withAvg('reviews', 'rating') to avoid a query per card). */
    public function getRatingAttribute(): ?float
    {
        return array_key_exists('reviews_avg_rating', $this->attributes) && $this->attributes['reviews_avg_rating'] !== null
            ? round((float) $this->attributes['reviews_avg_rating'], 1) : null;
    }

    public function isLowStock(int $threshold = 5): bool
    {
        return $this->stock > 0 && $this->stock <= $threshold;
    }

    // ── Scopes ────────────────────────────────────────────────
    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    public function scopeLowStock($query, int $threshold = 5)
    {
        return $query->where('stock', '>', 0)->where('stock', '<=', $threshold);
    }

    /** Publicly visible listings (published, approved by an admin, not archived). */
    public function scopePublished($query)
    {
        return $query->where('is_archived', false)
            ->where('status', 'active')
            ->where('approval_status', 'approved');
    }

    /** New seller products waiting for an admin (drafts are not submitted yet). */
    public function scopeAwaitingApproval($query)
    {
        return $query->where('approval_status', 'pending')->where('status', '!=', 'draft');
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    // ── Admin approval (new seller products only) ───────────
    public function isApproved(): bool        { return $this->approval_status === 'approved'; }
    public function isPendingApproval(): bool { return $this->approval_status === 'pending'; }
    public function isRejected(): bool        { return $this->approval_status === 'rejected'; }

    /** Shoppers can see and buy it. */
    public function isPublished(): bool
    {
        return ! $this->is_archived && $this->status === 'active' && $this->isApproved();
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }
}
