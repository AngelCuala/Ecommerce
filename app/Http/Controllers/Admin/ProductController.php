<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Category;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Book::with(['category', 'seller'])->latest();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%'.$request->search.'%')
                  ->orWhere('author', 'like', '%'.$request->search.'%')
                  ->orWhere('isbn', 'like', '%'.$request->search.'%');
            });
        }

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('name', $request->category));
        }

        $products   = $query->paginate(20)->withQueryString();
        $categories = Category::orderBy('name')->get();

        return view('admin.products.index', compact('products', 'categories'));
    }

    public function create()
    {
        $categories = Category::orderBy('name')->get();
        return view('admin.products.form', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $this->validateBook($request);

        if ($request->hasFile('cover_image')) {
            $data['image'] = $request->file('cover_image')->store('books', 'public');
        }

        $data['availability'] = $data['stock'] > 0 ? 'in_stock' : 'out_of_stock';

        Book::create($data);

        return redirect()->route('admin.products.index')->with('success', 'Book added successfully.');
    }

    public function edit(int $id)
    {
        $product    = Book::with('category')->findOrFail($id);
        $categories = Category::orderBy('name')->get();
        return view('admin.products.form', compact('product', 'categories'));
    }

    public function update(Request $request, int $id)
    {
        $book = Book::findOrFail($id);
        $data = $this->validateBook($request, $id);

        if ($request->hasFile('cover_image')) {
            $data['image'] = $request->file('cover_image')->store('books', 'public');
        }

        $data['availability'] = $data['stock'] > 0 ? 'in_stock' : 'out_of_stock';
        $book->update($data);

        return redirect()->route('admin.products.index')->with('success', 'Book updated.');
    }

    public function destroy(int $id)
    {
        Book::findOrFail($id)->delete();
        return back()->with('success', 'Book deleted.');
    }

    private function validateBook(Request $request, ?int $bookId = null): array
    {
        return $request->validate([
            'title'            => 'required|string|max:255',
            'author'           => 'required|string|max:255',
            'isbn'             => 'required|string|max:20|unique:books,isbn'.($bookId ? ",$bookId" : ''),
            'publisher'        => 'required|string|max:255',
            'publication_year' => 'required|integer|min:1000|max:'.(date('Y') + 1),
            'edition'          => 'nullable|string|max:50',
            'language'         => 'nullable|string|max:50',
            'pages'            => 'nullable|integer|min:1',
            'format'           => 'required|in:Paperback,Hardcover,eBook,Other',
            'category_id'      => 'required|exists:categories,id',
            'price'            => 'required|numeric|min:0',
            'stock'            => 'required|integer|min:0',
            'sku'              => 'nullable|string|max:100|unique:books,sku'.($bookId ? ",$bookId" : ''),
            'description'      => 'nullable|string|max:3000',
            'cover_image'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
        ]);
    }
}
