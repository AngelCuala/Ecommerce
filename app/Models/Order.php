<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One order = one store's items from a checkout (a checkout with two stores makes two orders
 * sharing a checkout_group).
 *
 * Status flow (buyer tab in brackets):
 *   Awaiting Payment [To Pay] → (paid online) Pending, or Cancelled when not paid in time
 *   Pending → Processing [To Ship] → Shipped → Delivered [To Receive] → Completed [Completed]
 *   Pending / Processing → Cancelled [Cancelled]
 *   Delivered → Return Requested → Refunded, or back to Delivered if the seller rejects [Return/Refund]
 */
class Order extends Model
{
    public const AWAITING_PAYMENT = 'Awaiting Payment';
    public const PENDING          = 'Pending';
    public const PROCESSING       = 'Processing';
    public const SHIPPED          = 'Shipped';
    public const DELIVERED        = 'Delivered';
    public const COMPLETED        = 'Completed';
    public const CANCELLED        = 'Cancelled';
    public const RETURN_REQUESTED = 'Return Requested';
    public const REFUNDED         = 'Refunded';
    public const IN_DISPUTE       = 'In Dispute';

    /** Orders that don't count as sales (and don't use up a per-buyer purchase limit). */
    public const VOID_STATUSES = [self::CANCELLED, self::REFUNDED];

    /** Not (yet) a sale: void, or an online order nobody has paid for yet. */
    public const NOT_SALES = [self::CANCELLED, self::REFUNDED, self::AWAITING_PAYMENT];

    /** Logistics is finished with these orders (nothing more to pick up or deliver). */
    public const LOGISTICS_DONE = [self::CANCELLED, self::DELIVERED, self::COMPLETED, self::RETURN_REQUESTED, self::REFUNDED, self::IN_DISPUTE];

    /** Seller panel tabs => statuses (the seller's own steps: New → Accepted → Packed → Ready for Pickup). */
    public const SELLER_TABS = [
        'new'           => ['label' => 'New',           'statuses' => [self::PENDING]],
        'to_ship'       => ['label' => 'To Ship',       'statuses' => [self::PROCESSING]],
        'shipping'      => ['label' => 'Shipping',      'statuses' => [self::SHIPPED, self::DELIVERED]],
        'completed'     => ['label' => 'Completed',     'statuses' => [self::COMPLETED]],
        'cancelled'     => ['label' => 'Cancelled',     'statuses' => [self::CANCELLED]],
        'return_refund' => ['label' => 'Return/Refund', 'statuses' => [self::RETURN_REQUESTED, self::REFUNDED, self::IN_DISPUTE]],
    ];

    public const SELLER_CANCEL_REASONS = ['Out of stock', 'Item damaged before shipping', 'Wrong price listed', 'Cannot ship to this address', 'Buyer asked to cancel'];

    /** Days after delivery the buyer can ask for a return; after that the order completes by itself. */
    public const RETURN_DAYS = 7;

    /** My Purchases tabs => statuses. */
    public const TABS = [
        'to_pay'        => ['label' => 'To Pay',        'statuses' => [self::AWAITING_PAYMENT]],
        'to_ship'       => ['label' => 'To Ship',       'statuses' => [self::PENDING, self::PROCESSING]],
        'to_receive'    => ['label' => 'To Receive',    'statuses' => [self::SHIPPED, self::DELIVERED]],
        'completed'     => ['label' => 'Completed',     'statuses' => [self::COMPLETED]],
        'cancelled'     => ['label' => 'Cancelled',     'statuses' => [self::CANCELLED]],
        'return_refund' => ['label' => 'Return/Refund', 'statuses' => [self::RETURN_REQUESTED, self::REFUNDED, self::IN_DISPUTE]],
    ];

    protected $fillable = [
        'user_id', 'address_id', 'checkout_group', 'seller_id',
        'full_name', 'phone', 'email',
        'address_line', 'barangay', 'barangay_code', 'city', 'province', 'region', 'zip_code',
        'municipality_code', 'province_code', // PSGC codes of the delivery address (used for routing)
        'shipping_address', 'buyer_note',
        'subtotal', 'shipping_fee', 'shipping_weight_kg', 'total_price',
        'platform_voucher_id', 'platform_discount', 'seller_discount',
        'shipping_voucher_id', 'shipping_discount',
        'payment_method', 'payment_status', 'pay_by', 'refunded_amount',
        'status', 'cancellation_reason',
        'delivered_at', 'completed_at', 'cancelled_at',
        'accepted_at', 'packed_at', 'promo_discount',
    ];

    protected $casts = [
        'subtotal'     => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'total_price'  => 'decimal:2',
        'platform_discount' => 'decimal:2',
        'seller_discount'   => 'decimal:2',
        'shipping_discount' => 'decimal:2',
        'shipping_weight_kg'=> 'decimal:2',
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'pay_by'       => 'datetime',
        'accepted_at'  => 'datetime',
        'packed_at'    => 'datetime',
        'promo_discount' => 'decimal:2',
        'refunded_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            $order->events()->create(['status' => $order->status ?: self::PENDING, 'note' => 'Order placed', 'created_at' => now()]);
        });

        static::updating(function (Order $order) {
            if (! $order->isDirty('status')) {
                return;
            }
            match ($order->status) {
                self::DELIVERED => $order->delivered_at ??= now(),
                self::COMPLETED => $order->completed_at ??= now(),
                self::CANCELLED => $order->cancelled_at ??= now(),
                default         => null,
            };
        });

        static::updated(function (Order $order) {
            if (! $order->wasChanged('status')) {
                return;
            }
            $from = $order->getOriginal('status');

            $order->events()->create([
                'status'     => $order->status,
                'note'       => $order->status === self::CANCELLED ? $order->cancellation_reason : null,
                'created_at' => now(),
            ]);

            // However an order gets cancelled (buyer, seller, returned parcel, admin…),
            // its stock and vouchers come back — once.
            if ($order->status === self::CANCELLED && $from !== self::CANCELLED) {
                $order->closeOpenPickups();
                $order->restock();
                app(\App\Services\VoucherService::class)->release($order);
                // Paid online: the money goes back.
                if ($order->isPaidOnline()) {
                    app(\App\Services\PaymentService::class)->refund($order);
                }
            }
            // An approved return on an order paid online is refunded through the gateway too.
            if ($order->status === self::REFUNDED && $order->isPaidOnline()) {
                app(\App\Services\PaymentService::class)->refund($order, 'Return approved');
            }

            app(\App\Services\OrderNotifier::class)->statusChanged($order, $from);
        });
    }

    /** The order was cancelled before pickup: close its pickup requests, rider pickups and offers, and tell the hub. */
    public function closeOpenPickups(): void
    {
        $svc = app(\App\Services\ParcelService::class);
        foreach (Parcel::where('order_id', $this->id)->whereIn('status', ['pending_pickup', 'pickup_approved'])->get() as $parcel) {
            $parcel->update(['status' => 'cancelled', 'failure_reason' => 'Order was cancelled.']);
            ParcelPickup::where('parcel_id', $parcel->id)->where('status', 'assigned')->update(['status' => 'cancelled', 'remarks' => 'Order cancelled.']);
            RiderJobOffer::where('parcel_id', $parcel->id)->where('status', 'pending')->update(['status' => 'cancelled']);
            if ($parcel->current_sorting_center_id) {
                $svc->notify((int) $parcel->current_sorting_center_id, 'Pickup cancelled',
                    "{$parcel->tracking_number}: order " . $this->number() . ' was cancelled. No pickup needed.', 'warning');
            }
        }
    }

    /** Put the order's units back on the shelf (and on their variants and flash sales). */
    public function restock(): void
    {
        foreach ($this->items()->get() as $item) {
            if ($item->flash_sale_id) {
                FlashSale::whereKey($item->flash_sale_id)->where('sold', '>=', $item->quantity)->decrement('sold', $item->quantity);
                Product::forgetFlashSales();
            }
            if ($item->variation_id && ($variation = ProductVariation::find($item->variation_id))) {
                $variation->increment('stock', $item->quantity);
                Product::withTrashed()->whereKey($item->product_id)
                    ->update(['stock' => ProductVariation::where('product_id', $item->product_id)->sum('stock')]);
            } else {
                Product::withTrashed()->whereKey($item->product_id)->increment('stock', $item->quantity);
            }
            Product::withTrashed()->whereKey($item->product_id)->where('stock', '>', 0)->update(['availability' => 'in_stock']);
        }
    }

    // ── Buyer rules ───────────────────────────────────────────

    /** The seller has booked the pickup (a courier delivery or sorting-center request is open). */
    public function isReadyForPickup(): bool
    {
        return app(\App\Services\DeliveryRoutingService::class)->activeRoute($this) !== null;
    }

    /** Buyer can cancel an unpaid order, or a paid one until the seller books the pickup ("Ready for Pickup"). */
    public function isCancellableByBuyer(): bool
    {
        return $this->status === self::AWAITING_PAYMENT
            || (in_array($this->status, [self::PENDING, self::PROCESSING], true) && ! $this->isReadyForPickup());
    }

    public function isOnlinePayment(): bool
    {
        return strtoupper((string) $this->payment_method) !== 'COD';
    }

    /** Paid through the gateway (so a cancellation or approved return is refunded). */
    public function isPaidOnline(): bool
    {
        return $this->isOnlinePayment() && in_array($this->payment_status, ['Paid', 'Refund pending', 'Refunded'], true);
    }

    /** Still waiting to be paid, and still in time to pay. */
    public function canBePaid(): bool
    {
        return $this->status === self::AWAITING_PAYMENT && (! $this->pay_by || $this->pay_by->isFuture());
    }

    /** "Order Received" only once the parcel has been delivered. */
    public function canConfirmReceived(): bool
    {
        return $this->status === self::DELIVERED;
    }

    /** Last moment a return can be asked for (and when the order completes by itself). */
    public function returnDeadline(): ?\Illuminate\Support\Carbon
    {
        return $this->delivered_at?->copy()->addDays(self::RETURN_DAYS);
    }

    /** Return / refund: only after delivery, within RETURN_DAYS, and before the order is completed. */
    public function returnWindowOpen(): bool
    {
        return $this->status === self::DELIVERED
            && $this->delivered_at
            && now()->lte($this->returnDeadline());
    }

    /** Reviews: only for completed orders, within Review::REVIEW_DAYS of completion. */
    public function reviewWindowOpen(): bool
    {
        return $this->status === self::COMPLETED
            && $this->completed_at
            && now()->lte($this->completed_at->copy()->addDays(Review::REVIEW_DAYS));
    }

    /** The seller's step: new → accepted → packed → ready (pickup booked) → then logistics takes over. */
    public function sellerStep(): string
    {
        return match (true) {
            $this->status === self::PENDING                       => 'new',
            $this->status !== self::PROCESSING                    => 'done',
            $this->isReadyForPickup()                             => 'ready',
            $this->packed_at !== null                             => 'packed',
            default                                               => 'accepted',
        };
    }

    /** The parcel has been handed to the courier / rider (or is further along). */
    public function isPickedUp(): bool
    {
        if (! in_array($this->status, [self::PENDING, self::PROCESSING], true)) {
            return true;
        }
        $delivery = Delivery::where('order_id', $this->id)->whereIn('status', ['picked_up', 'in_transit', 'delivered'])->exists();
        $parcel   = Parcel::where('order_id', $this->id)->whereNotIn('status', array_merge(Parcel::CLOSED_STATUSES, ['pending_pickup', 'pickup_approved', 'pickup_rejected']))->exists();
        return $delivery || $parcel;
    }

    /** A seller can cancel until the parcel is picked up (assumption A6). */
    public function isCancellableBySeller(): bool
    {
        return in_array($this->status, [self::PENDING, self::PROCESSING], true) && ! $this->isPickedUp();
    }

    /** The My Purchases tab this order belongs in. */
    public function tab(): string
    {
        foreach (self::TABS as $key => $tab) {
            if (in_array($this->status, $tab['statuses'], true)) {
                return $key;
            }
        }
        return 'to_ship';
    }

    /** Status as the buyer sees it. */
    public function buyerStatusLabel(): string
    {
        return match ($this->status) {
            self::AWAITING_PAYMENT => $this->payment_status === 'Failed' ? 'Payment failed — pay again' : 'Waiting for payment',
            self::PENDING          => 'Waiting for the seller',
            self::PROCESSING       => $this->isReadyForPickup() ? 'Ready for pickup' : 'Seller is preparing your order',
            self::SHIPPED          => 'On the way',
            self::DELIVERED        => 'Delivered',
            self::COMPLETED        => 'Completed',
            self::CANCELLED        => 'Cancelled',
            self::RETURN_REQUESTED => 'Return requested',
            self::REFUNDED         => 'Refunded',
            default                => $this->status,
        };
    }

    /** "#000123" */
    public function number(): string
    {
        return '#' . str_pad((string) $this->id, 6, '0', STR_PAD_LEFT);
    }

    // ── Relations ────────────────────────────────────────────

    public function platformVoucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'platform_voucher_id');
    }

    public function shippingVoucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'shipping_voucher_id');
    }

    public function voucherRedemptions(): HasMany
    {
        return $this->hasMany(VoucherRedemption::class);
    }

    /** Everything taken off the item prices by vouchers. */
    public function voucherDiscount(): float
    {
        return (float) $this->platform_discount + (float) $this->seller_discount;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderStatusEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class)->latest();
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /** Other orders from the same checkout (one per store). */
    public function siblings(): HasMany
    {
        return $this->hasMany(Order::class, 'checkout_group', 'checkout_group')->whereKeyNot($this->id);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    /** Sorting-center parcels for this order (one per seller). */
    public function parcels(): HasMany
    {
        return $this->hasMany(Parcel::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** Human-readable full shipping address */
    public function fullAddress(): string
    {
        // address_line often already contains the barangay/city/province, so
        // drop any part that repeats an earlier one (case-insensitive).
        $parts = [];
        foreach ([$this->address_line, $this->city, $this->province, $this->zip_code] as $chunk) {
            foreach (array_map('trim', explode(',', (string) $chunk)) as $part) {
                if ($part !== '' && ! in_array(mb_strtolower($part), array_map('mb_strtolower', $parts), true)) {
                    $parts[] = $part;
                }
            }
        }

        return implode(', ', $parts);
    }

    public function statusColor(): array
    {
        return match(strtolower($this->status)) {
            'delivered', 'completed' => ['bg'=>'#ECFDF5','text'=>'#059669'],
            'shipped'    => ['bg'=>'#FFF7ED','text'=>'#C2410C'],
            'processing' => ['bg'=>'rgba(200,169,138,.15)','text'=>'#6B4C3B'],
            'cancelled'  => ['bg'=>'#FEF2F2','text'=>'#DC2626'],
            'return requested', 'refunded' => ['bg'=>'#EFF6FF','text'=>'#1D4ED8'],
            default      => ['bg'=>'#FFFBEB','text'=>'#B45309'],   // Pending
        };
    }
}
