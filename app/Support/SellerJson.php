<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;

/**
 * The seller panel's pages as the app shows them, worked out like the web's seller Blade views
 * (seller.books.index, seller.orders.show, …).
 */
class SellerJson
{
    /** A row of the Inventory list. */
    public static function productRow(Product $p): array
    {
        return [
            'id'              => $p->id,
            'slug'            => $p->slug,
            'product_code'    => $p->product_code,
            'title'           => $p->title,
            'image_url'       => ApiJson::file($p->image),
            'category'        => $p->category?->name,
            'price'           => (float) $p->price,
            'effective_price' => $p->effective_price,
            'stock'           => (int) $p->stock,
            'sku'             => $p->sku,
            'status'          => $p->status,
            'approval_status' => $p->approval_status,
            'approval_note'   => $p->approval_note,
            'is_archived'     => (bool) $p->is_archived,
            'sale_state'      => $p->saleState(),
            'low_stock'       => $p->isLowStock(),
            'low_stock_at'    => $p->lowStockThreshold(),   // this product's alert level, else the store default
            'sold'            => isset($p->sold) ? (int) $p->sold : null,
            'variations_count' => $p->relationLoaded('variations') ? $p->variations->count() : null,
        ];
    }

    /** Everything the product form needs to edit one product. */
    public static function productForm(Product $p): array
    {
        $p->loadMissing(['images', 'variations', 'storeCategories']);

        return self::productRow($p) + [
            'category_id'      => $p->category_id,
            'subcategory'      => $p->subcategory,
            'brand'            => $p->brand,
            'condition'        => $p->condition,
            'description'      => $p->description,
            'sale_price'       => $p->sale_price !== null ? (float) $p->sale_price : null,
            'discount_percent' => (float) $p->discount_percent > 0 ? (int) $p->discount_percent : null,
            'sale_starts_at'   => $p->sale_starts_at ? \App\Support\VoucherRules::forInput($p->sale_starts_at) : null,
            'sale_ends_at'     => $p->sale_ends_at ? \App\Support\VoucherRules::forInput($p->sale_ends_at) : null,
            'purchase_limit'   => $p->purchase_limit,
            'low_stock_threshold' => $p->low_stock_threshold,   // empty = the store default
            'variation_labels' => $p->variation_labels,
            'weight_kg'        => $p->weight_kg !== null ? (float) $p->weight_kg : null,
            'length_cm'        => $p->length_cm !== null ? (float) $p->length_cm : null,
            'width_cm'         => $p->width_cm !== null ? (float) $p->width_cm : null,
            'height_cm'        => $p->height_cm !== null ? (float) $p->height_cm : null,
            'address_id'       => $p->address_id,
            'specs'            => is_array($p->specs) ? (object) $p->specs : (object) [],
            'video_url'        => ApiJson::file($p->video_path),
            'images'           => $p->images->map(fn ($i) => ['id' => $i->id, 'url' => $i->url(), 'label' => $i->label])->values(),
            'variations'       => $p->variations->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'price' => $v->price !== null ? (float) $v->price : null,
                'stock' => (int) $v->stock, 'sku' => $v->sku, 'image_url' => ApiJson::file($v->image_path)])->values(),
            'store_category_ids' => $p->storeCategories->pluck('id')->values(),
        ];
    }

    public static function orderRow(Order $o): array
    {
        $items = $o->sellerItems ?? $o->items;
        return [
            'id'           => $o->id,
            'number'       => $o->number(),
            'status'       => $o->status,
            'seller_step'  => $o->sellerStep(),
            'buyer'        => $o->full_name,
            'created_at'   => $o->created_at?->toIso8601String(),
            'total'        => (float) $o->total_price,
            'payment'      => $o->payment_method . ' · ' . $o->payment_status,
            'items'        => $items->map(fn ($i) => [
                'title' => $i->product->title ?? 'Removed product', 'variation' => $i->variation_name,
                'quantity' => (int) $i->quantity, 'price' => (float) $i->price, 'image_url' => ApiJson::file($i->product?->image),
            ])->values(),
        ];
    }

    /** The web's seller order page (seller.orders.show): figures, steps and which actions it offers. */
    public static function orderDetail(array $d): array
    {
        /** @var Order $order */
        $order = $d['order'];
        $parcel = $d['parcel'];
        $delivery = $order->delivery;
        $items = $order->sellerItems;
        $step = $order->sellerStep();
        $openReturn = $order->returnRequests->firstWhere('status', 'requested');
        $lastReturn = $order->returnRequests->first();
        $storeVoucher = $order->voucherRedemptions()->with('voucher')->get()->first(fn ($r) => $r->voucher && $r->voucher->scope === 'seller');
        $awaitingReceipt = $parcel && $parcel->status === 'returned' && $parcel->returned_at && ! $parcel->returned_received_at;

        $canBook = in_array($step, ['packed', 'ready'], true)
            && ((! $delivery && ! $parcel)
                || ($delivery && $delivery->status === 'available' && ! $delivery->pickup_scheduled_at)
                || ($parcel && $parcel->status === 'pickup_rejected'));

        return self::orderRow($order) + [
            'cancellation_reason' => $order->cancellation_reason,
            'figures' => [
                'gross'          => round($items->sum(fn ($i) => $i->price * $i->quantity), 2),
                'promo'          => round($items->sum('promo_discount'), 2),
                'store_voucher'  => round($items->sum('seller_discount'), 2),
                'store_voucher_code' => $storeVoucher?->voucher?->code,
                'alvy_voucher'   => (float) $order->platform_discount,
                'shipping_fee'   => (float) $order->shipping_fee,
                'shipping_discount' => (float) $order->shipping_discount,
                'commission'     => round($items->sum('commission_amount'), 2),
                'earnings'       => round($items->sum('seller_earning'), 2),
                'collect_cod'    => $order->payment_method === 'COD' && $order->payment_status !== 'Paid' ? (float) $order->total_price : null,
            ],
            'order_items' => $items->map(fn ($i) => [
                'id' => $i->id, 'title' => $i->product->title ?? 'Removed product', 'variation' => $i->variation_name,
                'price' => (float) $i->price, 'quantity' => (int) $i->quantity, 'image_url' => ApiJson::file($i->product?->image),
                'refunded' => $i->refunded_at ? (float) $i->refund_amount : null,
            ])->values(),
            // The website's Shipment tracking card, and GCash paid to ALVY (an admin confirmed it before the seller saw the order).
            'tracking' => ApiJson::tracking($order),
            'payment' => ['method' => $order->paymentMethodLabel(), 'status' => $order->payment_status],
            'gcash' => $order->isGcashReceipt() ? [
                'has_receipt' => (bool) $order->payment_proof_path, 'reference' => $order->payment_reference,
                'amount' => (float) $order->total_price, 'checking' => $order->payment_status === 'For verification',
            ] : null,
            'waybill_number' => $parcel?->waybill_number,
            'ship_to' => ['name' => $order->full_name, 'phone' => $order->phone, 'address' => $order->fullAddress(), 'note' => $order->buyer_note],
            'timeline' => $order->events->map(fn ($e) => ['status' => $e->status, 'note' => $e->note, 'at' => $e->created_at?->toIso8601String()])->values(),
            'return' => $lastReturn ? [
                'id' => $lastReturn->id, 'status' => $lastReturn->status, 'reason' => $lastReturn->reason, 'details' => $lastReturn->details,
                'refund_amount' => (float) $lastReturn->refund_amount, 'seller_note' => $lastReturn->seller_note,
                'photos' => array_map(fn ($p) => ApiJson::file($p), $lastReturn->photos()),
                'answer_by' => $lastReturn->status === 'requested' ? $lastReturn->created_at->addDays(\App\Models\ReturnRequest::RESPOND_DAYS)->toIso8601String() : null,
                'escalated_at' => $lastReturn->escalated_at?->toIso8601String(), 'dispute_note' => $lastReturn->dispute_note,
            ] : null,
            'parcel' => $parcel ? [
                'tracking_number' => $parcel->tracking_number, 'status' => $parcel->status, 'status_label' => $parcel->statusLabel(),
                'sorting_center' => $parcel->currentSortingCenter->name ?? null,
                'failure_reason' => $parcel->failure_reason && in_array($parcel->status, ['pickup_rejected', 'failed', 'returned']) ? $parcel->failure_reason : null,
                'returned_at' => $parcel->returned_at?->toIso8601String(),
            ] : null,
            'delivery' => $delivery ? ['status' => $delivery->status, 'tracking_number' => $delivery->tracking_number, 'notes' => $delivery->notes,
                'pickup_scheduled_at' => $delivery->pickup_scheduled_at?->toIso8601String()] : null,
            'sorting_centers' => $canBook ? collect($d['sortingCenters'])->map(fn ($sc) => [
                'id' => $sc->id, 'name' => $sc->display_name, 'municipality' => $sc->assigned_municipality,
                'covers_seller' => (bool) $sc->covers_seller, 'rider_count' => (int) $sc->rider_count,
            ])->values() : [],
            'actions' => [
                'accept'          => in_array($order->status, ['Pending', 'Processing'], true) && $step === 'new',
                'pack'            => in_array($order->status, ['Pending', 'Processing'], true) && $step === 'accepted',
                'book_pickup'     => ! in_array($order->status, Order::LOGISTICS_DONE, true) && $canBook,
                'waybill'         => $order->canPrintWaybill(),
                'accept_label'    => 'Accept order',
                'cancel'          => in_array($order->status, ['Pending', 'Processing'], true) && $order->isCancellableBySeller(),
                'cancel_reasons'  => Order::SELLER_CANCEL_REASONS,
                'handed_over'     => ! in_array($order->status, Order::LOGISTICS_DONE, true) && $delivery?->status === 'accepted',
                'decide_return'   => (bool) $openReturn,
                'returned_received' => (bool) ($parcel && ($parcel->status === 'return_to_sender' || $awaitingReceipt)),
                'returned_label'  => $awaitingReceipt ? 'Returned, awaiting confirmation' : ($parcel?->status === 'return_to_sender' ? 'Return to Sender' : null),
            ],
        ];
    }
}
