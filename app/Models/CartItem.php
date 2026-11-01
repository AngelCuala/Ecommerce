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
        // Part flash, part regular: the average (the line total is exact, see subtotal()).
        $flashUnits = $this->flashUnits();
        if ($flashUnits === 0) {
            return $this->product->regular_price;
        }
        if ($flashUnits >= $this->quantity) {
            return $this->product->effective_price;
        }
        return round($this->subtotal() / max(1, $this->quantity), 2);
    }

    /** The running flash sale this line gets (some of) its units from, or null. */
    public function flashSale(): ?FlashSale
    {
        return $this->flashUnits() > 0 ? $this->product->runningFlashSale() : null;
    }

    /**
     * Units of this line charged at the flash price: as many as the flash sale has left.
     * The rest are charged at the normal (or store sale) price (assumption A5).
     */
    public function flashUnits(): int
    {
        $flash = $this->product?->runningFlashSale();
        if (! $flash || $this->variation_id || $this->product->effective_price >= $this->product->regular_price) {
            return 0;
        }
        return max(0, min((int) $this->quantity, $flash->remaining()));
    }

    /** [[qty, unit price, flash?], ...]: one part, or a flash part plus a regular part. */
    public function priceParts(): array
    {
        $flashUnits = $this->flashUnits();
        if ($flashUnits > 0 && $flashUnits < $this->quantity) {
            return [
                [$flashUnits, $this->product->effective_price, true],
                [$this->quantity - $flashUnits, $this->product->regular_price, false],
            ];
        }
        return [[(int) $this->quantity, $this->unit_price, $flashUnits > 0]];
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
        if ($this->product->sellerOnVacation()) {
            return 'On vacation — the store is not taking orders right now';
        }
        // The seller removed this option (its row is gone, so the line lost its option).
        if (($this->variation_id && ! $this->variation) || (! $this->variation_id && $this->product->variations->isNotEmpty())) {
            return 'This option is no longer available';
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
        $flashUnits = $this->flashUnits();
        if ($flashUnits > 0 && $flashUnits < $this->quantity) {
            return round($flashUnits * $this->product->effective_price + ($this->quantity - $flashUnits) * $this->product->regular_price, 2);
        }
        return round($this->quantity * $this->unit_price, 2);
    }
}
