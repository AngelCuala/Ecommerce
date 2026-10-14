<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class CheckoutController extends Controller
{
    private function commissionRate(): float
    {
        return (float) config('marketplace.commission_rate', 10);
    }

    private function cartItems()
    {
        return CartItem::where('user_id', auth()->id())
            ->with('product.seller.sellerApplication')
            ->get();
    }

    /**
     * "Buy now" checks out just that one item (kept in the session), not the cart.
     * Returns null when this isn't a Buy now checkout.
     */
    private function buyNowItems(bool $requested)
    {
        $buyNow = Session::get('buy_now');
        if (! $requested || ! $buyNow) {
            return null;
        }

        $product = Product::with('seller.sellerApplication')->find($buyNow['product_id']);
        if (! $product) {
            Session::forget('buy_now');
            return collect();
        }

        // An unsaved cart line, so totals, vouchers and order lines work exactly as for the cart.
        $line = new CartItem(['user_id' => auth()->id(), 'product_id' => $product->id, 'quantity' => $buyNow['quantity']]);
        $line->id = 0;
        $line->setRelation('product', $product);

        return collect([$line]);
    }

    /** The items being checked out: the Buy now item, or else the whole cart. */
    private function checkoutItems(bool $buyNowRequested)
    {
        return $this->buyNowItems($buyNowRequested) ?? $this->cartItems();
    }

    /** Items that were unpublished (or never approved) after being added to the cart. */
    private function unavailableItems($items)
    {
        $unavailable = $items->filter(fn ($i) => ! $i->product || ! $i->product->isPublished());
        if ($unavailable->isEmpty()) {
            return null;
        }

        return redirect()->route('cart.index')->with('error',
            'Some items in your cart are no longer available: '
            . $unavailable->map(fn ($i) => $i->product->title ?? 'removed item')->implode(', ')
            . '. Please remove them to continue.');
    }

    private function calcTotals($items): array
    {
        $subtotal = $items->sum(fn ($i) => $i->quantity * $i->product->effective_price);
        $shipping = 50; // fixed ₱50 delivery fee
        $total    = round($subtotal + $shipping, 2);
        return compact('subtotal', 'shipping', 'total');
    }

    // ── Step 1: show checkout form ───────────────────────────
    public function index(Request $request)
    {
        $buyNow = $request->boolean('buy_now') && Session::has('buy_now');
        if (! $buyNow) {
            Session::forget('buy_now');   // a normal checkout of the cart
        }

        $items = $this->checkoutItems($buyNow);
        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', $buyNow ? 'That item is no longer available.' : 'Your cart is empty.');
        }
        if ($blocked = $this->unavailableItems($items)) {
            return $blocked;
        }

        ['subtotal'=>$subtotal,'shipping'=>$shipping,'total'=>$total] = $this->calcTotals($items);

        $user = auth()->user();

        // Vouchers the buyer can pick (store vouchers per seller in the cart, and ALVY vouchers).
        $vouchers = app(VoucherService::class)->options($items, $user);

        return view('checkout.index', compact(
            'items', 'subtotal', 'shipping', 'total', 'user', 'vouchers', 'buyNow'
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
            // Official PSGC codes from the address dropdowns (hidden inputs) — used for delivery routing.
            'province_code'     => ['nullable', 'regex:/^\d{9,10}$/'],
            'municipality_code' => ['nullable', 'regex:/^\d{9,10}$/'],
            'platform_voucher'  => 'nullable|string|max:30',
            'seller_vouchers'   => 'nullable|array',
            'seller_vouchers.*' => 'nullable|string|max:30',
        ]);

        $buyNow = $request->boolean('buy_now') && Session::has('buy_now');
        $items  = $this->checkoutItems($buyNow);
        if ($items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', $buyNow ? 'That item is no longer available.' : 'Your cart is empty.');
        }

        if ($blocked = $this->unavailableItems($items)) {
            return $blocked;
        }

        // Don't sell more than is in stock.
        foreach ($items as $item) {
            if ($item->quantity > $item->product->stock) {
                return back()->withInput()->with('error', 'Only ' . $item->product->stock . ' of "' . $item->product->title . '" left in stock.');
            }
        }

        $vouchers = app(VoucherService::class);
        // Checked before anything is saved; a bad code sends the buyer back with the reason.
        $quote = $vouchers->quote($items, auth()->user(), $request->platform_voucher, (array) $request->input('seller_vouchers', []));

        ['subtotal'=>$subtotal,'shipping'=>$shipping] = $this->calcTotals($items);
        $total = round($subtotal - $quote['seller_discount'] - $quote['platform_discount'] + $shipping, 2);

        $commissionRate = $this->commissionRate();

        $addressLine = trim(implode(', ', array_filter([
            $request->house_number,
            $request->street,
            $request->barangay,
            $request->city,
            $request->province,
            $request->region,
        ])));

        $order = DB::transaction(function () use ($request, $items, $quote, $vouchers, $subtotal, $shipping, $total, $commissionRate, $addressLine) {
            $order = Order::create([
                'user_id'          => auth()->id(),
                'full_name'        => $request->full_name,
                'phone'            => $request->phone,
                'email'            => $request->email,
                'address_line'     => $addressLine,
                'city'             => $request->city,
                'province'         => $request->province,
                'municipality_code'=> $request->municipality_code ?: null,
                'province_code'    => $request->province_code ?: null,
                'zip_code'         => $request->zip_code,
                'shipping_address' => $addressLine . ' ' . $request->zip_code,
                'subtotal'         => $subtotal,
                'shipping_fee'     => $shipping,
                'total_price'      => $total,
                'platform_voucher_id' => $quote['platform_voucher']?->id,
                'platform_discount'   => $quote['platform_discount'],
                'seller_discount'     => $quote['seller_discount'],
                'payment_method'   => 'COD',
                'payment_status'   => 'Pending',
                'status'           => 'Pending',
            ]);

            $shares = $vouchers->lineShares($quote);

            foreach ($items as $item) {
                $lineTotal = round($item->quantity * $item->product->effective_price, 2);
                $share     = $shares[$item->id] ?? ['seller' => 0.0, 'platform' => 0.0];

                // A store voucher comes out of the seller's sale (commission is on what was
                // actually charged for the item); an ALVY voucher is paid by ALVY, so the
                // seller's earning is unchanged by it.
                $charged          = round($lineTotal - $share['seller'], 2);
                $commissionAmount = round($charged * ($commissionRate / 100), 2);
                $sellerEarning    = round($charged - $commissionAmount, 2);

                OrderItem::create([
                    'order_id'          => $order->id,
                    'product_id'        => $item->product_id,
                    'quantity'          => $item->quantity,
                    'price'             => $item->product->effective_price,
                    'seller_discount'   => $share['seller'],
                    'platform_discount' => $share['platform'],
                    'commission_rate'   => $commissionRate,
                    'commission_amount' => $commissionAmount,
                    'seller_earning'    => $sellerEarning,
                ]);

                // Reduce stock
                Product::where('id', $item->product_id)->decrement('stock', $item->quantity);
            }

            // Count the voucher uses; fails (and rolls everything back) if one just ran out.
            $vouchers->redeem($order, auth()->user(), $quote);

            return $order;
        });

        // Buy now leaves the cart as it was; a cart checkout empties it.
        if ($buyNow) {
            Session::forget('buy_now');
        } else {
            CartItem::where('user_id', auth()->id())->delete();
        }

        // Pass order id via session for confirmation page
        Session::put('last_order_id', $order->id);

        return redirect()->route('checkout.confirmation');
    }

    // ── Step 3: confirmation page ────────────────────────────
    public function confirmation()
    {
        $orderId = Session::pull('last_order_id');

        $order = $orderId
            ? Order::with('items.product')->find($orderId)
            : Order::where('user_id', auth()->id())->with('items.product')->latest()->first();

        if (! $order) {
            return redirect()->route('home');
        }

        return view('checkout.confirmation', compact('order'));
    }
}
