<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class CheckoutController extends Controller
{
    private function commissionRate(): float
    {
        return (float) config('marketplace.commission_rate', 10);
    }

    private function cartItems()
    {
        return CartItem::where('user_id', auth()->id())->with('book')->get();
    }

    private function calcTotals($items): array
    {
        $subtotal = $items->sum(fn ($i) => $i->quantity * $i->book->price);
        $shipping = 50; // fixed ₱50 delivery fee
        $total    = round($subtotal + $shipping, 2);
        return compact('subtotal', 'shipping', 'total');
    }

    // ── Step 1: show checkout form ───────────────────────────
    public function index()
    {
        $items = $this->cartItems();
        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        ['subtotal'=>$subtotal,'shipping'=>$shipping,'total'=>$total] = $this->calcTotals($items);

        $user = auth()->user();

        return view('checkout.index', compact(
            'items', 'subtotal', 'shipping', 'total', 'user'
        ));
    }

    // ── Step 2a: COD / Card — place order ───────────────────
    public function store(Request $request)
    {
        $request->validate([
            'full_name'      => 'required|string|max:255',
            'phone'          => 'required|string|max:30',
            'email'          => 'required|email|max:255',
            'region'         => 'required|string|max:120',
            'province'       => 'required|string|max:120',
            'city'           => 'required|string|max:120',
            'barangay'       => 'required|string|max:120',
            'house_number'   => 'nullable|string|max:100',
            'street'         => 'nullable|string|max:255',
            'zip_code'       => 'required|string|max:20',
            'payment_method' => 'required|in:cod',
        ]);

        $items = $this->cartItems();
        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        ['subtotal'=>$subtotal,'shipping'=>$shipping,'total'=>$total] = $this->calcTotals($items);

        $isCOD          = true;
        $commissionRate = $this->commissionRate();

        $addressLine = trim(implode(', ', array_filter([
            $request->house_number,
            $request->street,
            $request->barangay,
            $request->city,
            $request->province,
            $request->region,
        ])));

        $order = Order::create([
            'user_id'          => auth()->id(),
            'full_name'        => $request->full_name,
            'phone'            => $request->phone,
            'email'            => $request->email,
            'address_line'     => $addressLine,
            'city'             => $request->city,
            'province'         => $request->province,
            'zip_code'         => $request->zip_code,
            'shipping_address' => $addressLine . ' ' . $request->zip_code,
            'subtotal'         => $subtotal,
            'shipping_fee'     => $shipping,
            'total_price'      => $total,
            'payment_method'   => 'COD',
            'payment_status'   => 'Pending',
            'status'           => 'Pending',
        ]);

        foreach ($items as $item) {
            $lineTotal        = round($item->quantity * $item->book->price, 2);
            $commissionAmount = round($lineTotal * ($commissionRate / 100), 2);
            $sellerEarning    = round($lineTotal - $commissionAmount, 2);

            OrderItem::create([
                'order_id'          => $order->id,
                'book_id'           => $item->book_id,
                'quantity'          => $item->quantity,
                'price'             => $item->book->price,
                'commission_rate'   => $commissionRate,
                'commission_amount' => $commissionAmount,
                'seller_earning'    => $sellerEarning,
            ]);

            // Reduce stock
            Book::where('id', $item->book_id)->decrement('stock', $item->quantity);
        }

        // Clear cart
        CartItem::where('user_id', auth()->id())->delete();

        // Pass order id via session for confirmation page
        Session::put('last_order_id', $order->id);

        return redirect()->route('checkout.confirmation');
    }

    // ── Step 3: confirmation page ────────────────────────────
    public function confirmation()
    {
        $orderId = Session::pull('last_order_id');

        $order = $orderId
            ? Order::with('items.book')->find($orderId)
            : Order::where('user_id', auth()->id())->with('items.book')->latest()->first();

        if (! $order) {
            return redirect()->route('home');
        }

        return view('checkout.confirmation', compact('order'));
    }
}
