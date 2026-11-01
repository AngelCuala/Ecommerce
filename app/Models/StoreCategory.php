<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A category on a seller's store page. The seller picks it from the ALVY categories (category_id), so a
 * shop selling motor parts and makeup has "Automotive & Motorcycle" and "Health & Beauty"; each product is
 * tagged with the one for its ALVY category. Older free-named categories (no category_id) still work.
 */
class StoreCategory extends Model
{
    public const MAX_NAME = 50;

    protected $fillable = ['seller_id', 'parent_id', 'category_id', 'name', 'sort_order', 'is_hidden'];

    protected $casts = ['is_hidden' => 'boolean', 'sort_order' => 'integer'];

    public function seller(): BelongsTo   { return $this->belongsTo(User::class, 'seller_id'); }
    public function parent(): BelongsTo   { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany   { return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name'); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }

    /** The seller's store category for an ALVY category, added to the store if it isn't there yet. */
    public static function forPlatformCategory(int $sellerId, int $categoryId): ?self
    {
        $category = Category::find($categoryId);
        if (! $category) {
            return null;
        }

        return self::firstOrCreate(['seller_id' => $sellerId, 'category_id' => $category->id], [
            'name'       => $category->name,
            'parent_id'  => null,
            'sort_order' => (int) self::where('seller_id', $sellerId)->whereNull('parent_id')->max('sort_order') + 1,
        ]);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_store_category');
    }
}
