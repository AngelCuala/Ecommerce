<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index()
    {
        $items = CartItem::where('user_id', auth()->id())
            ->with(['product.category', 'product.variations', 'product.seller.sellerApplication', 'variation'])
            ->oldest()->get();

        // Grouped by store; lines whose product was deleted go under "Unavailable".
        $stores = $items->groupBy(fn ($i) => $i->product ? (int) $i->product->seller_id : -1);

        return view('cart.index', compact('items', 'stores'));
    }

    /** Add to cart, or "Buy now" (straight to checkout with just this item). */
    public function store(Request $request, int $bookId)
    {
        $product = Product::with('variations')->findOrFail($bookId);

        if (! $product->isPublished()) {
            return back()->with('error', 'This item is not available right now.');
        }

        $request->validate([
            'quantity'     => 'nullable|integer|min:1|max:99999',
            'variation_id' => 'nullable|integer',
        ], [
            'quantity.integer' => 'Please enter a whole number for the quantity.',
            'quantity.min'     => 'The quantity must be at least 1.',
        ]);

        // A product with options needs every option chosen.
        $variation = null;
        if ($product->variations->isNotEmpty()) {
            $variation = $product->variations->firstWhere('id', (int) $request->input('variation_id'));
            if (! $variation) {
                return back()->withInput()->with('error', 'Please select a variation.');
            }
        }

        $stock = $variation ? (int) $variation->stock : $product->totalStock();
        if ($stock <= 0 || ! $product->inStock()) {
            return back()->withInput()->with('error', $variation ? '"' . $variation->name . '" is out of stock.' : 'This item is out of stock.');
        }

        $qty = (int) $request->input('quantity', 1);

        $line = CartItem::firstOrNew([
            'user_id' => auth()->id(), 'product_id' => $product->id, 'variation_id' => $variation?->id,
        ]);
        $inCart = $request->input('action') === 'buy_now' || ! $line->exists ? 0 : $line->quantity;

        if ($error = $this->quantityProblem($product, $qty + $inCart, $stock, $inCart)) {
            return back()->withInput()->with('error', $error);
        }

        $label = '"' . $product->title . '"' . ($variation ? ' (' . $variation->name . ')' : '');

        // "Buy now": straight to checkout with just this item; the cart is left alone.
        if ($request->input('action') === 'buy_now') {
            session()->put('buy_now', ['product_id' => $product->id, 'variation_id' => $variation?->id, 'quantity' => $qty]);
            \App\Models\ActivityLog::note('Buy now: ' . $label . ' ×' . $qty, $product);

            return redirect()->route('checkout.index', ['buy_now' => 1]);
        }

        // The same product and option merges into one line.
        $line->quantity = $inCart + $qty;
        $line->save();
        \App\Models\ActivityLog::note($label . ' ×' . $qty . ' (now ' . $line->quantity . ' in cart)', $product);

        return back()->with('success', $label . ' added to your cart.');
    }

    public function update(Request $request, int $cartItemId)
    {
        $request->validate(['quantity' => 'required|integer|min:1|max:99999'], [
            'quantity.min' => 'The quantity must be at least 1.',
        ]);

        $item = CartItem::with(['product.variations', 'variation'])
            ->where('id', $cartItemId)->where('user_id', auth()->id())->firstOrFail();

        if (! $item->product) {
            return back()->with('error', 'This item is no longer available. Please remove it.');
        }
        if ($error = $this->quantityProblem($item->product, (int) $request->quantity, $item->availableStock())) {
            return back()->with('error', $error);
        }

        $item->update(['quantity' => $request->quantity]);

        return back()->with('success', 'Cart updated.');
    }

    public function destroy(int $cartItemId)
    {
        CartItem::where('id', $cartItemId)
            ->where('user_id', auth()->id())
            ->firstOrFail()
            ->delete();

        return back()->with('success', 'Item removed from cart.');
    }

    /** Why this many units can't be bought (stock, or the per-buyer limit), or null. */
    private function quantityProblem(Product $product, int $qty, int $stock, int $alreadyInCart = 0): ?string
    {
        if ($qty > $stock) {
            return 'Only ' . $stock . ' left in stock' . ($alreadyInCart ? " (you already have {$alreadyInCart} in your cart)" : '') . '.';
        }

        $left = $product->remainingAllowanceFor(auth()->user());
        if ($left !== null && $qty > $left) {
            return '"' . $product->title . '" is limited to ' . $product->purchase_limit . ' per buyer'
                . ($left > 0 ? ' — you can add ' . max(0, $left - $alreadyInCart) . ' more.' : ', and you have already bought that many.');
        }

        return null;
    }
}
