<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StoreFollow;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\Request;

/** A seller's store page: their products by category, their vouchers, and Follow. */
class StoreController extends Controller
{
    public function show(Request $request, int $sellerId)
    {
        $seller = User::with('sellerApplication')->where('role', 'seller')->findOrFail($sellerId);

        $base = Product::published()->where('seller_id', $seller->id);

        // The seller's own store categories (two levels; hidden ones are left out). Products in
        // no visible category are "Uncategorized". Stores without any fall back to platform categories.
        $storeCategories = \App\Models\StoreCategory::where('seller_id', $seller->id)->whereNull('parent_id')->where('is_hidden', false)
            ->with(['children' => fn ($q) => $q->where('is_hidden', false)->withCount(['products' => fn ($p) => $p->published()])])
            ->withCount(['products' => fn ($p) => $p->published()])
            ->orderBy('sort_order')->orderBy('name')->get();
        $visibleIds = $storeCategories->pluck('id')->merge($storeCategories->flatMap(fn ($c) => $c->children->pluck('id')))->all();
        $uncategorized = $storeCategories->isNotEmpty()
            ? (clone $base)->whereDoesntHave('storeCategories', fn ($q) => $q->whereIn('store_categories.id', $visibleIds))->count() : 0;

        $categories = $storeCategories->isNotEmpty() ? collect() : (clone $base)->with('category')->get(['id', 'category_id', 'subcategory'])
            ->groupBy(fn ($p) => $p->category->name ?? 'Other')
            ->map(fn ($ps) => ['count' => $ps->count(), 'subs' => $ps->pluck('subcategory')->filter()->countBy()->sortKeys()->all()])
            ->sortKeys();

        $chosen = $request->filled('scat') && $request->scat !== 'none'
            ? \App\Models\StoreCategory::where('seller_id', $seller->id)->where('is_hidden', false)->find((int) $request->scat) : null;

        $products = (clone $base)
            ->with('category')->withAvg('reviews', 'rating')
            ->when($chosen, fn ($q) => $q->whereHas('storeCategories', fn ($c) => $c->whereIn('store_categories.id', $chosen->children()->where('is_hidden', false)->pluck('id')->push($chosen->id))))
            ->when($request->scat === 'none' && $storeCategories->isNotEmpty(), fn ($q) => $q->whereDoesntHave('storeCategories', fn ($c) => $c->whereIn('store_categories.id', $visibleIds)))
            ->when($request->filled('category'), fn ($q) => $q->whereHas('category', fn ($c) => $c->where('name', $request->category)))
            ->when($request->filled('sub'), fn ($q) => $q->where('subcategory', $request->sub))
            ->latest()->paginate(24)->withQueryString();

        $vouchers = Voucher::running()->where('scope', Voucher::SCOPE_SELLER)->where('seller_id', $seller->id)
            ->where('is_claimable', true)->orderBy('min_spend')->get();
        $claimed = auth()->check() ? auth()->user()->voucherClaims()->pluck('voucher_id')->all() : [];

        $followers = $seller->followers()->count();
        $following = auth()->check() && StoreFollow::where('user_id', auth()->id())->where('seller_id', $seller->id)->exists();

        return view('stores.show', compact('seller', 'categories', 'storeCategories', 'uncategorized', 'chosen', 'products', 'vouchers', 'claimed', 'followers', 'following'));
    }

    public function toggleFollow(int $sellerId)
    {
        $seller = User::where('role', 'seller')->findOrFail($sellerId);
        if ($seller->id === auth()->id()) {
            return back()->with('error', 'You can\'t follow your own store.');
        }

        $follow = StoreFollow::where('user_id', auth()->id())->where('seller_id', $seller->id)->first();
        if ($follow) {
            $follow->delete();
            return back()->with('success', 'You unfollowed ' . $seller->storeName() . '.');
        }

        StoreFollow::firstOrCreate(['user_id' => auth()->id(), 'seller_id' => $seller->id]);
        return back()->with('success', 'You are now following ' . $seller->storeName() . '.');
    }

    /** Stores the buyer follows. */
    public function following()
    {
        $follows = StoreFollow::where('user_id', auth()->id())
            ->with('seller.sellerApplication')->latest()->get()
            ->filter(fn ($f) => $f->seller);

        return view('stores.following', compact('follows'));
    }
}
