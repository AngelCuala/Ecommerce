<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = ['user_id', 'product_id', 'variation_id', 'quantity'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class, 'variation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Price of one unit right now: the variant's price (or the product's), with the product's sale. */
    public function getUnitPriceAttribute(): float
    {
        if (! $this->product) {
            return 0.0;
        }
        if ($this->variation && $this->variation->price > 0) {
            return $this->product->priceFor((float) $this->variation->price);
        }
        // The flash price only while enough flash units are left for this line.
        $flash = $this->flashSale();
        return $flash ? $this->product->effective_price : $this->product->regular_price;
    }

    /** The running flash sale this line gets its price from, or null. */
    public function flashSale(): ?FlashSale
    {
        $flash = $this->product?->runningFlashSale();
        return $flash && ! $this->variation_id && $this->quantity <= $flash->remaining() ? $flash : null;
    }

    /** Units in stock for this line (the variant's own stock when it is a variant). */
    public function availableStock(): int
    {
        if (! $this->product) {
            return 0;
        }
        return $this->variation ? (int) $this->variation->stock : $this->product->totalStock();
    }

    /** Why this line can't be checked out right now, or null when it can. */
    public function unavailableReason(): ?string
    {
        if (! $this->product || ! $this->product->isPublished()) {
            return 'No longer available';
        }
        if ($this->variation_id && ! $this->variation) {
            return 'This option is no longer available';
        }
        if (! $this->variation_id && $this->product->variations->isNotEmpty()) {
            return 'Please choose an option';
        }
        $stock = $this->availableStock();
        if ($stock <= 0) {
            return 'Sold out';
        }
        if ($this->quantity > $stock) {
            return 'Only ' . $stock . ' left';
        }
        return null;
    }

    public function subtotal(): float
    {
        return round($this->quantity * $this->unit_price, 2);
    }
}
