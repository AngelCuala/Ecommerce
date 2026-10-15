<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\FlashSale;
use App\Models\Product;
use Illuminate\Http\Request;

/** A seller's flash sales: one of their products at a flash price, for a time slot and a limited quantity. */
class FlashSaleController extends Controller
{
    public function index()
    {
        $sales = FlashSale::where('seller_id', auth()->id())->with('product')->withCount('reminders')
            ->orderByDesc('starts_at')->paginate(20);

        // Products that can have a flash sale: live, in stock, without options.
        $products = Product::published()->where('seller_id', auth()->id())->where('stock', '>', 0)
            ->whereDoesntHave('variations')->orderBy('title')->get(['id', 'title', 'price', 'sale_price', 'discount_percent', 'sale_ends_at', 'stock']);

        return view('seller.flash-sales.index', compact('sales', 'products'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id'  => 'required|integer',
            'flash_price' => 'required|numeric|min:1|max:1000000',
            'quantity'    => 'required|integer|min:1|max:99999',
            'starts_at'   => 'required|date|after:now',
            'ends_at'     => 'required|date|after:starts_at',
        ], [
            'starts_at.after' => 'The flash sale must start in the future.',
            'ends_at.after'   => 'The flash sale must end after it starts.',
        ]);

        $product = Product::published()->where('seller_id', auth()->id())->with('variations')->find($data['product_id']);
        if (! $product || $product->variations->isNotEmpty()) {
            return back()->withInput()->withErrors(['product_id' => 'Choose one of your live products without options.']);
        }
        if ($data['flash_price'] >= $product->regular_price) {
            return back()->withInput()->withErrors(['flash_price' => 'The flash price must be lower than the current price (₱' . number_format($product->regular_price, 2) . ').']);
        }
        if ($data['quantity'] > $product->stock) {
            return back()->withInput()->withErrors(['quantity' => 'Only ' . $product->stock . ' in stock.']);
        }
        $overlaps = FlashSale::where('product_id', $product->id)
            ->where('starts_at', '<', $data['ends_at'])->where('ends_at', '>', $data['starts_at'])->exists();
        if ($overlaps) {
            return back()->withInput()->withErrors(['starts_at' => 'This product already has a flash sale in that time.']);
        }

        FlashSale::create($data + ['seller_id' => auth()->id()]);
        \App\Models\ActivityLog::note('Flash sale for "' . $product->title . '" at ₱' . number_format($data['flash_price'], 2), $product);

        return back()->with('success', 'Flash sale scheduled for "' . $product->title . '".');
    }

    public function destroy(FlashSale $flashSale)
    {
        abort_unless((int) $flashSale->seller_id === (int) auth()->id(), 404);
        if ($flashSale->sold > 0) {
            return back()->with('error', 'Units were already sold in this flash sale, so it can\'t be deleted. It ends on its own.');
        }
        $flashSale->delete();

        return back()->with('success', 'Flash sale removed.');
    }
}
