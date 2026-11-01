<?php

namespace App\Http\Controllers\Api\V1\Buyer;

use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Models\OrderItem;
use App\Models\Review;
use App\Support\ApiJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Cart, checkout and My Purchases (B4–B10, B12–B13) through the web's own controllers. */
class ShoppingController extends Controller
{
    use CallsWeb;

    // ── Cart ─────────────────────────────────────────────────────

    public function cart(): JsonResponse
    {
        $d = $this->pageData($this->web(CartController::class, 'index'));

        return response()->json([
            'stores' => $d['stores']->map(fn ($lines, $sellerId) => [
                'seller_id' => $sellerId > 0 ? $sellerId : null,
                'name'      => $sellerId > 0 ? ($lines->first()->product?->seller?->storeName() ?? 'Seller') : 'Unavailable',
                'on_vacation' => $sellerId > 0 && (bool) $lines->first()->product?->sellerOnVacation(),
                'lines'     => $lines->map(fn ($i) => ApiJson::cartLine($i))->values(),
            ])->values(),
            'count'  => $d['items']->count(),
        ]);
    }

    /** Add to cart (quantity, variation_id) or "Buy now" (action=buy_now: then open checkout with buy_now=1). */
    public function addToCart(int $productId): JsonResponse
    {
        return $this->actionJson($this->web(CartController::class, 'store', ['bookId' => $productId]));
    }

    public function updateCart(int $cartItemId): JsonResponse
    {
        return $this->actionJson($this->web(CartController::class, 'update', ['cartItemId' => $cartItemId]));
    }

    public function removeFromCart(int $cartItemId): JsonResponse
    {
        return $this->actionJson($this->web(CartController::class, 'destroy', ['cartItemId' => $cartItemId]));
    }

    // ── Checkout ─────────────────────────────────────────────────

    /** The checkout page for the ticked cart lines (items[]=id…) or the Buy now item (buy_now=1). */
    public function checkout(): JsonResponse
    {
        $d = $this->pageData($this->web(CheckoutController::class, 'index'));
        $q = $d['summary']['quote'];

        return response()->json(self::summary($d['summary']) + [
            'buy_now'   => $d['buyNow'],
            'lines'     => $d['lines']->map(fn ($l) => ApiJson::cartLine($l) + ['seller_id' => (int) ($l->product->seller_id ?? 0)])->values(),
            'vouchers'  => $d['vouchers'],
            'chosen'    => $d['chosen'],
            'addresses' => $d['addresses']->map(fn ($a) => ApiJson::address($a))->values(),
            'address_id' => $d['address']?->id,
            'payment_methods' => collect($d['paymentMethods'])->map(fn ($m, $key) => ['key' => $key] + (is_array($m) ? $m : ['label' => $m]))->values(),
            'checkout_token'  => $d['token'],
            // Pay by GCash and upload the receipt (payment_method=gcash_receipt, payment_proof, payment_reference).
            'gcash_account'   => $d['gcashAccount'] ?? null,
            'prices'    => $d['prices'],
            'platform_voucher' => $q['platform_voucher']?->code,
            'shipping_voucher' => $q['shipping_voucher']?->code,
        ]);
    }

    /** Live totals when the address or a voucher changes (the web's own JSON). */
    public function preview(): JsonResponse
    {
        return $this->actionJson($this->web(CheckoutController::class, 'preview'));
    }

    /** Place the order. COD: {orders}. E-wallet / card: {payment_url} to open. */
    public function placeOrder(): JsonResponse
    {
        $response = $this->web(CheckoutController::class, 'store');

        if ($response instanceof RedirectResponse && ! str_starts_with($response->getTargetUrl(), url('/'))) {
            return $this->actionJson($response, ['payment_url' => $response->getTargetUrl(), 'orders' => $this->lastCheckout()]);
        }
        if ($response instanceof RedirectResponse && str_contains($response->getTargetUrl(), '/checkout/confirmation')) {
            return $this->actionJson($response, ['orders' => $this->lastCheckout()]);
        }

        return $this->actionJson($response, ['orders' => []]);
    }

    private function lastCheckout(): array
    {
        $group = session('last_checkout_group');
        if (! $group) {
            return [];
        }

        return \App\Models\Order::with(['items.product', 'seller.sellerApplication'])->where('user_id', auth()->id())
            ->where('checkout_group', $group)->get()->map(fn ($o) => ApiJson::orderSummary($o))->values()->all();
    }

    /** The checkout summary, as the web's preview answers it. */
    public static function summary(array $summary): array
    {
        $q = $summary['quote'];
        return [
            'unserviceable' => $summary['unserviceable'],
            'stores' => collect($summary['groups'])->map(fn ($g, $sellerId) => [
                'seller_id'  => $sellerId ?: null,
                'name'       => $g['name'],
                'subtotal'   => $g['subtotal'],
                'shipping'   => $summary['shipping'][$sellerId]['fee'] ?? null,
                'weight'     => $summary['shipping'][$sellerId]['weight'] ?? null,
                'chargeable' => $summary['shipping'][$sellerId]['chargeable'] ?? null,
                'route'      => $summary['shipping'][$sellerId]['route'] ?? null,   // "Same city", "Luzon → Visayas"…
                'discount'   => $q['seller_discounts'][$sellerId] ?? 0,
                'promo'      => $g['promo'] ?? 0,
                'promo_labels' => $g['promo_labels'] ?? [],
            ])->values()->all(),
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

    // ── My Purchases ─────────────────────────────────────────────

    /** tab: all, to_pay, to_ship, to_receive, completed, cancelled, return_refund. */
    public function orders(): JsonResponse
    {
        $d = $this->pageData($this->web(ProfileController::class, 'orders'));

        return response()->json(ApiJson::page($d['orders'], fn ($o) => ApiJson::orderSummary($o)) + [
            'tab'    => $d['tab'],
            'tabs'   => collect(\App\Models\Order::TABS)->reject(fn ($t, $key) => $key === 'return_refund' && ! \App\Models\Order::returnsEnabled() && ! ($d['counts'][$key] ?? 0))->map(fn ($t, $key) => ['key' => $key, 'label' => $t['label'], 'count' => $d['counts'][$key] ?? 0])->values(),
        ]);
    }

    public function order(int $id): JsonResponse
    {
        $d = $this->pageData($this->web(ProfileController::class, 'orderShow', ['id' => $id]));

        return response()->json(['order' => ApiJson::orderDetail($d['order'])]);
    }

    public function cancel(int $id): JsonResponse
    {
        return $this->afterOrderAction($id, $this->web(OrderController::class, 'cancelByBuyer', ['id' => $id]));
    }

    public function confirmReceived(int $id): JsonResponse
    {
        return $this->afterOrderAction($id, $this->web(OrderController::class, 'confirmDelivery', ['id' => $id]));
    }

    /** reason, details, items[] (to return only some), evidence_photos[] (1–5). */
    public function requestReturn(int $id): JsonResponse
    {
        return $this->afterOrderAction($id, $this->web(OrderController::class, 'requestReturn', ['id' => $id]));
    }

    public function escalate(int $id): JsonResponse
    {
        return $this->afterOrderAction($id, $this->web(OrderController::class, 'escalateReturn', ['id' => $id]));
    }

    /** Pay an e-wallet / card order: {payment_url} to open. */
    public function pay(int $id): JsonResponse
    {
        $response = $this->web(PaymentController::class, 'pay', ['orderId' => $id]);
        if ($response instanceof RedirectResponse && ! str_starts_with($response->getTargetUrl(), url('/'))) {
            return $this->actionJson($response, ['payment_url' => $response->getTargetUrl()]);
        }

        return $this->actionJson($response);
    }

    /** rating (1–5), comment, photos[] (up to 5). */
    public function review(OrderItem $item): JsonResponse
    {
        return $this->afterOrderAction($item->order_id, $this->web(ReviewController::class, 'store', ['item' => $item]));
    }

    public function editReview(Review $review): JsonResponse
    {
        return $this->afterOrderAction($review->order_id, $this->web(ReviewController::class, 'update', ['review' => $review]));
    }

    /** An order action's message, plus the order as it is now (when it is the buyer's). */
    private function afterOrderAction(?int $orderId, mixed $response): JsonResponse
    {
        $json = $this->actionJson($response);
        if ($json->getStatusCode() === 200 && $orderId) {
            $order = auth()->user()->orders()->with(['items.product', 'items.review', 'events', 'seller.sellerApplication', 'returnRequests', 'platformVoucher', 'shippingVoucher'])->find($orderId);
            if ($order) {
                $json->setData($json->getData(true) + ['order' => ApiJson::orderDetail($order)]);
            }
        }

        return $json;
    }
}
