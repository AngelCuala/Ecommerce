<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\UserAddress;
use App\Models\FlashSale;
use App\Services\OrderNotifier;
use App\Services\PaymentService;
use App\Services\ShippingService;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Checkout: the selected cart lines (or one "Buy now" item) are delivered to one of the
 * buyer's saved addresses. Each store becomes its own order with its own shipping fee
 * (see ShippingService); store, ALVY and shipping vouchers are applied by VoucherService.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private VoucherService $vouchers,
        private ShippingService $shipping,
    ) {}

    private function commissionRate(): float
    {
        return (float) config('marketplace.commission_rate', 10);
    }

    // ── What is being checked out ─────────────────────────────

    private function cartLines(): Collection
    {
        return CartItem::where('user_id', auth()->id())
            ->with(['product.seller.sellerApplication', 'product.variations', 'variation'])
            ->oldest()->get();
    }

    /** "Buy now": just that item (kept in the session), never the cart. Null when not Buy now. */
    private function buyNowLines(bool $requested): ?Collection
    {
        $buyNow = Session::get('buy_now');
        if (! $requested || ! $buyNow) {
            return null;
        }

        $product = Product::with(['seller.sellerApplication', 'variations'])->find($buyNow['product_id']);
        if (! $product) {
            Session::forget('buy_now');
            return collect();
        }

        // An unsaved cart line, so prices, vouchers and order lines work exactly as for the cart.
        $line = new CartItem([
            'user_id' => auth()->id(), 'product_id' => $product->id,
            'variation_id' => $buyNow['variation_id'] ?? null, 'quantity' => $buyNow['quantity'],
        ]);
        $line->id = 0;
        $line->setRelation('product', $product);
        $line->setRelation('variation', $line->variation_id ? $product->variations->firstWhere('id', $line->variation_id) : null);

        return collect([$line]);
    }

    /** The lines being checked out: the Buy now item, or the cart lines the buyer ticked. */
    private function checkoutLines(bool $buyNow): Collection
    {
        if (($lines = $this->buyNowLines($buyNow)) !== null) {
            return $lines;
        }

        // The lines ticked in the cart; without a selection (an old link to /checkout) the whole
        // cart, so anything unavailable in it is reported rather than silently skipped.
        $cart = $this->cartLines();
        $selected = Session::get('checkout_selection');

        return $selected === null ? $cart : $cart->whereIn('id', $selected)->values();
    }

    /** Lines that can't be bought right now (unpublished, deleted, sold out, too few left). */
    private function blockedLines(Collection $lines)
    {
        $blocked = $lines->filter(fn ($l) => $l->unavailableReason() !== null);
        if ($blocked->isEmpty()) {
            return null;
        }

        return redirect()->route('cart.index')->with('error',
            'Some items can\'t be checked out: '
            . $blocked->map(fn ($l) => ($l->product->title ?? 'Removed item') . ' (' . $l->unavailableReason() . ')')->implode(', ')
            . '. Please untick or update them.');
    }

    private function buyerAddress(Request $request): ?UserAddress
    {
        $user = auth()->user();
        $id = $request->input('address_id', Session::get('checkout_address'));

        return ($id ? $user->addresses()->find($id) : null) ?? $user->defaultAddress() ?? $user->addresses()->first();
    }

    /**
     * Everything the summary shows: per-store shipping, vouchers and totals.
     * Lenient mode drops vouchers that no longer apply and explains why in 'notices'.
     */
    private function summary(Collection $lines, ?UserAddress $address, array $codes, bool $strict): array
    {
        $groups = $this->vouchers->groupBySeller($lines);

        $unserviceable = $address
            ? $this->shipping->unserviceableReason($address->municipality_code, $address->province_code)
            : 'Please add a delivery address.';

        $shipping = $unserviceable ? [] : $this->shipping->quote($groups, $this->shipping->zoneOf($address->municipality_code ?: $address->province_code));
        $fees = array_map(fn ($s) => $s['fee'], $shipping);

        $quote = $this->vouchers->quote($lines, auth()->user(), $codes['platform'] ?? null, $codes['sellers'] ?? [],
            $codes['shipping'] ?? null, $fees, $strict);

        return ['groups' => $groups, 'shipping' => $shipping, 'quote' => $quote, 'unserviceable' => $unserviceable];
    }

    /** Current unit price of every line, to catch price changes while the buyer was checking out. */
    private function priceMap(Collection $lines): array
    {
        return $lines->mapWithKeys(fn ($l) => [(string) $l->id => number_format($l->unit_price, 2, '.', '')])->all();
    }

    // ── Pages ─────────────────────────────────────────────────

    public function index(Request $request)
    {
        $buyNow = $request->boolean('buy_now') && Session::has('buy_now');
        if (! $buyNow) {
            Session::forget('buy_now');
            if ($request->has('items')) {          // ticked in the cart
                Session::put('checkout_selection', array_map('intval', (array) $request->input('items')));
            }
        }

        $lines = $this->checkoutLines($buyNow);
        if ($lines->isEmpty()) {
            return redirect()->route('cart.index')->with('error', $buyNow ? 'That item is no longer available.' : 'Please tick the items you want to check out.');
        }
        if ($blocked = $this->blockedLines($lines)) {
            return $blocked;
        }

        $user    = auth()->user();
        $address = $this->buyerAddress($request);
        if ($address) {
            Session::put('checkout_address', $address->id);
        }

        // First visit: the cheapest vouchers. Coming back: what the buyer chose (dropped with a notice if it no longer applies).
        $fees = $address && ! $this->shipping->unserviceableReason($address->municipality_code, $address->province_code)
            ? array_sum(array_column($this->shipping->quote($this->vouchers->groupBySeller($lines),
                $this->shipping->zoneOf($address->municipality_code ?: $address->province_code)), 'fee'))
            : 0;
        $options = $this->vouchers->options($lines, $user, $fees);
        $best = [
            'platform' => $options['best']['platform'],
            'sellers'  => $options['best']['sellers'],
            'shipping' => $options['best']['shipping'],
        ];
        $codes = Session::get('checkout_vouchers') ?? $best;
        if ($request->has('items') && Session::has('checkout_vouchers')) {
            // New items ticked: keep the buyer's vouchers (one that no longer applies is dropped
            // with a notice below) and fill the empty slots with the best ones for the new items.
            $codes['platform'] = ($codes['platform'] ?? null) ?: $best['platform'];
            $codes['shipping'] = ($codes['shipping'] ?? null) ?: $best['shipping'];
            $codes['sellers']  = array_filter((array) ($codes['sellers'] ?? [])) + $best['sellers'];
        }

        $summary = $this->summary($lines, $address, $codes, false);
        $chosen  = [
            'platform' => $summary['quote']['platform_voucher']?->code,
            'shipping' => $summary['quote']['shipping_voucher']?->code,
            'sellers'  => array_map(fn ($v) => $v->code, $summary['quote']['seller_vouchers']),
        ];

        return view('checkout.index', [
            'lines'     => $lines,
            'summary'   => $summary,
            'vouchers'  => $options,
            'chosen'    => $chosen,
            'addresses' => $user->addresses()->orderByDesc('is_default')->oldest()->get(),
            'address'   => $address,
            'user'      => $user,
            'buyNow'    => $buyNow,
            'paymentMethods' => app(PaymentService::class)->methods(),
            'token'     => (string) Str::uuid(),
            'prices'    => $this->priceMap($lines),
        ]);
    }

    /** Live summary for the checkout page (address, vouchers or typed code changed). */
    public function preview(Request $request)
    {
        $request->validate([
            'address_id'        => 'nullable|integer',
            'platform_voucher'  => 'nullable|string|max:30',
            'shipping_voucher'  => 'nullable|string|max:30',
            'seller_vouchers'   => 'nullable|array',
            'seller_vouchers.*' => 'nullable|string|max:30',
            'voucher_code'      => 'nullable|string|max:30',
        ]);

        $buyNow = $request->boolean('buy_now') && Session::has('buy_now');
        $lines  = $this->checkoutLines($buyNow);
        $address = $this->buyerAddress($request);
        if ($address) {
            Session::put('checkout_address', $address->id);
        }

        $errors = [];
        try {
            [$platform, $sellers, $ship] = $this->vouchers->placeTypedCode($request->voucher_code, $request->platform_voucher,
                (array) $request->input('seller_vouchers', []), $request->shipping_voucher, $lines);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            [$platform, $sellers, $ship] = [$request->platform_voucher, (array) $request->input('seller_vouchers', []), $request->shipping_voucher];
        }

        $codes = ['platform' => $platform, 'sellers' => array_filter($sellers), 'shipping' => $ship];
        $summary = $this->summary($lines, $address, $codes, false);
        $q = $summary['quote'];

        Session::put('checkout_vouchers', [
            'platform' => $q['platform_voucher']?->code,
            'sellers'  => array_map(fn ($v) => $v->code, $q['seller_vouchers']),
            'shipping' => $q['shipping_voucher']?->code,
        ]);

        return response()->json($this->summaryJson($summary) + [
            'errors'  => array_map(fn ($m) => $m[0], $errors),
            'applied' => [
                'platform' => $q['platform_voucher']?->code,
                'shipping' => $q['shipping_voucher']?->code,
                'sellers'  => array_map(fn ($v) => $v->code, $q['seller_vouchers']),
            ],
        ]);
    }

    private function summaryJson(array $summary): array
    {
        $q = $summary['quote'];
        return [
            'unserviceable' => $summary['unserviceable'],
            'stores'  => collect($summary['groups'])->map(fn ($g, $sellerId) => [
                'subtotal'   => $g['subtotal'],
                'shipping'   => $summary['shipping'][$sellerId]['fee'] ?? null,
                'weight'     => $summary['shipping'][$sellerId]['weight'] ?? null,
                'chargeable' => $summary['shipping'][$sellerId]['chargeable'] ?? null,
                'discount'   => $q['seller_discounts'][$sellerId] ?? 0,
                'promo'      => $g['promo'] ?? 0,
            ])->all(),
            'subtotal'          => $q['merchandise'],
            'promo_discount'    => $q['promo_discount'],
            'shipping_total'    => $q['shipping_total'],
            'seller_discount'   => $q['seller_discount'],
            'platform_discount' => $q['platform_discount'],
            'shipping_discount' => $q['shipping_discount'],
            'total'             => $q['total'],
            'notices'           => array_values($q['notices']),
        ];
    }

    // ── Place the order ───────────────────────────────────────

    public function store(Request $request)
    {
        $request->validate([
            'address_id'        => 'required|integer',
            'payment_method'    => ['required', 'in:' . implode(',', array_merge(['cod'], array_keys(app(PaymentService::class)->methods())))],
            'checkout_token'    => 'required|uuid',
            'prices'            => 'nullable|array',
            'buyer_notes'       => 'nullable|array',
            'buyer_notes.*'     => 'nullable|string|max:200',
            'platform_voucher'  => 'nullable|string|max:30',
            'shipping_voucher'  => 'nullable|string|max:30',
            'seller_vouchers'   => 'nullable|array',
            'seller_vouchers.*' => 'nullable|string|max:30',
            'voucher_code'      => 'nullable|string|max:30',
        ], [
            'payment_method.required' => 'Please choose a payment method.',
            'address_id.required'     => 'Please choose a delivery address.',
            'buyer_notes.*.max'       => 'A note to the seller can be at most 200 characters.',
        ]);

        $user    = auth()->user();
        $address = $user->addresses()->find($request->address_id);
        if (! $address) {
            return back()->with('error', 'Please choose one of your saved addresses.');
        }

        // Already placed with this page's token (double click, Back and resubmit): show the orders.
        $tokenKey = 'checkout-token:' . $request->checkout_token;
        if (Cache::has($tokenKey)) {
            return redirect()->route('profile.orders')->with('success', 'Your order was already placed.');
        }

        $buyNow = $request->boolean('buy_now') && Session::has('buy_now');
        $lines  = $this->checkoutLines($buyNow);
        if ($lines->isEmpty()) {
            return redirect()->route('cart.index')->with('error', $buyNow ? 'That item is no longer available.' : 'Your cart is empty.');
        }
        if ($blocked = $this->blockedLines($lines)) {
            return $blocked;
        }

        // A price changed while the buyer was on the checkout page: show it, don't charge it silently.
        $changes = [];
        foreach ($lines as $line) {
            $seen = $request->input('prices.' . $line->id);
            if ($seen !== null && abs((float) $seen - $line->unit_price) >= 0.005) {
                $changes[] = $line->product->title . ' is now ₱' . number_format($line->unit_price, 2) . ' (was ₱' . number_format((float) $seen, 2) . ')';
            }
        }
        if ($changes) {
            return redirect()->route('checkout.index', $buyNow ? ['buy_now' => 1] : [])
                ->with('error', 'Prices changed: ' . implode('; ', $changes) . '. Please check the new total and place your order again.');
        }

        [$platform, $sellers, $ship] = $this->vouchers->placeTypedCode($request->voucher_code, $request->platform_voucher,
            (array) $request->input('seller_vouchers', []), $request->shipping_voucher, $lines);

        $summary = $this->summary($lines, $address, ['platform' => $platform, 'sellers' => array_filter($sellers), 'shipping' => $ship], true);
        if ($summary['unserviceable']) {
            return back()->with('error', $summary['unserviceable']);
        }

        $quote = $summary['quote'];
        $group = (string) Str::uuid();

        // Only the first click with this page's token places orders. The token is taken here, after every
        // check, so a blocked attempt (price, voucher, stock) can be fixed and placed again.
        if (! Cache::add($tokenKey, $user->id, now()->addHour())) {
            return redirect()->route('profile.orders')->with('success', 'Your order was already placed.');
        }

        try {
            $orders = DB::transaction(fn () => $this->createOrders($lines, $summary, $address, $request, $group, $request->payment_method));
        } catch (\App\Exceptions\CheckoutProblem $e) {   // ran out of stock / over a purchase limit while checking out
            Cache::forget($tokenKey);
            return redirect()->route($buyNow ? 'checkout.index' : 'cart.index', $buyNow ? ['buy_now' => 1] : [])->with('error', $e->getMessage());
        }

        $notifier = app(OrderNotifier::class);
        foreach ($orders as $order) {
            $notifier->placed($order);
        }

        \App\Models\ActivityLog::note(
            count($orders) . ' order(s) ' . collect($orders)->map->number()->implode(', ')
            . ' · ' . $lines->sum('quantity') . ' item(s): ' . $lines->map(fn ($i) => $i->product->title . ($i->variation ? " ({$i->variation->name})" : '') . ' ×' . $i->quantity)->implode(', ')
            . ' · total ₱' . number_format($quote['total'], 2)
            . (($codes = array_filter([$quote['platform_voucher']?->code, $quote['shipping_voucher']?->code, ...array_map(fn ($v) => $v->code, $quote['seller_vouchers'])]))
                ? ' · vouchers ' . implode(', ', $codes) : '')
            . ($buyNow ? ' · Buy now' : ''), reset($orders));

        // Buy now leaves the cart alone; a cart checkout removes only the lines that were bought.
        if ($buyNow) {
            Session::forget('buy_now');
        } else {
            CartItem::where('user_id', $user->id)->whereIn('id', $lines->pluck('id'))->delete();
        }
        Session::forget(['checkout_selection', 'checkout_vouchers']);
        Session::put('last_checkout_group', $group);

        // E-wallet / card: straight to the payment page. The orders wait in To Pay until it is paid.
        if ($request->payment_method !== 'cod') {
            try {
                $payment = app(PaymentService::class)->start($user, $group, $request->payment_method);
                return redirect()->away($payment->checkout_url);
            } catch (\Throwable $e) {
                report($e);
                return redirect()->route('profile.orders.show', reset($orders)->id)
                    ->with('error', 'Your order is saved, but the payment page could not open. Please press Pay now.');
            }
        }

        return redirect()->route('checkout.confirmation');
    }

    /** @return array<int, Order> one order per seller */
    private function createOrders(Collection $lines, array $summary, UserAddress $address, Request $request, string $group, string $method = 'cod'): array
    {
        $online = $method !== 'cod';
        $quote = $summary['quote'];
        $user  = auth()->user();

        // Lock the stock rows, then check them again: another buyer may have just bought the last one.
        $products   = Product::whereIn('id', $lines->pluck('product_id'))->lockForUpdate()->get()->keyBy('id');
        $variations = ProductVariation::whereIn('id', $lines->pluck('variation_id')->filter())->lockForUpdate()->get()->keyBy('id');
        foreach ($lines as $line) {
            $stock = $line->variation_id ? ($variations[$line->variation_id]->stock ?? 0) : ($products[$line->product_id]->stock ?? 0);
            if ($line->quantity > $stock) {
                throw new \App\Exceptions\CheckoutProblem($stock > 0
                    ? 'Only ' . $stock . ' of "' . $line->product->title . '" left. Please lower the quantity.'
                    : '"' . $line->product->title . '" just sold out.');
            }
        }
        // Flash-sale units are limited too: the flash price holds only while enough are left.
        $flashUse = [];
        foreach ($lines as $line) {
            if ($flash = $line->flashSale()) {
                $locked = FlashSale::whereKey($flash->id)->lockForUpdate()->first();
                if ($locked->remaining() < $line->flashUnits()) {
                    throw new \App\Exceptions\CheckoutProblem('The flash sale for "' . $line->product->title . '" just sold out, so the price is back to ₱'
                        . number_format($line->product->regular_price, 2) . '. Please check your order again.');
                }
                $flashUse[$line->id] = $locked;
            }
        }
        foreach ($lines->groupBy('product_id') as $productId => $productLines) {
            $product = $productLines->first()->product;
            $left = $product->remainingAllowanceFor($user);
            if ($left !== null && $productLines->sum('quantity') > $left) {
                throw new \App\Exceptions\CheckoutProblem('"' . $product->title . '" is limited to ' . $product->purchase_limit . ' per buyer'
                    . ($left > 0 ? " — you can buy {$left} more." : ' and you have reached it.'));
            }
        }

        $shares         = $this->vouchers->lineShares($quote);
        $shippingShares = $this->vouchers->shippingShares($quote);
        $rate           = $this->commissionRate();
        $notes          = (array) $request->input('buyer_notes', []);

        $orders = [];
        foreach ($quote['groups'] as $sellerId => $storeGroup) {
            $fee       = $summary['shipping'][$sellerId]['fee'] ?? 0;
            $shipOff   = $shippingShares[$sellerId] ?? 0;
            $storeOff  = $quote['seller_discounts'][$sellerId] ?? 0;
            $alvyOff   = round($storeGroup['items']->sum(fn ($l) => $shares[$l->id]['platform'] ?? 0), 2);
            $subtotal  = $storeGroup['gross'];
            $promoOff  = $storeGroup['promo'];

            $order = Order::create([
                'user_id'           => $user->id,
                'address_id'        => $address->id,
                'checkout_group'    => $group,
                'seller_id'         => $sellerId ?: null,
                'full_name'         => $address->full_name,
                'phone'             => $address->phone,
                'email'             => $user->email,
                'address_line'      => $address->full_address,
                'barangay'          => $address->barangay,
                'barangay_code'     => $address->barangay_code,
                'city'              => $address->city,
                'province'          => $address->province,
                'region'            => $address->region,
                'municipality_code' => $address->municipality_code,
                'province_code'     => $address->province_code,
                'zip_code'          => $address->zip,
                'shipping_address'  => $address->full_address,
                'buyer_note'        => trim((string) ($notes[$sellerId] ?? '')) ?: null,
                'subtotal'          => $subtotal,
                'shipping_fee'      => $fee,
                'shipping_weight_kg'=> $summary['shipping'][$sellerId]['weight'] ?? null,
                'seller_discount'   => $storeOff,
                'promo_discount'    => $promoOff,
                'platform_voucher_id' => $alvyOff > 0 ? $quote['platform_voucher']?->id : null,
                'platform_discount' => $alvyOff,
                'shipping_voucher_id' => $shipOff > 0 ? $quote['shipping_voucher']?->id : null,
                'shipping_discount' => $shipOff,
                'total_price'       => round(max(0, $subtotal - $promoOff - $storeOff - $alvyOff) + max(0, $fee - $shipOff), 2),
                'payment_method'    => strtoupper($method),
                'payment_status'    => $online ? 'Unpaid' : 'Pending',
                'pay_by'            => $online ? now()->addMinutes((int) config('payments.unpaid_minutes', 60)) : null,
                'status'            => $online ? Order::AWAITING_PAYMENT : Order::PENDING,
            ]);

            $orderItems = [];
            foreach ($storeGroup['items'] as $line) {
                $share = $shares[$line->id] ?? ['seller' => 0.0, 'platform' => 0.0, 'promo' => 0.0];
                $parts = $line->priceParts();
                $lineTotal = $line->subtotal();

                // Part flash, part regular price: one order item for each, so the order shows
                // exactly what was charged. Discounts are shared in proportion to each part.
                $left = ['seller' => $share['seller'], 'platform' => $share['platform'], 'promo' => $share['promo'] ?? 0.0];
                foreach ($parts as $n => [$qty, $unit, $isFlash]) {
                    $partTotal = round($qty * $unit, 2);
                    $last = $n === count($parts) - 1;
                    $part = [];
                    foreach ($left as $k => $amount) {
                        $part[$k] = $last ? round($amount, 2) : round(($share[$k] ?? 0) * ($lineTotal > 0 ? $partTotal / $lineTotal : 0), 2);
                    }
                    foreach ($part as $k => $amount) {
                        $left[$k] -= $amount;
                    }

                    // Seller-funded discounts (promotion, store voucher) come out of the seller's
                    // sale; commission is on what was actually charged. ALVY vouchers are paid by ALVY.
                    $charged    = round($partTotal - $part['promo'] - $part['seller'], 2);
                    $commission = round($charged * ($rate / 100), 2);

                    $orderItems[] = OrderItem::create([
                        'order_id'          => $order->id,
                        'product_id'        => $line->product_id,
                        'variation_id'      => $line->variation_id,
                        'variation_name'    => $line->variation?->name,
                        'flash_sale_id'     => $isFlash && isset($flashUse[$line->id]) ? $flashUse[$line->id]->id : null,
                        'quantity'          => $qty,
                        'price'             => $unit,
                        'promo_discount'    => $part['promo'],
                        'seller_discount'   => $part['seller'],
                        'platform_discount' => $part['platform'],
                        'commission_rate'   => $rate,
                        'commission_amount' => $commission,
                        'seller_earning'    => round($charged - $commission, 2),
                    ]);
                }

                if (isset($flashUse[$line->id])) {
                    $flashUse[$line->id]->increment('sold', $line->flashUnits());
                }
                if ($line->variation_id) {
                    $before = (int) $variations[$line->variation_id]->stock;
                    $variations[$line->variation_id]->decrement('stock', $line->quantity);
                    Product::whereKey($line->product_id)->update(['stock' => ProductVariation::where('product_id', $line->product_id)->sum('stock')]);
                    \App\Services\LowStockAlert::check($line->product, $before, $before - $line->quantity, $variations[$line->variation_id]);
                } else {
                    $before = (int) $products[$line->product_id]->stock;
                    Product::whereKey($line->product_id)->decrement('stock', $line->quantity);
                    \App\Services\LowStockAlert::check($line->product, $before, $before - $line->quantity);
                }
            }

            $this->roundCommissionPerOrder($orderItems, $rate);
            $orders[$sellerId] = $order;
        }

        // Count the voucher uses; fails (and rolls everything back) if one just ran out.
        $this->vouchers->redeem($orders, $user, $quote);

        return $orders;
    }

    /**
     * A9: commission is rate × the order's charged item total, rounded once per order (half up).
     * Each line was rounded on its own, so any centavo left over goes to the order's largest line;
     * that line's seller earning moves with it.
     *
     * @param  array<int, OrderItem>  $items
     */
    private function roundCommissionPerOrder(array $items, float $rate): void
    {
        if (! $items) {
            return;
        }
        $charged = fn (OrderItem $i) => round((float) $i->price * $i->quantity - (float) $i->promo_discount - (float) $i->seller_discount, 2);
        $total = round(array_sum(array_map($charged, $items)), 2);
        $expected = round($total * $rate / 100 + 1e-9, 2);   // half up, safe from float error (e.g. 20.8905)
        $diff = round($expected - array_sum(array_map(fn ($i) => (float) $i->commission_amount, $items)), 2);
        if (abs($diff) < 0.005) {
            return;
        }

        $largest = collect($items)->sortByDesc($charged)->first();
        $commission = round((float) $largest->commission_amount + $diff, 2);
        $largest->forceFill(['commission_amount' => $commission, 'seller_earning' => round($charged($largest) - $commission, 2)])->save();
    }

    public function confirmation()
    {
        $group  = Session::pull('last_checkout_group');
        $orders = $group
            ? Order::with('items.product', 'seller.sellerApplication')->where('user_id', auth()->id())->where('checkout_group', $group)->get()
            : Order::with('items.product', 'seller.sellerApplication')->where('user_id', auth()->id())->latest()->take(1)->get();

        if ($orders->isEmpty()) {
            return redirect()->route('home');
        }

        return view('checkout.confirmation', compact('orders'));
    }
}
