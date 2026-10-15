<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A seller's own category for their store page (two levels at most), separate from the
 * platform categories buyers browse across ALVY.
 */
class StoreCategory extends Model
{
    public const MAX_NAME = 50;

    protected $fillable = ['seller_id', 'parent_id', 'name', 'sort_order', 'is_hidden'];

    protected $casts = ['is_hidden' => 'boolean', 'sort_order' => 'integer'];

    public function seller(): BelongsTo   { return $this->belongsTo(User::class, 'seller_id'); }
    public function parent(): BelongsTo   { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany   { return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name'); }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_store_category');
    }
}
