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
        } elseif ($status === 'review') {
            $query->active()->whereIn('approval_status', ['pending', 'rejected'])->where('status', '!=', 'draft');
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
            'review'       => (clone $allBooks)->active()->whereIn('approval_status', ['pending', 'rejected'])
                ->where('status', '!=', 'draft')->count(),
        ];

        return view('seller.books.index', compact('books', 'status', 'counts'));
    }

    public function create()
    {
        return view('seller.books.form', $this->formData());
    }

    public function store(Request $request)
    {
        if ($blocked = $this->blockUnverifiedPublish($request)) {
            return $blocked;
        }
        // The same form sent twice (double click, Enter twice, Back and resubmit) creates one product.
        if ($token = $request->input('form_token')) {
            if (! \Illuminate\Support\Facades\Cache::add('product-form:' . auth()->id() . ':' . $token, true, now()->addHour())) {
                return redirect()->route('seller.books.index')->with('success', 'This product was already saved.');
            }
        }
        $data = $this->validateProduct($request);

        $book = DB::transaction(function () use ($request, $data) {
            $payload = $this->buildPayload($request, $data);
            $payload['seller_id'] = auth()->id();
            // New products wait for an admin before they appear in the shop.
            $payload['approval_status'] = 'pending';

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
            $this->syncStoreCategories($request, $book);

            return $book;
        });

        \App\Models\ActivityLog::note('"' . $book->title . '" (' . $book->product_code . ') · ₱' . number_format((float) $book->price, 2)
            . ' · stock ' . $book->stock . ($book->status === 'draft' ? ' · draft' : ' · sent for approval'), $book);

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
        if ($blocked = $this->blockUnverifiedPublish($request)) {
            return $blocked;
        }
        $data = $this->validateProduct($request, $book->id, $book);

        DB::transaction(function () use ($request, $data, $book) {
            $payload = $this->buildPayload($request, $data);

            if ($request->hasFile('cover_image')) {
                $payload['image'] = $request->file('cover_image')->store('products', 'public');
            }
            if ($request->hasFile('video')) {
                $payload['video_path'] = $request->file('video')->store('products/videos', 'public');
            }

            // Editing an approved product needs no new approval; fixing a rejected one
            // sends it back to the admin.
            if ($book->isRejected()) {
                $payload += ['approval_status' => 'pending', 'approval_note' => null,
                             'approval_reviewed_at' => null, 'approval_reviewed_by' => null];
            }

            $book->update($payload);

            if (empty($book->product_code)) {
                $book->product_code = 'ALVY-' . str_pad((string) $book->id, 6, '0', STR_PAD_LEFT);
                $book->save();
            }

            $this->syncImages($request, $book);
            $this->syncVariations($request, $book);
            $this->syncStoreCategories($request, $book);
        });

        $saved = $book->fresh();
        \App\Models\ActivityLog::note('"' . $saved->title . '" (' . $saved->product_code . ') · ₱' . number_format((float) $saved->price, 2)
            . ' · stock ' . $saved->stock, $saved);

        return redirect()->route('seller.books.index')
            ->with('success', $this->successMessage($saved, $request));
    }

    public function destroy(Product $book)
    {
        $this->authorizeBook($book);
        if ($this->hasActiveOrders($book)) {
            return back()->with('error', '"' . $book->title . '" has orders that are not finished yet, so it cannot be deleted. Deactivate it instead.');
        }
        $book->delete();   // soft delete: order history still shows the product
        return back()->with('success', 'Product deleted.');
    }

    /** Orders that still need work (not Completed, Cancelled or Refunded). */
    private function hasActiveOrders(Product $book): bool
    {
        return \App\Models\OrderItem::where('product_id', $book->id)
            ->whereHas('order', fn ($q) => $q->whereIn('status', [
                \App\Models\Order::AWAITING_PAYMENT, \App\Models\Order::PENDING, \App\Models\Order::PROCESSING,
                \App\Models\Order::SHIPPED, \App\Models\Order::DELIVERED, \App\Models\Order::RETURN_REQUESTED, \App\Models\Order::IN_DISPUTE,
            ]))->exists();
    }

    /** Copy a listing: same details, new product ID, saved as a draft named "Copy of …". */
    public function duplicate(Product $book)
    {
        $this->authorizeBook($book);
        $book->load(['images', 'variations', 'storeCategories']);

        $copy = DB::transaction(function () use ($book) {
            $copy = $book->replicate(['product_code', 'sku', 'isbn', 'approval_note', 'approval_reviewed_at', 'approval_reviewed_by', 'is_archived', 'archived_at']);
            $copy->fill([
                'title'           => \Illuminate\Support\Str::limit('Copy of ' . $book->title, 255, ''),
                'status'          => 'draft',
                'approval_status' => 'pending',
                'is_archived'     => false,
            ]);
            $copy->image = $book->image ? $this->copyFile($book->image) : null;
            $copy->save();
            $copy->update(['product_code' => 'ALVY-' . str_pad((string) $copy->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($book->images as $img) {
                ProductImage::create(['product_id' => $copy->id, 'path' => $this->copyFile($img->path), 'label' => $img->label, 'sort_order' => $img->sort_order]);
            }
            $copy->load('images');
            if ($main = $copy->images->first()) {
                $copy->update(['image' => $main->path]);
            }
            foreach ($book->variations as $v) {
                // SKUs must stay unique in the store, so the copy's options start without one.
                ProductVariation::create(['product_id' => $copy->id, 'name' => $v->name, 'price' => $v->price, 'stock' => $v->stock,
                    'image_path' => $v->image_path ? $this->copyFile($v->image_path) : null, 'sort_order' => $v->sort_order]);
            }
            $copy->storeCategories()->sync($book->storeCategories->pluck('id'));

            return $copy;
        });

        \App\Models\ActivityLog::note('"' . $book->title . '" copied as ' . $copy->product_code, $copy);

        return redirect()->route('seller.books.edit', $copy)->with('success', 'Copy saved as a draft (Product ID ' . $copy->product_code . '). Review it, then publish.');
    }

    private function copyFile(string $path): string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return $path;
        }
        $new = dirname($path) . '/' . \Illuminate\Support\Str::random(24) . '.' . pathinfo($path, PATHINFO_EXTENSION);
        $disk->copy($path, $new);

        return $new;
    }

    /** Bulk Deactivate / Bulk Delete on selected products. Products with unfinished orders are never deleted. */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:deactivate,activate,delete',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
        ], ['ids.required' => 'Select at least one product.']);

        $books = Product::where('seller_id', auth()->id())->whereIn('id', $data['ids'])->get();
        if ($books->isEmpty()) {
            return back()->with('error', 'Select at least one of your products.');
        }

        if ($data['action'] !== 'delete') {
            $archive = $data['action'] === 'deactivate';
            $books->each->update(['is_archived' => $archive, 'archived_at' => $archive ? now() : null]);
            \App\Models\ActivityLog::note(($archive ? 'Deactivated ' : 'Reactivated ') . $books->count() . ' products');

            return back()->with('success', $books->count() . ' ' . \Illuminate\Support\Str::plural('product', $books->count()) . ($archive ? ' deactivated.' : ' reactivated.'));
        }

        [$skipped, $deletable] = $books->partition(fn ($b) => $this->hasActiveOrders($b));
        $deletable->each->delete();
        \App\Models\ActivityLog::note('Deleted ' . $deletable->count() . ' products' . ($skipped->isNotEmpty() ? ', skipped ' . $skipped->count() : ''));

        $msg = $deletable->count() . ' ' . \Illuminate\Support\Str::plural('product', $deletable->count()) . ' deleted.';
        if ($skipped->isNotEmpty()) {
            $msg .= ' Skipped ' . $skipped->map(fn ($b) => '"' . $b->title . '"')->join(', ') . ': it has orders that are not finished yet.';
        }

        return back()->with($skipped->isNotEmpty() ? 'warning' : 'success', $msg);
    }

    /** End a running (or scheduled) sale right away. Orders already placed keep the price paid. */
    public function endSale(Product $book)
    {
        $this->authorizeBook($book);
        if (! $book->saleState() || $book->saleState() === 'Ended') {
            return back()->with('error', 'This product has no running or scheduled sale.');
        }
        $book->update(['sale_ends_at' => now(), 'sale_starts_at' => $book->sale_starts_at && $book->sale_starts_at->isFuture() ? now() : $book->sale_starts_at]);
        \App\Models\ActivityLog::note('Ended the sale on "' . $book->title . '"', $book);

        return back()->with('success', 'The sale on "' . $book->title . '" has ended. Buyers now see ₱' . number_format($book->price, 2) . '.');
    }

    public function archive(Product $book)
    {
        $this->authorizeBook($book);
        $book->update(['is_archived' => true, 'archived_at' => now()]);
        return back()->with('success', '"' . $book->title . '" deactivated. Buyers can no longer see it.');
    }

    public function unarchive(Product $book)
    {
        $this->authorizeBook($book);
        $book->update(['is_archived' => false, 'archived_at' => null]);
        return back()->with('success', '"' . $book->title . '" reactivated.');
    }

    public function updateStock(Request $request, Product $book)
    {
        $this->authorizeBook($book);
        $request->validate(['stock' => 'required|integer|min:0|max:99999'], [
            'stock.integer' => 'Stock must be a whole number from 0 to 99,999.',
            'stock.min'     => 'Stock must be a whole number from 0 to 99,999.',
            'stock.max'     => 'Stock must be a whole number from 0 to 99,999.',
        ]);
        \App\Models\ActivityLog::note('"' . $book->title . '" stock ' . $book->stock . ' → ' . $request->stock, $book);
        $book->update([
            'stock'        => (int) $request->stock,
            'availability' => $request->stock > 0 ? 'in_stock' : 'out_of_stock',
            // Sold-out listings stay visible (shown as "Sold out"); restocking makes them buyable again.
            'status'       => $book->status === 'out_of_stock' ? 'active' : $book->status,
        ]);
        return back()->with('success', 'Stock updated to ' . number_format((int) $request->stock) . '.');
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
            'conditions'      => self::CONDITIONS,
            'storeCategories' => \App\Models\StoreCategory::where('seller_id', auth()->id())->whereNull('parent_id')
                                    ->with('children')->orderBy('sort_order')->orderBy('name')->get(),
        ];
    }

    /** Whether the seller submitted "Save as Draft". */
    private function isDraftSubmit(Request $request): bool
    {
        return $request->input('action') === 'draft' || $request->input('status') === 'draft';
    }

    /** Product conditions a seller can choose. */
    public const CONDITIONS = ['New', 'Used - Like New', 'Used - Good', 'Refurbished'];

    /** A seller that isn't Approved yet can save drafts only (assumption A18). */
    private function blockUnverifiedPublish(Request $request)
    {
        if (auth()->user()->isApproved() || auth()->user()->isAdmin() || $this->isDraftSubmit($request)) {
            return null;
        }

        return back()->withInput()->withErrors(['publish' => 'Your store must be verified before you can publish products. You can save this product as a draft for now.']);
    }

    private function validateProduct(Request $request, ?int $bookId = null, ?Product $book = null): array
    {
        // Drafts validate loosely so sellers can save partial work.
        $draft = $this->isDraftSubmit($request);
        $req   = $draft ? 'nullable' : 'required';

        // Empty optional numbers mean "none" (a discount of 0 is not a discount).
        foreach (['discount_percent', 'sale_price', 'length_cm', 'width_cm', 'height_cm', 'sale_starts_at', 'sale_ends_at'] as $f) {
            if ($request->input($f) === '' || ($f === 'discount_percent' && $request->has($f) && $request->input($f) === null)) {
                $request->merge([$f => null]);
            }
        }

        $money = ['numeric', 'min:1', 'max:1000000', 'decimal:0,2'];
        $rules = [
            'title'            => $req . '|string|min:5|max:120',
            'category_id'      => $req . '|exists:categories,id',
            'subcategory'      => 'nullable|string|max:255',
            'brand'            => 'nullable|string|max:255',
            'condition'        => ['nullable', Rule::in(self::CONDITIONS)],
            'description'      => $req . '|string|min:20|max:3000',
            'store_category_ids'   => 'nullable|array|max:3',
            'store_category_ids.*' => ['integer', Rule::exists('store_categories', 'id')->where('seller_id', auth()->id())],

            'price'            => array_merge([$req], $money),
            'sale_price'       => ['nullable', 'numeric', 'min:1', 'decimal:0,2', 'lt:price'],
            'discount_percent' => ['nullable', 'numeric', 'min:1', 'max:99'],
            'voucher_code'     => 'nullable|string|max:50',
            'stock'            => [$req, 'integer', 'min:0', 'max:99999'],
            'purchase_limit'   => 'nullable|integer|min:1|max:99999',
            'sale_starts_at'   => ['nullable', 'date'],
            'sale_ends_at'     => array_merge(['nullable', 'date'], $request->filled('sale_starts_at') ? ['after:sale_starts_at'] : []),
            'variation_labels' => 'nullable|string|max:60',
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

            // Media: JPG or PNG up to 5 MB, 1 to 9 photos (assumption A16).
            'cover_image'      => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
            'images'           => ['nullable', 'array', 'max:' . Product::MAX_IMAGES,
                // Photos already saved (minus any being removed) + new uploads: 1 to the limit.
                function (string $attr, $value, \Closure $fail) use ($request, $bookId) {
                    $total = $this->photoCount($request, $bookId);
                    if ($total > Product::MAX_IMAGES) {
                        $fail('A product can have up to ' . Product::MAX_IMAGES . " photos — this would make {$total}. Remove some first.");
                    }
                }],
            'images.*'         => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
            'video'            => 'nullable|mimetypes:video/mp4,video/quicktime,video/webm|max:20480',

            // Specs (free-form key/value from category schema)
            'specs'            => 'nullable|array',
            'specs.*'          => 'nullable|string|max:255',

            // Variation types (at most 2) and their options (at most 20 each, assumption A7)
            'variation_types'             => 'nullable|array|max:' . ProductVariation::MAX_TYPES,
            'variation_types.*.name'      => 'required|string|max:30|distinct:ignore_case',
            'variation_types.*.options'   => 'required|array|min:1|max:' . ProductVariation::MAX_OPTIONS,
            'variation_types.*.options.*' => 'required|string|max:40|distinct:ignore_case',

            // Variant rows ("Red / S"), each with its own price, stock and SKU
            'variations'                => 'nullable|array',
            'variations.*.name'         => 'required|string|max:120|distinct:ignore_case',
            'variations.*.price'        => array_merge([$req], $money),
            'variations.*.stock'        => [$req, 'integer', 'min:0', 'max:99999'],
            'variations.*.sku'          => ['nullable', 'string', 'max:100', 'distinct:ignore_case'],
            'variations.*.image'        => 'nullable|image|mimes:jpg,jpeg,png|max:5120',

            // Shipping
            'weight_kg'        => [$req, 'numeric', 'min:0.01', 'max:1000'],
            'length_cm'        => 'nullable|numeric|gt:0|max:1000',
            'width_cm'         => 'nullable|numeric|gt:0|max:1000',
            'height_cm'        => 'nullable|numeric|gt:0|max:1000',
            'address_id'       => ['nullable', Rule::exists('user_addresses', 'id')->where('user_id', auth()->id())],

            // Image ordering / removal
            'image_order'      => 'nullable|array',
            'image_order.*'    => 'nullable|integer',
            'removed_images'   => 'nullable|array',
            'removed_images.*' => 'nullable|integer',
        ];

        $priceMsg = 'Enter a price from ₱1.00 to ₱1,000,000.00 with up to 2 decimal places.';
        $stockMsg = 'Stock must be a whole number from 0 to 99,999.';
        $messages = [
            'title.required'        => 'Please enter a product name.',
            'title.min'             => 'The product name must be 5 to 120 characters.',
            'title.max'             => 'The product name must be 5 to 120 characters.',
            'description.required'  => 'Please describe the product (20 to 3,000 characters).',
            'description.min'       => 'The description must be 20 to 3,000 characters.',
            'description.max'       => 'The description must be 20 to 3,000 characters.',
            'store_category_ids.max' => 'A product can be in up to 3 store categories.',
            'category_id.required'  => 'Please choose a platform category.',
            'price.required'        => 'Please enter a price.',
            'price.numeric'         => $priceMsg, 'price.min' => $priceMsg, 'price.max' => $priceMsg, 'price.decimal' => $priceMsg,
            'sale_price.lt'         => 'The sale price must be lower than the original price.',
            'sale_price.min'        => 'The sale price must be at least ₱1.00.',
            'sale_price.decimal'    => 'The sale price can have up to 2 decimal places.',
            'discount_percent.min'  => 'The discount must be from 1% to 99%.',
            'discount_percent.max'  => 'The discount must be from 1% to 99%.',
            'sale_ends_at.after'    => 'The sale must end after it starts.',
            'stock.required'        => 'Please enter the available stock.',
            'stock.integer'         => $stockMsg, 'stock.min' => $stockMsg, 'stock.max' => $stockMsg,
            'weight_kg.required'    => 'Please enter the weight.',
            'weight_kg.numeric'     => 'The weight must be a number of at least 0.01 kg.',
            'weight_kg.min'         => 'The weight must be at least 0.01 kg.',
            'length_cm.gt'          => 'Length must be more than 0 cm.',
            'width_cm.gt'           => 'Width must be more than 0 cm.',
            'height_cm.gt'          => 'Height must be more than 0 cm.',
            'length_cm.numeric'     => 'Length must be a number.',
            'width_cm.numeric'      => 'Width must be a number.',
            'height_cm.numeric'     => 'Height must be a number.',
            'images.max'            => 'A product can have up to ' . Product::MAX_IMAGES . ' photos.',
            'images.*.image'        => 'Each photo must be a JPG or PNG image.',
            'images.*.mimes'        => 'Each photo must be a JPG or PNG image.',
            'images.*.max'          => 'Each photo must be 5 MB or smaller.',
            'variation_types.max'   => 'A product can have at most ' . ProductVariation::MAX_TYPES . ' variation types (for example Color and Size).',
            'variation_types.*.name.required'   => 'Please name each variation type.',
            'variation_types.*.name.distinct'   => 'Each variation type needs a different name.',
            'variation_types.*.options.required' => 'Add at least one option to each variation type.',
            'variation_types.*.options.max'     => 'A variation type can have at most ' . ProductVariation::MAX_OPTIONS . ' options.',
            'variation_types.*.options.*.required' => 'Option names cannot be empty.',
            'variation_types.*.options.*.distinct' => 'Each option must be different (Red and red count as the same).',
            'variations.*.name.required' => 'Option names cannot be empty.',
            'variations.*.name.distinct' => 'Each variant must be different (Red and red count as the same).',
            'variations.*.price.required' => 'Enter a price for every variant.',
            'variations.*.price.numeric' => $priceMsg, 'variations.*.price.min' => $priceMsg,
            'variations.*.price.max' => $priceMsg, 'variations.*.price.decimal' => $priceMsg,
            'variations.*.stock.required' => 'Enter the stock for every variant.',
            'variations.*.stock.integer' => $stockMsg, 'variations.*.stock.min' => $stockMsg, 'variations.*.stock.max' => $stockMsg,
            'variations.*.sku.distinct' => 'Each variant needs a different SKU.',
            'variations.*.image.image' => 'Variant photos must be JPG or PNG images.',
            'variations.*.image.mimes' => 'Variant photos must be JPG or PNG images.',
            'variations.*.image.max'   => 'Variant photos must be 5 MB or smaller.',
        ];

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules, $messages);

        $validator->after(function ($v) use ($request, $draft, $bookId, $book) {
            // Publishing needs at least one photo.
            if (! $draft && $this->photoCount($request, $bookId) < 1) {
                $v->errors()->add('images', 'Please add at least one photo.');
            }

            // A new start time can't be in the past (an unchanged running sale keeps its start).
            if ($request->filled('sale_starts_at') && strtotime((string) $request->sale_starts_at)) {
                $start   = \Carbon\Carbon::parse($request->sale_starts_at);
                $changed = ! $book || ! $book->sale_starts_at || ! $book->sale_starts_at->equalTo($start);
                if ($changed && $start->lt(now()->subMinute())) {
                    $v->errors()->add('sale_starts_at', 'The sale start cannot be in the past.');
                }
            }
            if ($request->filled('sale_ends_at') && ! $request->filled('sale_starts_at') && strtotime((string) $request->sale_ends_at)) {
                $end     = \Carbon\Carbon::parse($request->sale_ends_at);
                $changed = ! $book || ! $book->sale_ends_at || ! $book->sale_ends_at->equalTo($end);
                if ($changed && $end->lte(now())) {
                    $v->errors()->add('sale_ends_at', 'The sale end must be in the future.');
                }
            }

            // Variant rows: at most 2 types ("Red / S") and 20 options per type.
            $rows = collect((array) $request->input('variations', []))->pluck('name')->filter(fn ($n) => is_string($n) && trim($n) !== '');
            $groups = [];
            foreach ($rows as $name) {
                $parts = array_map('trim', explode('/', $name));
                if (count($parts) > ProductVariation::MAX_TYPES) {
                    $v->errors()->add('variations', 'A product can have at most ' . ProductVariation::MAX_TYPES . ' variation types (for example Color and Size).');
                    break;
                }
                foreach ($parts as $i => $part) {
                    if ($part === '') {
                        $v->errors()->add('variations', 'Option names cannot be empty.');
                        break 2;
                    }
                    $groups[$i][mb_strtolower($part)] = true;
                }
            }
            foreach ($groups as $opts) {
                if (count($opts) > ProductVariation::MAX_OPTIONS) {
                    $v->errors()->add('variations', 'A variation type can have at most ' . ProductVariation::MAX_OPTIONS . ' options.');
                    break;
                }
            }

            // A variant with unfinished orders can't be removed (A19).
            if ($book) {
                $rows = collect((array) $request->input('variations', []));
                $kept = $rows->pluck('name')->filter()->map(fn ($n) => mb_strtolower(trim($n)))->all();
                $keptIds = $rows->pluck('id')->filter()->map(fn ($id) => (int) $id)->all();
                foreach ($book->variations as $variant) {
                    if (in_array($variant->id, $keptIds, true) || in_array(mb_strtolower($variant->name), $kept, true)) {
                        continue;
                    }
                    $busy = \App\Models\OrderItem::where('variation_id', $variant->id)->whereHas('order', fn ($q) => $q->whereIn('status', [
                        \App\Models\Order::AWAITING_PAYMENT, \App\Models\Order::PENDING, \App\Models\Order::PROCESSING, \App\Models\Order::SHIPPED,
                        \App\Models\Order::DELIVERED, \App\Models\Order::RETURN_REQUESTED, \App\Models\Order::IN_DISPUTE]))->exists();
                    if ($busy) {
                        $v->errors()->add('variations', 'Variant "' . $variant->name . '" has orders that are not finished yet, so it can\'t be removed. Set its stock to 0 instead.');
                    }
                }
            }

            // SKUs are unique within the store (products and variants).
            $skus = collect((array) $request->input('variations', []))->pluck('sku')->filter()->map(fn ($s) => trim($s))->push($request->input('sku'))->filter();
            if ($skus->isNotEmpty()) {
                $lower = $skus->map(fn ($s) => mb_strtolower($s))->all();
                $taken = ProductVariation::whereIn(DB::raw('LOWER(sku)'), $lower)
                    ->whereHas('product', fn ($q) => $q->where('seller_id', auth()->id())->when($bookId, fn ($q) => $q->where('id', '!=', $bookId)))
                    ->pluck('sku')
                    ->merge(Product::where('seller_id', auth()->id())->when($bookId, fn ($q) => $q->where('id', '!=', $bookId))
                        ->whereIn(DB::raw('LOWER(sku)'), $lower)->pluck('sku'));
                if ($taken->isNotEmpty()) {
                    $v->errors()->add('variations', 'SKU ' . $taken->first() . ' is already used by another product in your store.');
                }
            }
        });

        return $validator->validate();
    }

    /** Photos the product will have after this save (kept + new uploads). */
    private function photoCount(Request $request, ?int $bookId): int
    {
        $kept = $bookId
            ? ProductImage::where('product_id', $bookId)
                ->whereNotIn('id', array_filter((array) $request->input('removed_images', [])))
                ->count()
            : 0;

        return $kept + count(array_filter((array) $request->file('images', [])));
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
            'condition'        => $data['condition'] ?? 'New',
            'title'            => $data['title'] ?? 'Untitled draft',
            'description'      => $data['description'] ?? null,

            'price'            => $data['price'] ?? 0,
            'sale_price'       => $data['sale_price'] ?? null,
            'discount_percent' => $data['discount_percent'] ?? 0,
            'sale_starts_at'   => $data['sale_starts_at'] ?? null,
            'sale_ends_at'     => $data['sale_ends_at'] ?? null,
            'purchase_limit'   => $data['purchase_limit'] ?? null,
            'variation_labels' => ! empty($data['variation_types'])
                ? collect($data['variation_types'])->pluck('name')->map(fn ($n) => trim($n))->join(', ')
                : ($data['variation_labels'] ?? null),
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

        // Nothing left: the listing stays visible as "Sold out" (availability), it isn't hidden.
        if ($status === 'out_of_stock') {
            $payload['status'] = 'active';
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

        // Options are matched by id (a rename keeps the same option), else by name, and updated
        // in place, so buyers' carts and order history keep pointing at them.
        $all      = $book->variations()->get();
        $byId     = $all->keyBy('id');
        $existing = $all->keyBy(fn ($v) => mb_strtolower($v->name));
        $kept = [];
        $keptIds = [];
        $sort = 0;
        foreach ($variations as $index => $v) {
            $name = trim($v['name'] ?? '');
            if ($name === '' || in_array(mb_strtolower($name), $kept, true)) continue;
            $kept[] = mb_strtolower($name);
            $values = [
                'product_id' => $book->id,
                'name'       => $name,
                'price'      => ($v['price'] ?? '') !== '' ? $v['price'] : null,
                'stock'      => (int) ($v['stock'] ?? 0),
                'sku'        => ($v['sku'] ?? '') !== '' ? trim($v['sku']) : null,
                'sort_order' => $sort++,
            ];
            if (($file = $request->file("variations.$index.image")) && $file->isValid()) {
                $values['image_path'] = $file->store('products/variants', 'public');
            }
            $match = $byId[(int) ($v['id'] ?? 0)] ?? $existing[mb_strtolower($name)] ?? null;
            if ($match && in_array($match->id, $keptIds, true)) {
                $match = null;
            }
            $saved = ProductVariation::updateOrCreate(['id' => $match?->id], $values);
            $keptIds[] = $saved->id;
        }
        $book->variations()->whereNotIn('id', $keptIds ?: [0])->delete();

        // When variations exist, base stock mirrors their total.
        if ($sort > 0) {
            $total = (int) $book->variations()->sum('stock');
            $book->update([
                'stock'        => $total,
                'availability' => $total > 0 ? 'in_stock' : 'out_of_stock',
            ]);
        }
    }

    /** The product's store categories (separate from the platform category). */
    private function syncStoreCategories(Request $request, Product $book): void
    {
        if ($request->has('store_category_ids') || $request->boolean('store_categories_present')) {
            $book->storeCategories()->sync(array_map('intval', (array) $request->input('store_category_ids', [])));
        }
    }

    private function successMessage(Product $book, Request $request): string
    {
        $code = $book->product_code ?: ('ALVY-' . str_pad((string) $book->id, 6, '0', STR_PAD_LEFT));

        if ($book->status === 'draft') {
            return 'Draft saved. Product ID: ' . $code;
        }
        if ($book->isPendingApproval()) {
            return 'Product submitted for admin approval. It will appear in the shop once approved. Product ID: ' . $code;
        }
        return 'Product saved successfully. Product ID: ' . $code;
    }

    private function authorizeBook(Product $book): void
    {
        if ((int) $book->seller_id !== (int) auth()->id()) {
            abort(403, 'You can only manage your own products.');
        }
    }
}
