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
        if ($request->filled('scat') && $request->scat !== 'none' && ! $chosen) {
            return redirect()->route('stores.show', $seller->id)->with('error', 'That category is not available.');
        }

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

        $productCount = (clone $base)->count();
        $followers = $seller->followers()->count();
        $following = auth()->check() && StoreFollow::where('user_id', auth()->id())->where('seller_id', $seller->id)->exists();

        return view('stores.show', compact('seller', 'categories', 'storeCategories', 'uncategorized', 'chosen', 'products', 'productCount', 'vouchers', 'claimed', 'followers', 'following'));
    }

    public function toggleFollow(int $sellerId)
    {
        $seller = User::where('role', 'seller')->findOrFail($sellerId);
        if ((int) $seller->id === (int) auth()->id()) {
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
            ->filter(fn ($f) => $f->seller)->values();
        $ids = $follows->pluck('seller_id')->all();

        // Each store's numbers, a few queries for the whole page (not per store).
        $products  = Product::published()->whereIn('seller_id', $ids)->selectRaw('seller_id, COUNT(*) as n')->groupBy('seller_id')->pluck('n', 'seller_id');
        $followers = StoreFollow::whereIn('seller_id', $ids)->selectRaw('seller_id, COUNT(*) as n')->groupBy('seller_id')->pluck('n', 'seller_id');
        $ratings   = \App\Models\Review::join('products', 'products.id', '=', 'reviews.product_id')->whereIn('products.seller_id', $ids)
            ->selectRaw('products.seller_id, AVG(reviews.rating) as avg, COUNT(*) as n')->groupBy('products.seller_id')->get()->keyBy('seller_id');
        $vouchers  = Voucher::running()->where('scope', Voucher::SCOPE_SELLER)->where('is_claimable', true)->whereIn('seller_id', $ids)
            ->selectRaw('seller_id, COUNT(*) as n')->groupBy('seller_id')->pluck('n', 'seller_id');
        // The newest few products of each store, for a preview row.
        $latest = Product::published()->whereIn('seller_id', $ids)->latest()->limit(count($ids) * 40)->get()
            ->groupBy('seller_id')->map(fn ($ps) => $ps->take(4));

        $stores = $follows->map(fn ($f) => (object) [
            'follow'    => $f,
            'seller'    => $f->seller,
            'app'       => $f->seller->sellerApplication,
            'products'  => (int) ($products[$f->seller_id] ?? 0),
            'followers' => (int) ($followers[$f->seller_id] ?? 0),
            'rating'    => isset($ratings[$f->seller_id]) ? round((float) $ratings[$f->seller_id]->avg, 1) : null,
            'reviews'   => (int) ($ratings[$f->seller_id]->n ?? 0),
            'vouchers'  => (int) ($vouchers[$f->seller_id] ?? 0),
            'latest'    => $latest[$f->seller_id] ?? collect(),
        ]);

        return view('stores.following', compact('stores'));
    }
}
