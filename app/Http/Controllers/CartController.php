<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\CartItem;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index()
    {
        $items = CartItem::where('user_id', auth()->id())
            ->with('book.category')
            ->get();

        $subtotal = $items->sum(fn ($i) => $i->quantity * $i->book->price);

        return view('cart.index', compact('items', 'subtotal'));
    }

    public function store(Request $request, int $bookId)
    {
        $book = Book::findOrFail($bookId);

        if (! $book->inStock()) {
            return back()->with('error', 'This item is out of stock.');
        }

        $request->validate(['quantity' => 'nullable|integer|min:1|max:99']);
        $qty = max(1, (int) $request->input('quantity', 1));

        $item = CartItem::firstOrNew([
            'user_id' => auth()->id(),
            'book_id' => $book->id,
        ]);

        // Increment if already in cart, otherwise set the requested qty
        $item->quantity = $item->exists ? $item->quantity + $qty : $qty;
        $item->save();

        return back()->with('success', '"' . $book->title . '" added to your cart.');
    }

    public function update(Request $request, int $cartItemId)
    {
        $request->validate(['quantity' => 'required|integer|min:1|max:99']);

        $item = CartItem::where('id', $cartItemId)
            ->where('user_id', auth()->id())
            ->firstOrFail();

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
}
