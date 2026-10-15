<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\UserNotification;

/**
 * Tells the seller when a product (or one of its options) drops to the low-stock threshold.
 * The alert is sent once, when stock crosses the threshold, not on every sale below it.
 */
class LowStockAlert
{
    public const THRESHOLD = 5;

    public static function check(?Product $product, int $before, int $after, ?ProductVariation $variation = null): void
    {
        if (! $product || ! $product->seller_id || $before <= self::THRESHOLD || $after > self::THRESHOLD) {
            return;
        }

        $name = '"' . $product->title . '"' . ($variation ? ' (' . $variation->name . ')' : '');
        UserNotification::create([
            'user_id' => $product->seller_id,
            'title'   => $after <= 0 ? 'Sold out' : 'Low stock',
            'body'    => $after <= 0
                ? $name . ' is sold out. Restock it so buyers can order again.'
                : $name . ' has only ' . $after . ' left (alert at ' . self::THRESHOLD . ' or fewer).',
            'type'    => 'warning',
            'link'    => route('seller.books.edit', $product->id),
        ]);
    }
}
