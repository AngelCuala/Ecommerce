<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Seller-funded promotions applied at checkout:
 * bundle deals ("any 3 items from these products, 15% off") and buy X get Y ("buy 2, get 1 free").
 */
class PromotionController extends Controller
{
    public function index()
    {
        $promotions = Promotion::where('seller_id', auth()->id())->with('freeProduct')->latest()->get();
        $products   = Product::where('seller_id', auth()->id())->where('status', '!=', 'draft')->orderBy('title')->get(['id', 'title']);

        return view('seller.promotions.index', compact('promotions', 'products'));
    }

    public function store(Request $request)
    {
        $own = Rule::exists('products', 'id')->where('seller_id', auth()->id())->whereNull('deleted_at');
        $data = $request->validate([
            'name'             => 'required|string|max:120',
            'type'             => ['required', Rule::in([Promotion::BUNDLE, Promotion::BXGY])],
            'product_ids'      => 'required|array|min:1',
            'product_ids.*'    => ['integer', $own],
            'min_qty'          => 'required|integer|min:' . ($request->input('type') === Promotion::BUNDLE ? 2 : 1) . '|max:999',
            'discount_percent' => 'required_if:type,' . Promotion::BUNDLE . '|nullable|numeric|min:1|max:99',
            'free_product_id'  => ['required_if:type,' . Promotion::BXGY, 'nullable', 'integer', $own],
            'free_qty'         => 'nullable|integer|min:1|max:99',
            'starts_at'        => 'nullable|date',
            'ends_at'          => array_filter(['nullable', 'date', 'after:now', $request->filled('starts_at') ? 'after:starts_at' : null]),
        ], [
            'product_ids.required'         => 'Choose the products this promotion is for.',
            'min_qty.min'                  => $request->input('type') === Promotion::BUNDLE ? 'A bundle needs at least 2 items.' : 'Buyers must buy at least 1.',
            'discount_percent.required_if' => 'Enter the bundle discount (1% to 99%).',
            'discount_percent.min'         => 'The bundle discount must be from 1% to 99%.',
            'discount_percent.max'         => 'The bundle discount must be from 1% to 99%.',
            'free_product_id.required_if'  => 'Choose the free product.',
            'ends_at.after'                => 'The end must be in the future and after the start.',
        ]);

        $promotion = Promotion::create([
            'seller_id'        => auth()->id(),
            'name'             => $data['name'],
            'type'             => $data['type'],
            'product_ids'      => array_values(array_unique(array_map('intval', $data['product_ids']))),
            'min_qty'          => $data['min_qty'],
            'discount_percent' => $data['type'] === Promotion::BUNDLE ? $data['discount_percent'] : null,
            'free_product_id'  => $data['type'] === Promotion::BXGY ? $data['free_product_id'] : null,
            'free_qty'         => $data['type'] === Promotion::BXGY ? ($data['free_qty'] ?? 1) : 1,
            'starts_at'        => $data['starts_at'] ?? null,
            'ends_at'          => $data['ends_at'] ?? null,
        ]);
        \App\Models\ActivityLog::note('Promotion "' . $promotion->name . '" · ' . $promotion->label(), $promotion);

        return back()->with('success', 'Promotion "' . $promotion->name . '" saved. It applies automatically at checkout.');
    }

    public function toggle(Promotion $promotion)
    {
        $this->authorizeOwn($promotion);
        $promotion->update(['is_active' => ! $promotion->is_active]);

        return back()->with('success', 'Promotion "' . $promotion->name . '" turned ' . ($promotion->is_active ? 'on' : 'off') . '.');
    }

    public function destroy(Promotion $promotion)
    {
        $this->authorizeOwn($promotion);
        $promotion->delete();   // orders already placed keep the discount they got

        return back()->with('success', 'Promotion deleted.');
    }

    private function authorizeOwn(Promotion $promotion): void
    {
        abort_unless((int) $promotion->seller_id === (int) auth()->id(), 404);
    }
}
