<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Controllers\CategoryPageController;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Category;
use App\Models\ProductVariation;
use App\Models\UserAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    /** Allowed product statuses. */
    private const STATUSES = ['draft', 'active', 'inactive', 'out_of_stock'];

    /**
     * Category-specific specification schemas.
     * Keyed by a lowercase keyword matched against the category name.
     */
    public static function specSchemas(): array
    {
        return [
            'apparel'     => ['Material', 'Color', 'Size', 'Fit', 'Gender', 'Care Instructions'],
            'women'       => ['Material', 'Color', 'Size', 'Fit', 'Gender', 'Care Instructions'],
            'men'         => ['Material', 'Color', 'Size', 'Fit', 'Gender', 'Care Instructions'],
            'clothing'    => ['Material', 'Color', 'Size', 'Fit', 'Gender', 'Care Instructions'],
            'electronics' => ['Brand', 'Model', 'Warranty', 'Battery Life', 'Dimensions'],
            'gadget'      => ['Brand', 'Model', 'Warranty', 'Battery Life', 'Dimensions'],
            'beauty'      => ['Brand', 'Volume / Size', 'Ingredients', 'Expiration Date', 'Skin Type'],
            'health'      => ['Brand', 'Volume / Size', 'Ingredients', 'Expiration Date', 'Skin Type'],
            'cosmetic'    => ['Brand', 'Volume / Size', 'Ingredients', 'Expiration Date', 'Skin Type'],
        ];
    }

    /** Resolve the spec field list for a given category name. */
    public static function specsForCategory(?string $categoryName): array
    {
        if (! $categoryName) return [];
        $name = strtolower($categoryName);
        foreach (self::specSchemas() as $keyword => $fields) {
            if (str_contains($name, $keyword)) {
                return $fields;
            }
        }
        return [];
    }

    /** Subcategory list keyed by category name, from the shared catalog. */
    private function subcategoryMap(): array
    {
        $map = [];
        foreach (CategoryPageController::catalog() as $cat) {
            $map[$cat['name']] = $cat['subs'] ?? [];
        }
        return $map;
    }

    public function index(Request $request)
    {
        $query = Product::where('seller_id', auth()->id())
            ->with(['category', 'images']);

        $status = $request->input('status', 'active');
        if ($status === 'archived') {
            $query->archived();
        } elseif ($status === 'draft') {
            $query->active()->status('draft');
        } elseif ($status === 'low_stock') {
            $query->active()->lowStock();
        } elseif ($status === 'out_of_stock') {
            $query->active()->where('stock', 0);
        } else {
            $query->active();
        }

        if ($request->filled('search')) {
            $query->where(fn($q) =>
                $q->where('title', 'like', '%'.$request->search.'%')
                  ->orWhere('sku', 'like', '%'.$request->search.'%')
                  ->orWhere('product_code', 'like', '%'.$request->search.'%')
            );
        }

        $books    = $query->latest()->get();
        $allBooks = Product::where('seller_id', auth()->id());
        $counts   = [
            'active'       => (clone $allBooks)->active()->count(),
            'draft'        => (clone $allBooks)->active()->status('draft')->count(),
            'low_stock'    => (clone $allBooks)->active()->lowStock()->count(),
            'out_of_stock' => (clone $allBooks)->active()->where('stock', 0)->count(),
            'archived'     => (clone $allBooks)->archived()->count(),
        ];

        return view('seller.books.index', compact('books', 'status', 'counts'));
    }

    public function create()
    {
        return view('seller.books.form', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validateProduct($request);

        $book = DB::transaction(function () use ($request, $data) {
            $payload = $this->buildPayload($request, $data);
            $payload['seller_id'] = auth()->id();

            if ($request->hasFile('cover_image')) {
                $payload['image'] = $request->file('cover_image')->store('products', 'public');
            }
            if ($request->hasFile('video')) {
                $payload['video_path'] = $request->file('video')->store('products/videos', 'public');
            }

            $book = Product::create($payload);

            // Auto product code once we have an id.
            $book->product_code = 'ALVY-' . str_pad((string) $book->id, 6, '0', STR_PAD_LEFT);
            $book->save();

            $this->syncImages($request, $book);
            $this->syncVariations($request, $book);

            return $book;
        });

        return redirect()->route('seller.books.index')
            ->with('success', $this->successMessage($book, $request));
    }

    public function edit(Product $book)
    {
        $this->authorizeBook($book);
        $book->load(['images', 'variations']);
        return view('seller.books.form', array_merge($this->formData(), ['book' => $book]));
    }

    public function update(Request $request, Product $book)
    {
        $this->authorizeBook($book);
        $data = $this->validateProduct($request, $book->id);

        DB::transaction(function () use ($request, $data, $book) {
            $payload = $this->buildPayload($request, $data);

            if ($request->hasFile('cover_image')) {
                $payload['image'] = $request->file('cover_image')->store('products', 'public');
            }
            if ($request->hasFile('video')) {
                $payload['video_path'] = $request->file('video')->store('products/videos', 'public');
            }

            $book->update($payload);

            if (empty($book->product_code)) {
                $book->product_code = 'ALVY-' . str_pad((string) $book->id, 6, '0', STR_PAD_LEFT);
                $book->save();
            }

            $this->syncImages($request, $book);
            $this->syncVariations($request, $book);
        });

        return redirect()->route('seller.books.index')
            ->with('success', $this->successMessage($book->fresh(), $request));
    }

    public function destroy(Product $book)
    {
        $this->authorizeBook($book);
        $book->delete();
        return back()->with('success', 'Product deleted.');
    }

    public function archive(Product $book)
    {
        $this->authorizeBook($book);
        $book->update(['is_archived' => true, 'archived_at' => now()]);
        return back()->with('success', '"' . $book->title . '" archived.');
    }

    public function unarchive(Product $book)
    {
        $this->authorizeBook($book);
        $book->update(['is_archived' => false, 'archived_at' => null]);
        return back()->with('success', '"' . $book->title . '" restored to active listings.');
    }

    public function updateStock(Request $request, Product $book)
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

    /** Shared data for the create/edit form. */
    private function formData(): array
    {
        return [
            'categories'      => Category::orderBy('name')->get(),
            'subcategoryMap'  => $this->subcategoryMap(),
            'specSchemas'     => self::specSchemas(),
            'addresses'       => UserAddress::where('user_id', auth()->id())
                                    ->orderByDesc('is_default')->get(),
            'statuses'        => self::STATUSES,
        ];
    }

    /** Whether the seller submitted "Save as Draft". */
    private function isDraftSubmit(Request $request): bool
    {
        return $request->input('action') === 'draft' || $request->input('status') === 'draft';
    }

    private function validateProduct(Request $request, ?int $bookId = null): array
    {
        // Drafts validate loosely so sellers can save partial work.
        $draft = $this->isDraftSubmit($request);

        $rules = [
            'title'            => ($draft ? 'nullable' : 'required') . '|string|max:255',
            'category_id'      => ($draft ? 'nullable' : 'required') . '|exists:categories,id',
            'subcategory'      => 'nullable|string|max:255',
            'brand'            => 'nullable|string|max:255',
            'description'      => 'nullable|string|max:5000',

            'price'            => ($draft ? 'nullable' : 'required') . '|numeric|min:0',
            'sale_price'       => 'nullable|numeric|min:0|lte:price',
            'discount_percent' => 'nullable|numeric|min:0|max:99',
            'voucher_code'     => 'nullable|string|max:50',
            'stock'            => ($draft ? 'nullable' : 'required') . '|integer|min:0',
            'sku'              => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($bookId)],
            'status'           => ['nullable', Rule::in(self::STATUSES)],

            // Optional book-specific fields (ALVY is a general shop now).
            'author'           => 'nullable|string|max:255',
            'isbn'             => ['nullable', 'string', 'max:20', Rule::unique('products', 'isbn')->ignore($bookId)],
            'publisher'        => 'nullable|string|max:255',
            'publication_year' => 'nullable|integer|min:1000|max:' . (date('Y') + 1),
            'edition'          => 'nullable|string|max:50',
            'language'         => 'nullable|string|max:50',
            'pages'            => 'nullable|integer|min:1',
            'format'           => 'nullable|string|max:50',

            // Media
            'cover_image'      => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'images'           => 'nullable|array|max:9',
            'images.*'         => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'video'            => 'nullable|mimetypes:video/mp4,video/quicktime,video/webm|max:20480',

            // Specs (free-form key/value from category schema)
            'specs'            => 'nullable|array',
            'specs.*'          => 'nullable|string|max:255',

            // Variations
            'variations'                => 'nullable|array',
            'variations.*.name'         => 'nullable|string|max:255',
            'variations.*.price'        => 'nullable|numeric|min:0',
            'variations.*.stock'        => 'nullable|integer|min:0',
            'variations.*.sku'          => 'nullable|string|max:100',

            // Shipping
            'weight_kg'        => 'nullable|numeric|min:0',
            'length_cm'        => 'nullable|numeric|min:0',
            'width_cm'         => 'nullable|numeric|min:0',
            'height_cm'        => 'nullable|numeric|min:0',
            'address_id'       => ['nullable', Rule::exists('user_addresses', 'id')->where('user_id', auth()->id())],

            // Image ordering / removal
            'image_order'      => 'nullable|array',
            'image_order.*'    => 'nullable|integer',
            'removed_images'   => 'nullable|array',
            'removed_images.*' => 'nullable|integer',
        ];

        $messages = [
            'sale_price.lte'   => 'The sale price must be less than or equal to the original price.',
            'title.required'   => 'Please enter a product name.',
            'price.required'   => 'Please enter a price.',
            'stock.required'   => 'Please enter the available stock.',
        ];

        return $request->validate($rules, $messages);
    }

    /** Build the products-table payload from validated data. */
    private function buildPayload(Request $request, array $data): array
    {
        $status = $data['status'] ?? 'active';
        if ($this->isDraftSubmit($request)) {
            $status = 'draft';
        }

        $stock = (int) ($data['stock'] ?? 0);

        // Filter out empty spec values.
        $specs = collect($request->input('specs', []))
            ->filter(fn($v) => $v !== null && $v !== '')
            ->toArray();

        $payload = [
            'category_id'      => $data['category_id'] ?? null,
            'subcategory'      => $data['subcategory'] ?? null,
            'brand'            => $data['brand'] ?? null,
            'title'            => $data['title'] ?? 'Untitled draft',
            'description'      => $data['description'] ?? null,

            'price'            => $data['price'] ?? 0,
            'sale_price'       => $data['sale_price'] ?? null,
            'discount_percent' => $data['discount_percent'] ?? 0,
            'voucher_code'     => $data['voucher_code'] ?? null,
            'stock'            => $stock,
            'sku'              => $data['sku'] ?? null,

            'author'           => $data['author'] ?? null,
            'isbn'             => $data['isbn'] ?? null,
            'publisher'        => $data['publisher'] ?? null,
            'publication_year' => $data['publication_year'] ?? null,
            'edition'          => $data['edition'] ?? null,
            'language'         => $data['language'] ?? null,
            'pages'            => $data['pages'] ?? null,
            'format'           => $data['format'] ?? null,

            'specs'            => $specs ?: null,

            'weight_kg'        => $data['weight_kg'] ?? null,
            'length_cm'        => $data['length_cm'] ?? null,
            'width_cm'         => $data['width_cm'] ?? null,
            'height_cm'        => $data['height_cm'] ?? null,
            'address_id'       => $data['address_id'] ?? null,

            'status'           => $status,
            'availability'     => $stock > 0 ? 'in_stock' : 'out_of_stock',
        ];

        // Auto out-of-stock status override when nothing is left (unless draft).
        if ($status !== 'draft' && $stock <= 0) {
            $payload['status'] = 'out_of_stock';
        }

        return $payload;
    }

    /**
     * Sync product images: remove flagged existing images, re-order the rest,
     * append new uploads. The image with the lowest sort_order is the main image.
     */
    private function syncImages(Request $request, Product $book): void
    {
        // 1. Remove images the seller deleted.
        $removed = array_filter((array) $request->input('removed_images', []));
        if ($removed) {
            $toDelete = $book->images()->whereIn('id', $removed)->get();
            foreach ($toDelete as $img) {
                Storage::disk('public')->delete($img->path);
                $img->delete();
            }
        }

        // 2. Re-order existing images per submitted order.
        $order = (array) $request->input('image_order', []);
        $position = 0;
        foreach ($order as $imageId) {
            $img = $book->images()->whereKey($imageId)->first();
            if ($img) {
                $img->update(['sort_order' => $position]);
                $position++;
            }
        }

        // 3. Append newly uploaded images after the existing ones.
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $file) {
                if (! $file || ! $file->isValid()) continue;
                $path = $file->store('products/gallery', 'public');
                ProductImage::create([
                    'product_id'    => $book->id,
                    'path'       => $path,
                    'label'      => $position === 0 ? 'Main' : 'Photo ' . ($position + 1),
                    'sort_order' => $position,
                ]);
                $position++;
            }
        }

        // 4. Keep the single "image" column in sync with the main gallery image.
        $book->load('images');
        $main = $book->images->first();
        if ($main && $book->image !== $main->path) {
            $book->update(['image' => $main->path]);
        }
    }

    /** Replace variations with the submitted set (per-variation inventory). */
    private function syncVariations(Request $request, Product $book): void
    {
        $variations = (array) $request->input('variations', []);
        $book->variations()->delete();

        $sort = 0;
        foreach ($variations as $v) {
            $name = trim($v['name'] ?? '');
            if ($name === '') continue;
            ProductVariation::create([
                'product_id'    => $book->id,
                'name'       => $name,
                'price'      => ($v['price'] ?? '') !== '' ? $v['price'] : null,
                'stock'      => (int) ($v['stock'] ?? 0),
                'sku'        => $v['sku'] ?? null,
                'sort_order' => $sort++,
            ]);
        }

        // When variations exist, base stock mirrors their total.
        if ($sort > 0) {
            $total = (int) $book->variations()->sum('stock');
            $book->update([
                'stock'        => $total,
                'availability' => $total > 0 ? 'in_stock' : 'out_of_stock',
            ]);
        }
    }

    private function successMessage(Product $book, Request $request): string
    {
        $code = $book->product_code ?: ('ALVY-' . str_pad((string) $book->id, 6, '0', STR_PAD_LEFT));

        if ($book->status === 'draft') {
            return 'Draft saved. Product ID: ' . $code;
        }
        return 'Product published successfully. Product ID: ' . $code;
    }

    private function authorizeBook(Product $book): void
    {
        if ($book->seller_id !== auth()->id()) {
            abort(403, 'You can only manage your own products.');
        }
    }
}
