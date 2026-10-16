<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\StoreCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The seller's own store categories: two levels at most (category → subcategory),
 * names unique within the store (ignoring case and outer spaces).
 */
class StoreCategoryController extends Controller
{
    public function index()
    {
        $categories = StoreCategory::where('seller_id', auth()->id())->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->withCount('products')])->withCount('products')
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('seller.store-categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $parent = isset($data['parent_id']) ? $this->own((int) $data['parent_id']) : null;
        if ($parent && $parent->parent_id) {
            return back()->withInput()->withErrors(['parent_id' => 'Store categories have at most 2 levels: a subcategory can\'t have its own subcategory.']);
        }

        $category = StoreCategory::create([
            'seller_id'  => auth()->id(),
            'parent_id'  => $parent?->id,
            'name'       => $data['name'],
            'sort_order' => (int) StoreCategory::where('seller_id', auth()->id())->where('parent_id', $parent?->id)->max('sort_order') + 1,
        ]);
        \App\Models\ActivityLog::note('Store category "' . $category->name . '"' . ($parent ? ' under "' . $parent->name . '"' : ''), $category);

        return back()->with('success', 'Category "' . $category->name . '" added.');
    }

    public function update(Request $request, StoreCategory $storeCategory)
    {
        $category = $this->own($storeCategory->id);
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
