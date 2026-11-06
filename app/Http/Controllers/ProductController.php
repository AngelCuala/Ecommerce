<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Voucher;
use App\Models\WishlistItem;

class ProductController extends Controller
{
    public function show(string $slug)
    {
        // Slug format: {title-slug}-{id}
        $id = (int) last(explode('-', $slug));

        $product = Product::withTrashed()->with(['category', 'seller.sellerApplication', 'images', 'variations'])->find($id);

        // Deleted, drafts, archived or not (yet) approved: old links show "unavailable" instead of an error.
        if (! $product || $product->trashed() || ! $product->isPublished()) {
            return response()->view('products.unavailable', ['product' => $product], 404);
        }

        $related = $this->related($product);

        $reviews = $product->reviews()->with('user')->latest()->paginate(10, ['*'], 'reviews_page');
        $ratingBreakdown = $product->reviews()->selectRaw('rating, COUNT(*) as n')->groupBy('rating')->pluck('n', 'rating');

        $sold = (int) $product->orderItems()->whereHas('order', fn ($q) => $q->whereNotIn('status', Order::VOID_STATUSES))->sum('quantity');

        // Store vouchers buyers can claim from this product page.
        $vouchers = $product->seller_id
            ? Voucher::running()->where('scope', Voucher::SCOPE_SELLER)->where('seller_id', $product->seller_id)
                ->where('is_claimable', true)->orderBy('min_spend')->get()
            : collect();

        $user      = auth()->user();
        $claimed   = $user ? $user->voucherClaims()->pluck('voucher_id')->all() : [];
        $wished    = $user && WishlistItem::where('user_id', $user->id)->where('product_id', $product->id)->exists();
        $allowance = $product->remainingAllowanceFor($user);

        $flash    = $product->variations->isEmpty() ? $product->runningFlashSale() : null;
        $upcoming = $product->variations->isEmpty() ? $product->upcomingFlashSale() : null;
        $reminded = $upcoming && $user && $upcoming->reminders()->where('user_id', $user->id)->exists();

        return view('products.show', compact('product', 'related', 'reviews', 'ratingBreakdown', 'sold',
            'vouchers', 'claimed', 'wished', 'allowance', 'flash', 'upcoming', 'reminded'));
    }

    /**
     * "You may also like" (website and app): items like this one first — same category and sharing words of its
     * name (a word must start a word of the other title, so "glass" finds "glasses" but not "fiberglass") — then
     * the rest of its category, then very close names from other categories; the same shop breaks ties.
     */
    private function related(Product $product, int $limit = 8)
    {
        $stop = ['the', 'and', 'for', 'with', 'from', 'new', 'set', 'pcs', 'pc', 'of', 'ang', 'mga', 'para', 'sa', 'na', 'ng'];
        $words = collect(preg_split('/[^\pL\pN]+/u', mb_strtolower($product->title)))
            ->filter(fn ($w) => mb_strlen($w) >= 3 && ! in_array($w, $stop, true) && ! ctype_digit($w))
            ->unique()->take(6)->values();

        $score = ['CASE WHEN category_id = ? THEN 4 ELSE 0 END', 'CASE WHEN seller_id = ? THEN 1 ELSE 0 END'];
        $bindings = [$product->category_id, $product->seller_id];
        foreach ($words as $w) {
            $score[] = '(CASE WHEN LOWER(title) LIKE ? OR LOWER(title) LIKE ? THEN 3 ELSE 0 END)';
            array_push($bindings, ...self::wordStart($w));
        }

        return Product::select('products.*')
            ->selectRaw('(' . implode(' + ', $score) . ') AS related_score', $bindings)
            ->with(['category', 'images'])->withAvg('reviews', 'rating')
            ->published()
            ->where('id', '!=', $product->id)
            ->where(function ($q) use ($product, $words) {
                $q->where('category_id', $product->category_id);
                foreach ($words as $w) {
                    $q->orWhereRaw('(LOWER(title) LIKE ? OR LOWER(title) LIKE ?)', self::wordStart($w));
                }
            })
            ->orderByDesc('related_score')
            ->latest('id')
            ->take($limit)
            ->get();
    }

    /** LIKE patterns for a title that has a word starting with $w: at the very start, or after a space. */
    private static function wordStart(string $w): array
    {
        $w = addcslashes($w, '%_\\');

        return [$w . '%', '% ' . $w . '%'];
    }
}
