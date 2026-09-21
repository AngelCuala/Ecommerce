<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariation extends Model
{
    protected $fillable = [
        'book_id', 'name', 'price', 'stock', 'sku', 'sort_order',
    ];

    protected $casts = [
        'price'      => 'float',
        'stock'      => 'integer',
        'sort_order' => 'integer',
    ];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function inStock(): bool
    {
        return $this->stock > 0;
    }
}
