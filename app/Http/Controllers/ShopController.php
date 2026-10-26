<?php

namespace App\Http\Controllers;

use App\Models\SellerApplication;
use App\Support\ProductSearch;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request, ProductSearch $search)
    {
        $filtering = $request->hasAny(['min_price', 'max_price', 'rating', 'category', 'location', 'sort', 'store', 'availability']);

        // A category link without a search: go to that category's page.
        if (! $request->filled('search') && $request->filled('category') && ! $filtering) {
            $match = collect(CategoryPageController::catalog())->first(fn ($c) =>
                str_contains(strtolower($c['name']), strtolower($request->category)) ||
                str_contains(strtolower($request->category), strtolower($c['name'])));
            if ($match) {
                return redirect()->route('categories.show', $match['slug']);
            }
        }

        // An empty search shows everything (newest first).
        $term = trim((string) $request->input('search'));

        [$query, $notes] = $search->apply($request, $term);
        $products = $query->paginate(24)->withQueryString();

        // Nothing found: try the closest spelling ("Ratan baskit" → "rattan basket").
        $suggestion = null;
        if ($products->total() === 0 && $term !== '' && ($suggestion = $search->suggestion($term))) {
            [$query] = $search->apply($request, $suggestion);
            $products = $query->paginate(24)->withQueryString();
            if ($products->total() === 0) {
                $suggestion = null;
            }
        }

        $categories = collect(CategoryPageController::catalog())->pluck('name');
        $locations  = SellerApplication::where('status', 'approved')->whereNotNull('province')
            ->distinct()->orderBy('province')->pluck('province');

        // Stores with something on sale: only the public shop name is used.
        $stores = SellerApplication::where('status', 'approved')->whereNotNull('shop_name')
            ->whereHas('user.products', fn ($p) => $p->published())
            ->orderBy('shop_name')->pluck('shop_name', 'user_id');

        // Stores whose name matches the search (every word), shown above the products.
        $matchedStores = collect();
        if ($term !== '') {
            $q = SellerApplication::where('status', 'approved')->whereNotNull('shop_name')
                ->whereHas('user.products', fn ($p) => $p->published());
            foreach (preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY) as $word) {
                $q->where('shop_name', 'like', '%' . ProductSearch::likeEscape($word) . '%');
            }
            $matchedStores = $q->orderBy('shop_name')->limit(6)->get(['user_id', 'shop_name', 'municipality', 'province', 'logo_path']);
        }

        return view('shop.index', compact('products', 'term', 'suggestion', 'notes', 'categories', 'locations', 'stores', 'matchedStores'));
    }
}
