<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\StoreCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The categories on the seller's store page, picked from the ALVY categories (a shop selling motor parts and
 * makeup adds "Automotive & Motorcycle" and "Health & Beauty"). Each product is tagged with the one for its
 * ALVY category when it is saved. Older free-named categories can still be renamed, hidden or deleted.
 */
class StoreCategoryController extends Controller
{
    public function index()
    {
        $categories = StoreCategory::where('seller_id', auth()->id())->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->withCount('products')])->withCount('products')
            ->orderBy('sort_order')->orderBy('name')->get();

        // ALVY categories this store hasn't added yet.
        $available = \App\Models\Category::whereNotIn('id', $categories->pluck('category_id')->filter())->orderBy('name')->get(['id', 'name']);

        return view('seller.store-categories.index', compact('categories', 'available'));
    }

    /** category_id: the ALVY category to add to the store. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'exists:categories,id', function ($attr, $value, $fail) {
                if (StoreCategory::where('seller_id', auth()->id())->where('category_id', $value)->exists()) {
                    $fail('That category is already in your store.');
                }
            }],
        ], [
            'category_id.required' => 'Please choose a category.',
            'category_id.exists'   => 'Please choose one of the ALVY categories.',
        ]);

        $category = StoreCategory::forPlatformCategory(auth()->id(), (int) $data['category_id']);
        \App\Models\ActivityLog::note('Store category "' . $category->name . '" added', $category);

        return back()->with('success', 'Category "' . $category->name . '" added to your store. Pick it when you add a product.');
    }

    public function update(Request $request, StoreCategory $storeCategory)
    {
        $category = $this->own($storeCategory->id);
        if ($category->category_id) {
            return back()->with('error', "This category comes from ALVY, so its name can't be changed. Delete it and add another one instead.");
        }
        $data = $this->validated($request, $category);
        $old  = $category->name;
        $category->update(['name' => $data['name']]);
        \App\Models\ActivityLog::note('Store category "' . $old . '" renamed to "' . $category->name . '"', $category);

        return back()->with('success', 'Category renamed to "' . $category->name . '".');
    }

    /** New order of the categories (ids top to bottom, within one level). */
    public function reorder(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer']);
        $own  = StoreCategory::where('seller_id', auth()->id())->whereIn('id', $data['ids'])->pluck('id')->all();

        DB::transaction(function () use ($data, $own) {
            foreach (array_values(array_filter($data['ids'], fn ($id) => in_array((int) $id, $own, true))) as $i => $id) {
                StoreCategory::whereKey($id)->update(['sort_order' => $i + 1]);
            }
        });

        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('success', 'Category order saved.');
    }

    /** Hide or show a category on the store page (its products stay buyable). */
    public function toggle(StoreCategory $storeCategory)
    {
        $category = $this->own($storeCategory->id);
        $category->update(['is_hidden' => ! $category->is_hidden]);

        return back()->with('success', 'Category "' . $category->name . '" is now ' . ($category->is_hidden ? 'hidden from' : 'shown on') . ' your store page.');
    }

    /** Delete a category; its products are not deleted, they just become Uncategorized in the store. */
    public function destroy(Request $request, StoreCategory $storeCategory)
    {
        $category = $this->own($storeCategory->id);
        $ids      = $category->children()->pluck('id')->push($category->id);
        $products = DB::table('product_store_category')->whereIn('store_category_id', $ids)->distinct()->count('product_id');

        $subs = $category->children()->orderBy('sort_order')->pluck('name');
        if (($products > 0 || $subs->isNotEmpty()) && ! $request->boolean('confirm')) {
            return back()->with('warning', '"' . $category->name . '"'
                . ($subs->isNotEmpty() ? ' and its subcategories ' . $subs->map(fn ($n) => '"' . $n . '"')->join(', ', ' and ') : '')
                . ' will be deleted. ' . ($products > 0 ? $products . ' ' . \Illuminate\Support\Str::plural('product', $products)
                . ' will move to Uncategorized (they are not deleted). ' : '') . 'Click Delete again to confirm.')
                ->with('confirm_delete', $category->id);
        }

        $name = $category->name;
        $category->delete();   // the pivot rows go with it; products stay
        \App\Models\ActivityLog::note('Deleted store category "' . $name . '"' . ($products ? " ({$products} products now Uncategorized)" : ''));

        return back()->with('success', 'Category "' . $name . '" deleted.' . ($products ? " {$products} " . \Illuminate\Support\Str::plural('product', $products) . ' moved to Uncategorized.' : ''));
    }

    private function validated(Request $request, ?StoreCategory $ignore = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name'      => ['required', 'string', 'max:' . StoreCategory::MAX_NAME, function ($attr, $value, $fail) use ($ignore) {
                $exists = StoreCategory::where('seller_id', auth()->id())
                    ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($value)])
                    ->when($ignore, fn ($q) => $q->where('id', '!=', $ignore->id))
                    ->exists();
                if ($exists) {
                    $fail('A category named "' . $value . '" already exists in your store.');
                }
            }],
            'parent_id' => 'nullable|integer',
        ], [
            'name.required' => 'Please enter a category name.',
            'name.max'      => 'Category names can be up to ' . StoreCategory::MAX_NAME . ' characters.',
        ]);
    }

    private function own(int $id): StoreCategory
    {
        return StoreCategory::where('seller_id', auth()->id())->findOrFail($id);
    }
}
