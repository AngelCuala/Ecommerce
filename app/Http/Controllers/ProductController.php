<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function show(string $slug)
    {
        // Slug format: {title-slug}-{id}
        $id = (int) last(explode('-', $slug));

        $product = Book::with(['category', 'seller', 'images'])
            ->findOrFail($id);

        $related = Book::with(['category', 'images'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get();

        return view('products.show', compact('product', 'related'));
    }
}
