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

        // Drafts, archived products and products not (yet) approved by an admin aren't public.
        // (Sellers and admins don't browse the storefront; they review products in their panels.)
        abort_unless($product->isPublished(), 404);

        $related = Product::with(['category', 'images'])
            ->published()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get();

        return view('products.show', compact('product', 'related'));
    }
}
