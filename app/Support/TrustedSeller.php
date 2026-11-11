<?php

namespace App\Support;

use App\Models\ContactStrike;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Services\ContactStrikes;

/**
 * "Trusted Seller" badge: a reward for selling well on ALVY (shown on the store and its products).
 *   - at least MIN_ORDERS completed orders,
 *   - rated MIN_RATING or more (once the store has MIN_REVIEWS reviews),
 *   - no off-platform contact strike in the last 90 days.
 */
class TrustedSeller
{
    public const MIN_ORDERS = 10;
    public const MIN_RATING = 4.5;
    public const MIN_REVIEWS = 3;

    private static array $cache = [];

    public static function is(?User $seller): bool
    {
        return $seller && $seller->isSeller() && self::progress($seller)['trusted'];
    }

    /** ['trusted', 'orders', 'rating', 'reviews', 'strikes', 'checks' => [label => passed]] */
    public static function progress(User $seller): array
    {
        if (isset(self::$cache[$seller->id])) {
            return self::$cache[$seller->id];
        }
        $orders = Order::where('seller_id', $seller->id)->where('status', Order::COMPLETED)->count();
        $reviews = Review::whereHas('product', fn ($q) => $q->where('seller_id', $seller->id));
        $count = (clone $reviews)->count();
        $rating = $count ? round((float) $reviews->avg('rating'), 2) : null;
        $strikes = app(ContactStrikes::class)->count($seller);

        $checks = [
            'At least ' . self::MIN_ORDERS . ' completed orders (' . $orders . ' so far)' => $orders >= self::MIN_ORDERS,
            'Rating ' . self::MIN_RATING . '★ or higher' . ($count >= self::MIN_REVIEWS ? ' (' . number_format($rating, 1) . '★ now)' : ' (counts after ' . self::MIN_REVIEWS . ' reviews)')
                => $count < self::MIN_REVIEWS || $rating >= self::MIN_RATING,
            'No contact-sharing strikes in the last ' . ContactStrikes::WINDOW_DAYS . ' days' => $strikes === 0,
        ];

        return self::$cache[$seller->id] = ['trusted' => ! in_array(false, $checks, true), 'orders' => $orders, 'rating' => $rating,
            'reviews' => $count, 'strikes' => $strikes, 'checks' => $checks];
    }

    /** Forget cached results (tests, or after a change in the same request). */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
