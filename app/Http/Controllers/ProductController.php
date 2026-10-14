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

        $related = Product::with(['category', 'images'])->withAvg('reviews', 'rating')
            ->published()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get();

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
}
