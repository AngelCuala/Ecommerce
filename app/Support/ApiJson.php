<?php

namespace App\Support;

use App\Http\Controllers\OrderController;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\ParcelDelivery;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\Review;
use App\Models\ReturnRequest;
use App\Models\SellerApplication;
use App\Models\User;
use App\Models\UserAddress;
use App\Models\Voucher;
use App\Models\WishlistItem;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * What the app shows, worked out exactly as the web's Blade pages do (prices, sale and flash-sale
 * labels, which buttons appear on an order), so the app and the website always agree.
 */
class ApiJson
{
    public static function file(?string $path): ?string
    {
        return $path ? asset('storage/' . $path) : null;
    }

    public static function page(LengthAwarePaginator $p, callable $map): array
    {
        return [
            'data' => collect($p->items())->map($map)->values()->all(),
            'meta' => ['current_page' => $p->currentPage(), 'last_page' => $p->lastPage(), 'per_page' => $p->perPage(), 'total' => $p->total()],
        ];
    }

    // ── Products (components/product-card + products/show) ─────

    /** The product card: price after sale / flash sale, -% tag, rating, sold, wishlist heart. */
    /** @var array{key?: string, wished?: array, vacation?: array, prices?: \Illuminate\Support\Collection} */
    private static array $cards = [];

    public static function productCard(Product $product): array
    {
        // One query per request for the hearts, vacations and option prices, not one per card.
        $key = spl_object_id(request()) . ':' . (auth()->id() ?? 'guest');
        if ((self::$cards['key'] ?? null) !== $key) {
            self::$cards = [
                'key'      => $key,
                'wished'   => auth()->check() ? WishlistItem::where('user_id', auth()->id())->pluck('product_id')->all() : [],
                'vacation' => SellerApplication::where('is_on_vacation', true)->pluck('user_id')->all(),
                'prices'   => ProductVariation::where('price', '>', 0)->groupBy('product_id')
                    ->selectRaw('product_id, MIN(price) as min_price, MAX(price) as max_price')->get()->keyBy('product_id'),
            ];
        }
        ['wished' => $wished, 'vacation' => $vacation, 'prices' => $variantPrices] = self::$cards;

        $price = $product->effective_price;
        $hasDiscount = $product->hasDiscount() && $price < $product->price;
        $range = null;
        if (($vp = $variantPrices->get($product->id)) && (float) $vp->min_price != (float) $vp->max_price && ! $product->runningFlashSale()) {
            $range = [$product->priceFor((float) $vp->min_price), $product->priceFor((float) $vp->max_price)];
        }

        return [
            'id'               => $product->id,
            'slug'             => $product->slug,
            'title'            => $product->title,
            'image_url'        => self::file($product->image),
            'category'         => $product->relationLoaded('category') ? $product->category?->name : null,
            'price'            => $price,
            'original_price'   => $hasDiscount ? (float) $product->price : null,
            'discount_percent' => $hasDiscount ? (int) round(($product->price - $price) / $product->price * 100) : 0,
            'price_range'      => $range,
            'rating'           => $product->rating,
            'sold'             => (int) ($product->sold ?? 0),
            'out_of_stock'     => ($product->stock ?? 1) <= 0,
            'on_vacation'      => in_array($product->seller_id, $vacation),
            'wished'           => in_array($product->id, $wished),
            'seller_id'        => $product->seller_id,
        ];
    }

    /** Everything products/show.blade.php shows. */
    public static function productDetail(array $d): array
    {
        /** @var Product $product */
        $product = $d['product'];
        $inStock = $product->inStock();
        $hasDiscount = $product->hasDiscount() && $product->effective_price < $product->price;
        [$min, $max] = $product->priceRange();
        $flash = $d['flash'] ?? null;
        $upcoming = $d['upcoming'] ?? null;

        $images = [];
        if ($product->image) {
            $images[] = ['url' => self::file($product->image), 'label' => 'Cover'];
        }
        foreach ($product->images as $img) {
            $images[] = ['url' => $img->url(), 'label' => $img->label ?? ''];
        }

        $details = array_filter([
            'Product ID' => $product->product_code, 'Category' => $product->category->name ?? null, 'Subcategory' => $product->subcategory,
            'Brand' => $product->brand ?? $product->author, 'SKU' => $product->sku, 'Format' => $product->format, 'Language' => $product->language,
            'Publisher' => $product->publisher, 'Year' => $product->publication_year, 'ISBN' => $product->isbn, 'Edition' => $product->edition,
        ], fn ($v) => ! is_null($v) && $v !== '');

        $seller = $product->seller;

        return [
            'id'               => $product->id,
            'slug'             => $product->slug,
            'title'            => $product->title,
            'description'      => $product->description,
            'images'           => $images,
            'price'            => $product->effective_price,
            'original_price'   => $hasDiscount ? (float) $product->price : null,
            'discount_percent' => $hasDiscount && $product->price > 0 ? (int) round(100 - ($product->effective_price / $product->price * 100)) : 0,
            'price_range'      => $product->variations->isNotEmpty() && $min != $max ? [$min, $max] : null,
            'sale_ends_at'     => $hasDiscount && $product->sale_ends_at ? $product->sale_ends_at->toIso8601String() : null,
            'in_stock'         => $inStock,
            'stock'            => $product->totalStock(),
            'purchase_limit'   => $product->purchase_limit,
            'allowance'        => $d['allowance'] ?? null,
            'max_quantity'     => max(1, min($product->totalStock(), $d['allowance'] ?? PHP_INT_MAX)),
            'on_vacation'      => $product->sellerOnVacation(),
            'can_buy'          => $inStock && ! $product->sellerOnVacation(),
            'variation_groups' => $product->variationGroups(),
            'variation_labels' => $product->variationGroupLabels(),
            'variations'       => $product->variations->map(fn ($v) => [
                'id'    => $v->id,
                'name'  => $v->name,
                'parts' => array_map('trim', explode('/', $v->name)),
                'price' => $v->price > 0 ? $product->priceFor((float) $v->price) : $product->effective_price,
                'base'  => $v->price > 0 ? (float) $v->price : (float) $product->price,
                'stock' => (int) $v->stock,
                'image_url' => self::file($v->image_path),
            ])->values()->all(),
            'flash'    => $flash ? ['price' => round((float) $flash->flash_price, 2), 'ends_at' => $flash->ends_at?->toIso8601String(),
                'remaining' => $flash->remaining(), 'quantity' => (int) $flash->quantity, 'regular_price' => $product->regular_price] : null,
            'upcoming' => $upcoming ? ['id' => $upcoming->id, 'starts_at' => $upcoming->starts_at?->toIso8601String(),
                'price' => round((float) $upcoming->flash_price, 2), 'quantity' => (int) $upcoming->quantity, 'reminded' => (bool) ($d['reminded'] ?? false)] : null,
            'details'  => $details,
            'specs'    => is_array($product->specs) ? array_filter($product->specs, fn ($v) => $v !== null && $v !== '') : [],
            'rating'   => $product->rating_avg,
            'rating_count'     => $product->rating_count,
            'rating_breakdown' => collect(range(5, 1))->mapWithKeys(fn ($s) => [$s => (int) ($d['ratingBreakdown'][$s] ?? 0)])->all(),
            'sold'     => (int) ($d['sold'] ?? 0),
            'wished'   => (bool) ($d['wished'] ?? false),
            'seller'   => $seller ? ['id' => $seller->id, 'name' => $seller->storeName(), 'logo_url' => self::file($seller->sellerApplication?->logo_path)] : null,
            'vouchers' => collect($d['vouchers'] ?? [])->map(fn ($v) => self::voucher($v, in_array($v->id, $d['claimed'] ?? [])))->values()->all(),
            'reviews'  => self::page($d['reviews'], fn ($r) => self::review($r)),
            'related'  => collect($d['related'] ?? [])->map(fn ($p) => self::productCard($p))->values()->all(),
        ];
    }

    public static function review(Review $r): array
    {
        return [
            'id'          => $r->id,
            'rating'      => (int) $r->rating,
            'comment'     => $r->comment,
            'photos'      => array_map(fn ($p) => self::file($p), $r->photos()),
            'user'        => $r->relationLoaded('user') ? ($r->user?->name ?? 'Buyer') : null,
            'created_at'  => $r->created_at?->toIso8601String(),
            'edited'      => (bool) $r->edit_count,
            'can_edit'    => $r->canBeEdited(),
            'seller_reply' => $r->seller_reply ?? null,
        ];
    }

    public static function voucher(Voucher $v, ?bool $claimed = null): array
    {
        return [
            'id'          => $v->id,
            'code'        => $v->code,
            'name'        => $v->name,
            'scope'       => $v->scope,
            'label'       => $v->discountLabel(),
            'min_spend'   => (float) $v->min_spend,
            'ends_at'     => $v->ends_at?->toIso8601String(),
            'store'       => $v->seller_id ? ($v->relationLoaded('seller') ? $v->seller?->storeName() : null) : 'ALVY',
            'claimed'     => $claimed,
        ];
    }

    // ── Cart ─────────────────────────────────────────────────────

    public static function cartLine(CartItem $i): array
    {
        $p = $i->product;
        return [
            'id'           => $i->id,
            'product'      => $p ? ['id' => $p->id, 'slug' => $p->slug, 'title' => $p->title, 'image_url' => self::file($p->image), 'seller_id' => $p->seller_id] : null,
            'variation'    => $i->variation ? ['id' => $i->variation->id, 'name' => $i->variation->name] : null,
            'quantity'     => (int) $i->quantity,
            'unit_price'   => $p ? $i->unit_price : 0,
            'subtotal'     => $p ? $i->subtotal() : 0,
            'stock'        => $p ? $i->availableStock() : 0,
            'unavailable'  => $i->unavailableReason(),
        ];
    }

    // ── Orders (profile/orders + profile/order-show) ───────────

    public static function orderSummary(Order $o): array
    {
        return [
            'id'           => $o->id,
            'number'       => $o->number(),
            'status'       => $o->status,
            'status_label' => $o->buyerStatusLabel(),
            'tab'          => $o->tab(),
            'store'        => $o->seller?->storeName() ?? 'ALVY',
            'seller_id'    => $o->seller_id,
            'total'        => (float) $o->total_price,
            'created_at'   => $o->created_at?->toIso8601String(),
            'items'        => $o->items->map(fn ($i) => [
                'title' => $i->product->title ?? 'Removed product', 'variation' => $i->variation_name,
                'quantity' => (int) $i->quantity, 'price' => (float) $i->price, 'image_url' => self::file($i->product?->image),
            ])->values()->all(),
        ];
    }

    private const EVENT_LABELS = [
        'Pending' => 'Order placed', 'Processing' => 'Seller is preparing the order', 'Shipped' => 'Picked up — on the way',
        'Delivered' => 'Delivered', 'Completed' => 'Order completed', 'Cancelled' => 'Order cancelled',
        'Return Requested' => 'Return requested', 'Refunded' => 'Return approved — refunded', 'In Dispute' => 'Escalated — ALVY is reviewing the return',
    ];

    /** Everything the web's order page shows, and which of its buttons are there. */
    public static function orderDetail(Order $order): array
    {
        $return = $order->returnRequests->first();
        $delivery = ParcelDelivery::whereHas('parcel', fn ($q) => $q->where('order_id', $order->id))
            ->whereIn('status', ['out_for_delivery', 'delivered'])->with('rider')->latest('id')->first();
        $reviewOpen = $order->reviewWindowOpen();
        $returnOpen = $order->returnWindowOpen() && ! $order->returnRequests->where('status', 'requested')->count();

        return self::orderSummary($order) + [
            'payment' => [
                'merchandise'       => (float) $order->subtotal,
                'shipping'          => (float) $order->shipping_fee,
                'shipping_weight_kg' => $order->shipping_weight_kg ? (float) $order->shipping_weight_kg : null,
                'promo_discount'    => (float) $order->promo_discount,
                'store_voucher'     => (float) $order->seller_discount,
                'alvy_voucher'      => (float) $order->platform_discount,
                'alvy_voucher_code' => $order->platformVoucher?->code,
                'shipping_voucher'  => (float) $order->shipping_discount,
                'shipping_voucher_code' => $order->shippingVoucher?->code,
                'total'             => (float) $order->total_price,
                'method'            => $order->payment_method,
                'status'            => $order->payment_status,
                'refunded'          => (float) $order->refunded_amount,
                'pay_by'            => $order->pay_by?->toIso8601String(),
            ],
            'deliver_to' => ['name' => $order->full_name, 'phone' => $order->phone, 'address' => $order->fullAddress(), 'note' => $order->buyer_note],
            'order_items' => $order->items->map(fn ($item) => [
                'id'            => $item->id,
                'title'         => $item->product->title ?? 'Removed product',
                'slug'          => $item->product && ! $item->product->trashed() ? $item->product->slug : null,
                'variation'     => $item->variation_name,
                'price'         => (float) $item->price,
                'quantity'      => (int) $item->quantity,
                'total'         => round($item->price * $item->quantity, 2),
                'image_url'     => self::file($item->product?->image),
                'refunded'      => $item->refunded_at ? (float) $item->refund_amount : null,
                'review'        => $item->review ? self::review($item->review) : null,
                'can_review'    => ! $item->review && $reviewOpen,
            ])->values()->all(),
            'delivery' => $delivery ? [
                'status'       => $delivery->status,
                'rider'        => $delivery->rider->full_name ?? null,
                'otp'          => $delivery->status === 'out_for_delivery' ? $delivery->otp_code : null,
                'cod_amount'   => (float) $delivery->cod_amount,
                'delivered_at' => $delivery->delivered_at?->toIso8601String(),
                'proof_url'    => $delivery->status === 'delivered' ? self::file($delivery->pod_path) : null,
            ] : null,
            'timeline' => $order->events->map(fn ($e) => [
                'label' => self::EVENT_LABELS[$e->status] ?? $e->status, 'note' => $e->note, 'at' => $e->created_at?->toIso8601String(),
            ])->values()->all(),
            'return' => $return ? [
                'status' => $return->status, 'status_label' => $return->statusLabel(), 'reason' => $return->reason,
                'details' => $return->details, 'seller_note' => $return->seller_note, 'refund_amount' => (float) $return->refund_amount,
                'photos' => array_map(fn ($p) => self::file($p), $return->photos()),
            ] : null,
            'actions' => [
                'pay'             => $order->canBePaid(),
                'pay_label'       => $order->payment_status === 'Failed' ? 'Pay again' : 'Pay now',
                'confirm_received' => $order->canConfirmReceived(),
                'completes_on'    => $order->canConfirmReceived() ? $order->returnDeadline()?->toIso8601String() : null,
                'cancel'          => $order->isCancellableByBuyer(),
                'cancel_reasons'  => OrderController::CANCEL_REASONS,
                'cancel_blocked_note' => ! $order->isCancellableByBuyer() && $order->status === Order::PROCESSING && $order->isReadyForPickup()
                    ? 'The seller has booked the pickup, so this order can no longer be cancelled. Message the seller if you need help.' : null,
                'return'          => $returnOpen,
                'return_deadline' => $returnOpen ? $order->returnDeadline()?->toIso8601String() : null,
                'return_reasons'  => ReturnRequest::REASONS,
                'escalate'        => $return && $return->status === 'rejected' && $order->status === Order::DELIVERED,
                'message_seller'  => (bool) $order->seller_id,
            ],
        ];
    }

    // ── Account ──────────────────────────────────────────────────

    public static function address(UserAddress $a): array
    {
        return [
            'id' => $a->id, 'label' => $a->label, 'full_name' => $a->full_name, 'phone' => $a->phone,
            'address_line' => $a->address_line, 'barangay' => $a->barangay, 'barangay_code' => $a->barangay_code ?? null,
            'city' => $a->city, 'province' => $a->province, 'region' => $a->region, 'zip' => $a->zip,
            'municipality_code' => $a->municipality_code, 'province_code' => $a->province_code,
            'is_default' => (bool) $a->is_default, 'full_address' => $a->full_address,
        ];
    }

    public static function store(User $seller): array
    {
        $app = $seller->sellerApplication;
        return [
            'id' => $seller->id, 'name' => $seller->storeName(), 'description' => $app?->description,
            'logo_url' => self::file($app?->logo_path), 'banner_url' => self::file($app?->banner_path),
            'location' => collect([$app?->municipality, $app?->province])->filter()->implode(', ') ?: null,
            'on_vacation' => (bool) $app?->is_on_vacation,
        ];
    }
}
