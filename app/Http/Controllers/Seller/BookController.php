<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookImage;
use App\Models\Category;
use Illuminate\Http\Request;

class BookController extends Controller
{
    private const IMAGE_LABELS = [
        'front_cover'  => 'Front Cover',
        'back_cover'   => 'Back Cover',
        'spine'        => 'Spine',
        'inside_pages' => 'Inside Pages',
        'defects'      => 'Damage / Defects',
    ];

    public function index(Request $request)
    {
        $query = Book::where('seller_id', auth()->id())
            ->with(['category', 'images']);

        // Filter by status
        $status = $request->input('status', 'active');
        if ($status === 'archived') {
            $query->archived();
        } elseif ($status === 'low_stock') {
            $query->active()->lowStock();
        } elseif ($status === 'out_of_stock') {
            $query->active()->where('stock', 0);
        } else {
            $query->active();
        }

        // Search
        if ($request->filled('search')) {
            $query->where(fn($q) =>
                $q->where('title', 'like', '%'.$request->search.'%')
                  ->orWhere('sku', 'like', '%'.$request->search.'%')
            );
        }

        $books    = $query->latest()->get();
        $allBooks = Book::where('seller_id', auth()->id());
        $counts   = [
            'active'       => (clone $allBooks)->active()->count(),
            'low_stock'    => (clone $allBooks)->active()->lowStock()->count(),
            'out_of_stock' => (clone $allBooks)->active()->where('stock', 0)->count(),
            'archived'     => (clone $allBooks)->archived()->count(),
        ];

        return view('seller.books.index', compact('books', 'status', 'counts'));
    }

    public function create()
    {
        $categories  = Category::orderBy('name')->get();
        $imageLabels = self::IMAGE_LABELS;
        return view('seller.books.form', compact('categories', 'imageLabels'));
    }

    public function store(Request $request)
    {
        $data = $this->validateBook($request);

        if ($request->hasFile('cover_image')) {
            $data['image'] = $request->file('cover_image')->store('books', 'public');
        }

        $data['seller_id']        = auth()->id();
        $data['availability']     = $data['stock'] > 0 ? 'in_stock' : 'out_of_stock';
        $data['discount_percent'] = $data['discount_percent'] ?? 0;

        $book = Book::create($data);
        $this->storeGalleryImages($request, $book);

        return redirect()->route('seller.books.index')
            ->with('success', '"' . $book->title . '" listed successfully.');
    }

    public function edit(Book $book)
    {
        $this->authorizeBook($book);
        $categories  = Category::orderBy('name')->get();
        $imageLabels = self::IMAGE_LABELS;
        $book->load('images');
        return view('seller.books.form', compact('book', 'categories', 'imageLabels'));
    }

    public function update(Request $request, Book $book)
    {
        $this->authorizeBook($book);

        $data = $this->validateBook($request, $book->id);

        if ($request->hasFile('cover_image')) {
            $data['image'] = $request->file('cover_image')->store('books', 'public');
        }

        $data['availability']     = $data['stock'] > 0 ? 'in_stock' : 'out_of_stock';
        $data['discount_percent'] = $data['discount_percent'] ?? 0;

        $book->update($data);
        $this->storeGalleryImages($request, $book);

        return redirect()->route('seller.books.index')
            ->with('success', '"' . $book->title . '" updated.');
    }

    public function destroy(Book $book)
    {
        $this->authorizeBook($book);
        $book->delete();
        return back()->with('success', 'Product deleted.');
    }

    /** Archive (soft-hide) instead of delete */
    public function archive(Book $book)
    {
        $this->authorizeBook($book);
        $book->update(['is_archived' => true, 'archived_at' => now()]);
        return back()->with('success', '"' . $book->title . '" archived.');
    }

    /** Restore from archive */
    public function unarchive(Book $book)
    {
        $this->authorizeBook($book);
        $book->update(['is_archived' => false, 'archived_at' => null]);
        return back()->with('success', '"' . $book->title . '" restored to active listings.');
    }

    /** Quick stock update */
    public function updateStock(Request $request, Book $book)
    {
        $this->authorizeBook($book);
        $request->validate(['stock' => 'required|integer|min:0']);
        $book->update([
            'stock'        => $request->stock,
            'availability' => $request->stock > 0 ? 'in_stock' : 'out_of_stock',
        ]);
        return back()->with('success', 'Stock updated to ' . $request->stock . '.');
    }

    // ── Helpers ───────────────────────────────────────────────

    private function validateBook(Request $request, ?int $bookId = null): array
    {
        return $request->validate([
            'title'            => 'required|string|max:255',
            'author'           => 'required|string|max:255',
            'isbn'             => 'required|string|max:20|unique:books,isbn' . ($bookId ? ",$bookId" : ''),
            'publisher'        => 'required|string|max:255',
            'publication_year' => 'required|integer|min:1000|max:' . (date('Y') + 1),
            'edition'          => 'nullable|string|max:50',
            'language'         => 'nullable|string|max:50',
            'pages'            => 'nullable|integer|min:1',
            'format'           => 'required|in:Paperback,Hardcover,eBook,Other',
            'category_id'      => 'required|exists:categories,id',
            'price'            => 'required|numeric|min:0',
            'discount_percent' => 'nullable|numeric|min:0|max:99',
            'voucher_code'     => 'nullable|string|max:50',
            'stock'            => 'required|integer|min:0',
            'sku'              => 'nullable|string|max:100|unique:books,sku' . ($bookId ? ",$bookId" : ''),
            'description'      => 'nullable|string|max:3000',
            'cover_image'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
        ]);
    }

    private function storeGalleryImages(Request $request, Book $book): void
    {
        foreach (self::IMAGE_LABELS as $key => $label) {
            if ($request->hasFile("images.$key")) {
                $path = $request->file("images.$key")->store('books/gallery', 'public');
                BookImage::create([
                    'book_id'    => $book->id,
                    'path'       => $path,
                    'label'      => $label,
                    'sort_order' => array_search($key, array_keys(self::IMAGE_LABELS)),
                ]);
            }
        }
    }

    private function authorizeBook(Book $book): void
    {
        if ($book->seller_id !== auth()->id()) {
            abort(403, 'You can only manage your own products.');
        }
    }
}
