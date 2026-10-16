<?php

namespace App\Support;

use App\Http\Controllers\CategoryPageController;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Storefront search and filters: keyword, price range (on the price buyers actually pay),
 * minimum rating, category, seller location, and sort. Only published, in-stock products.
 */
class ProductSearch
{
    public const SORTS = [
        'relevance'    => 'Relevance',
        'newest'       => 'Latest',
        'best_selling' => 'Best selling',
        'price_low'    => 'Price: Low to High',
        'price_high'   => 'Price: High to Low',
    ];

    /** SQL for the price a buyer pays now (sale price, else discount %, else price). */
    public static function priceSql(): string
    {
        $running = '((products.sale_ends_at IS NULL OR products.sale_ends_at > ' . self::nowSql() . ') AND (products.sale_starts_at IS NULL OR products.sale_starts_at <= ' . self::nowSql() . '))';

        return "ROUND(CASE
            WHEN {$running} AND products.sale_price > 0 AND products.sale_price < products.price THEN products.sale_price
            WHEN {$running} AND products.discount_percent > 0 THEN products.price * (1 - products.discount_percent / 100)
            ELSE products.price END, 2)";
    }

    private static function nowSql(): string
    {
        return DB::getDriverName() === 'sqlite' ? "datetime('now')" : 'NOW()';
    }

    /** "%" and "_" typed by the buyer are searched as themselves. */
    public static function likeEscape(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }

    /**
     * Apply the request's search and filters.
     *
     * @return array{0: Builder, 1: array<string,string>} the query and messages for the buyer
     */
    public function apply(Request $request, ?string $term): array
    {
        $notes = [];
        $query = Product::query()->published()->where('stock', '>', 0)
            ->with('category')->withAvg('reviews', 'rating')
            ->withSum(['orderItems as sold' => fn ($q) => $q->whereHas('order', fn ($o) => $o->whereNotIn('status', Order::VOID_STATUSES))], 'quantity');

        // Every word must match somewhere (title, brand, category…), in any order.
        $term = trim((string) $term);
        foreach (preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $like = '%' . self::likeEscape($word) . '%';
            $query->where(fn ($q) => $q->where('title', 'like', $like)
                ->orWhere('brand', 'like', $like)
                ->orWhere('author', 'like', $like)
                ->orWhere('subcategory', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $like)));
        }

        // Price range — both ends included. A negative price, or a minimum above the maximum, is
        // rejected with a note and the price filter is not applied.
        $min = is_numeric($request->min_price) ? (float) $request->min_price : null;
        $max = is_numeric($request->max_price) ? (float) $request->max_price : null;
        if (($min !== null && $min < 0) || ($max !== null && $max < 0)) {
            $notes['price'] = 'Prices can\'t be negative, so the price filter was not applied.';
            $min = $max = null;
        } elseif ($min !== null && $max !== null && $min > $max) {
            $notes['price'] = 'The minimum price (₱' . number_format($min, 2) . ') is higher than the maximum (₱'
                . number_format($max, 2) . '), so the price filter was not applied.';
            $min = $max = null;
        }
        // The bounds are floats already, so they go in as numbers (some drivers bind floats as text).
        if ($min !== null) {
            $query->whereRaw(self::priceSql() . ' >= ' . number_format($min, 2, '.', ''));
        }
        if ($max !== null) {
            $query->whereRaw(self::priceSql() . ' <= ' . number_format($max, 2, '.', ''));
        }

        if ($request->filled('rating') && in_array((int) $request->rating, [1, 2, 3, 4, 5], true)) {
            $query->whereRaw('(SELECT AVG(rating) FROM reviews WHERE reviews.product_id = products.id) >= ?', [(int) $request->rating]);
        }

        if ($request->filled('category')) {
            $names = CategoryPageController::categoryNames((string) $request->category);
            $query->whereHas('category', fn ($c) => $c->whereIn('name', $names));
        }

        if ($request->filled('location')) {
            $query->whereHas('seller.sellerApplication', fn ($s) => $s->where('province', $request->location));
        }

        $sort = array_key_exists($request->sort, self::SORTS) ? $request->sort : ($term !== '' ? 'relevance' : 'newest');
        match ($sort) {
            'price_low'    => $query->orderByRaw(self::priceSql() . ' ASC'),
            'price_high'   => $query->orderByRaw(self::priceSql() . ' DESC'),
            'best_selling' => $query->orderByDesc('sold'),
            'newest'       => $query->latest('products.created_at'),
            default        => $term !== ''
                // Exact title first, then titles starting with it, then other matches.
                ? $query->orderByRaw('CASE WHEN LOWER(products.title) = ? THEN 0 WHEN LOWER(products.title) LIKE ? THEN 1 WHEN LOWER(products.title) LIKE ? THEN 2 ELSE 3 END',
                    [mb_strtolower($term), mb_strtolower(self::likeEscape($term)) . '%', '%' . mb_strtolower(self::likeEscape($term)) . '%'])
                : $query->latest('products.created_at'),
        };
        $query->orderBy('products.id');   // stable order, so no product repeats across pages

        return [$query, $notes];
    }

    /**
     * "Did you mean …": each word replaced by the closest word used in product titles
     * (up to 2 letters off), or null when nothing close is found.
     */
    public function suggestion(string $term): ?string
    {
        $words = preg_split('/\s+/', mb_strtolower(trim($term)), -1, PREG_SPLIT_NO_EMPTY);
        if (! $words) {
            return null;
        }

        $dictionary = cache()->remember('search-dictionary', now()->addMinutes(10), fn () => Product::published()
            ->pluck('title')
            ->flatMap(fn ($t) => preg_split('/[^\pL\pN]+/u', mb_strtolower($t), -1, PREG_SPLIT_NO_EMPTY))
            ->filter(fn ($w) => mb_strlen($w) >= 3)->unique()->values()->all());

        $changed = false;
        $fixed = array_map(function ($word) use ($dictionary, &$changed) {
            if (in_array($word, $dictionary, true)) {
                return $word;
            }
            $best = null;
            $bestDistance = 3;
            foreach ($dictionary as $candidate) {
                $d = levenshtein($word, $candidate);
                if ($d < $bestDistance) {
                    [$best, $bestDistance] = [$candidate, $d];
                }
            }
            if ($best) {
                $changed = true;
                return $best;
            }
            return $word;
        }, $words);

        return $changed ? implode(' ', $fixed) : null;
    }
}
