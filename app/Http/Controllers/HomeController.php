<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;

class HomeController extends Controller
{
    public function index()
    {
        // Sorting center staff should never see the buyer homepage
        if (auth()->check() && auth()->user()->role === 'sorting_center') {
            return redirect()->route('sc.dashboard');
        }

        $published = fn () => Product::with('category')->published()->withSum('orderItems as sold', 'quantity');

        // Newest listings
        $featured = $published()->latest()->take(12)->get();

        // Best sellers by units sold; topped up with newer listings if there aren't enough sales yet.
        $topIds = OrderItem::selectRaw('product_id, SUM(quantity) as sold')
            ->groupBy('product_id')->orderByDesc('sold')->take(12)->pluck('product_id');
        $trending = $published()->whereIn('id', $topIds)->get()
            ->sortBy(fn ($p) => $topIds->search($p->id))->values();
        if ($trending->count() < 6) {
            $trending = $trending->concat(
                $published()->whereNotIn('id', $trending->pluck('id'))->inRandomOrder()->take(6 - $trending->count())->get()
            );
        }

        // Category shortcuts (photos live in public/images/categories) with item counts.
        $dbCategories = Category::withCount(['products' => fn ($q) => $q->published()])->get()->keyBy('name');
        $shortcuts = collect(CategoryPageController::catalog())->map(function ($c) use ($dbCategories) {
            $db = $dbCategories->get($c['name']);
            return (object) [
                'name'  => $c['name'],
                'slug'  => $c['slug'],
                'count' => $db->products_count ?? 0,
            ];
        });

        return view('home.index', compact('featured', 'trending', 'shortcuts'));
    }
}
