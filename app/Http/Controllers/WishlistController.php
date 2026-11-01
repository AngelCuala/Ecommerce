<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\WishlistItem;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index()
    {
        $items = WishlistItem::where('user_id', auth()->id())
            ->with(['product' => fn ($q) => $q->with('category')->withAvg('reviews', 'rating')])
            ->latest()->get();

        return view('wishlist.index', compact('items'));
    }

    /**
     * Heart button. "state" = on / off makes the request idempotent, so clicking twice
     * quickly can't add the item twice (the table also has a unique row per buyer + product).
     */
    public function toggle(Request $request, int $productId)
    {
        $product  = Product::withTrashed()->findOrFail($productId);
        $existing = WishlistItem::where('user_id', auth()->id())->where('product_id', $product->id)->exists();
        $wish     = $request->filled('state') ? $request->input('state') === 'on' : ! $existing;

        if ($wish) {
            WishlistItem::firstOrCreate(['user_id' => auth()->id(), 'product_id' => $product->id]);
        } else {
            WishlistItem::where('user_id', auth()->id())->where('product_id', $product->id)->delete();
        }

        if ($request->expectsJson()) {
            return response()->json(['wished' => $wish]);
        }
        return back()->with('success', $wish ? 'Added to your wishlist.' : 'Removed from your wishlist.');
    }
}
