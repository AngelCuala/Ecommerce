<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Book extends Model
{
    protected $fillable = [
        'category_id', 'seller_id',
        'title', 'author', 'isbn',
        'publisher', 'publication_year', 'edition', 'language', 'pages', 'format',
        'price', 'discount_percent', 'voucher_code',
        'stock', 'sku', 'availability',
        'description', 'image',
        'is_archived', 'archived_at',
    ];

    protected $casts = [
        'price'            => 'float',
        'discount_percent' => 'float',
        'stock'            => 'integer',
        'pages'            => 'integer',
        'is_archived'      => 'boolean',
        'archived_at'      => 'datetime',
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

    public function images(): HasMany
    {
        return $this->hasMany(BookImage::class)->orderBy('sort_order');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function violations(): HasMany
    {
        return $this->hasMany(\App\Models\SellerViolation::class);
    }

    // ── Accessors ─────────────────────────────────────────────
    public function getSlugAttribute(): string
    {
        return Str::slug($this->title) . '-' . $this->id;
    }

    /** Primary display image — first gallery image or fallback single image */
    public function getPrimaryImageAttribute(): ?string
    {
        $first = $this->images->first();
        if ($first) return asset('storage/' . $first->path);
        if ($this->image) return asset('storage/' . $this->image);
        return null;
    }

    public function inStock(): bool
    {
        return $this->stock > 0 && $this->availability !== 'out_of_stock';
    }

    /** Effective price after discount */
    public function getEffectivePriceAttribute(): float
    {
        if ($this->discount_percent > 0) {
            return round($this->price * (1 - $this->discount_percent / 100), 2);
        }
        return $this->price;
    }

    public function hasDiscount(): bool
    {
        return $this->discount_percent > 0;
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
}
