<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariation extends Model
{
    protected $fillable = [
        'product_id', 'name', 'price', 'stock', 'sku', 'image_path', 'sort_order',
    ];

    /** Most option types per product and options per type (assumption A7). */
    public const MAX_TYPES   = 2;
    public const MAX_OPTIONS = 20;

    protected $casts = [
        'price'      => 'float',
        'stock'      => 'integer',
        'sort_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function inStock(): bool
    {
        return $this->stock > 0;
    }
}
