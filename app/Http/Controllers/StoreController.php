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

        // Store categories, two levels: category → subcategories.
        $categories = (clone $base)->with('category')->get(['id', 'category_id', 'subcategory'])
            ->groupBy(fn ($p) => $p->category->name ?? 'Other')
            ->map(fn ($ps) => ['count' => $ps->count(), 'subs' => $ps->pluck('subcategory')->filter()->countBy()->sortKeys()->all()])
            ->sortKeys();

        $products = (clone $base)
            ->with('category')->withAvg('reviews', 'rating')
            ->when($request->filled('category'), fn ($q) => $q->whereHas('category', fn ($c) => $c->where('name', $request->category)))
            ->when($request->filled('sub'), fn ($q) => $q->where('subcategory', $request->sub))
            ->latest()->paginate(24)->withQueryString();

        $vouchers = Voucher::running()->where('scope', Voucher::SCOPE_SELLER)->where('seller_id', $seller->id)
            ->where('is_claimable', true)->orderBy('min_spend')->get();
        $claimed = auth()->check() ? auth()->user()->voucherClaims()->pluck('voucher_id')->all() : [];

        $followers = $seller->followers()->count();
        $following = auth()->check() && StoreFollow::where('user_id', auth()->id())->where('seller_id', $seller->id)->exists();

        return view('stores.show', compact('seller', 'categories', 'products', 'vouchers', 'claimed', 'followers', 'following'));
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
