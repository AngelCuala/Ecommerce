<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function show(string $slug)
    {
        // Slug format: {title-slug}-{id}
        $id = (int) last(explode('-', $slug));

        $product = Product::with(['category', 'seller', 'images', 'variations'])
            ->findOrFail($id);

        $related = Product::with(['category', 'images'])
            ->published()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get();

        return view('products.show', compact('product', 'related'));
    }
}
