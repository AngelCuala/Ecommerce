<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request)
    {
        // If there's a search query, keep the shop page for search results
        if ($request->filled('search')) {
            $query = Book::with(['category', 'images'])
                ->active()
                ->where('stock', '>', 0)
                ->where(function ($q) use ($request) {
                    $q->where('title', 'like', '%' . $request->search . '%')
                      ->orWhere('author', 'like', '%' . $request->search . '%')
                      ->orWhere('description', 'like', '%' . $request->search . '%');
                });

            if ($request->filled('min_price')) {
                $query->where('price', '>=', $request->min_price);
            }
            if ($request->filled('max_price')) {
                $query->where('price', '<=', $request->max_price);
            }

            match ($request->get('sort', 'newest')) {
                'price_low'  => $query->orderBy('price'),
                'price_high' => $query->orderByDesc('price'),
                default      => $query->latest(),
            };

            $products   = $query->get();
            $categories = Category::orderBy('name')->get();
            return view('shop.index', compact('products', 'categories'));
        }

        // If a category is passed, find the matching slug and redirect
        if ($request->filled('category')) {
            $catalog = \App\Http\Controllers\CategoryPageController::catalog();
            $match   = collect($catalog)->first(fn($c) =>
                str_contains(strtolower($c['name']), strtolower($request->category)) ||
                str_contains(strtolower($request->category), strtolower($c['name']))
            );
            if ($match) {
                return redirect()->route('categories.show', $match['slug']);
            }
        }

        // Default: redirect to the category page
        return redirect()->route('categories.page');
    }
}
