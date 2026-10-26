<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\UserNotification;

/**
 * Tells the seller (only the product's own seller) when a product or one of its options drops
 * to its low-stock level, and again when it sells out.
 *
 * Each alert is sent once, when stock crosses the level, not on every later sale below it. After a
 * restock above the level the next drop alerts again. The level is the product's own
 * low_stock_threshold, else config('marketplace.low_stock_threshold').
 * Call it with the stock before and after the change, inside the checkout transaction that
 * locked the row, so two buyers can't both trigger the same alert.
 */
class LowStockAlert
{
    /** @deprecated Store-wide default; use Product::lowStockThreshold(). */
    public const THRESHOLD = 5;

    public static function check(?Product $product, int $before, int $after, ?ProductVariation $variation = null): void
    {
        if (! $product || ! $product->seller_id || $after >= $before) {
            return;
        }
        $level = $product->lowStockThreshold();

        $soldOut = $before > 0 && $after <= 0;
        $low     = $before > $level && $after <= $level && $after > 0;
        if (! $soldOut && ! $low) {
            return;
        }

        $name = '"' . $product->title . '"' . ($variation ? ' (' . $variation->name . ')' : '');
        UserNotification::create([
            'user_id' => $product->seller_id,
            'title'   => $soldOut ? 'Sold out' : 'Low stock',
            'body'    => $soldOut
                ? $name . ' is sold out. Restock it so buyers can order again.'
                : $name . ' has only ' . $after . ' left (alert at ' . $level . ' or fewer).',
            'type'    => 'warning',
            'link'    => route('seller.books.edit', $product->id),
        ]);
    }
}
