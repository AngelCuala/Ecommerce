<?php

namespace App\Services;

use App\Models\Order;
use App\Models\UserNotification;

/**
 * In-app notifications for every order status change, to the buyer and to the seller.
 * Each one links to the order, so opening it shows the right order.
 */
class OrderNotifier
{
    /** A new order was placed (called once its items exist). */
    public function placed(Order $order): void
    {
        if ($order->status === Order::AWAITING_PAYMENT) {
            // The seller hears about it once it is paid.
            $this->toBuyer($order, 'Complete your payment', 'Pay for order ' . $order->number() . ' by '
                . $order->pay_by?->format('M j, g:i A') . ', or it will be cancelled.', 'warning');
            return;
        }
        $this->toBuyer($order, 'Order placed', 'We received your order ' . $order->number() . '. The seller will prepare it soon.', 'info');
        $this->toSellers($order, 'New order ' . $order->number(), 'A buyer ordered ' . $this->itemsText($order) . '. Please prepare it.', 'info');
    }

    public function statusChanged(Order $order, ?string $from): void
    {
        $n = $order->number();

        if ($from === Order::AWAITING_PAYMENT && $order->status === Order::PENDING) {
            $this->toBuyer($order, 'Payment received', "We received your payment for order {$n}. The seller will prepare it soon.", 'success');
            $this->toSellers($order, "New order {$n}", 'A buyer paid for ' . $this->itemsText($order) . '. Please prepare it.', 'info');
            return;
        }

        [$buyerTitle, $buyerBody, $type] = match ($order->status) {
            Order::PROCESSING       => ['Order confirmed', "The seller is preparing order {$n}.", 'info'],
            Order::SHIPPED          => ['Order shipped', "Order {$n} has been picked up and is on its way.", 'info'],
            Order::DELIVERED        => $from === Order::RETURN_REQUESTED
                ? ['Return request rejected', "The seller rejected the return request for order {$n}. See the order for their reason.", 'warning']
                : ['Order delivered', "Order {$n} was delivered. Tap Order Received, or ask for a return within " . Order::RETURN_DAYS . ' days.', 'success'],
            Order::COMPLETED        => ['Order completed', "Order {$n} is complete. Rate your items to help other buyers.", 'success'],
            Order::CANCELLED        => ['Order cancelled', "Order {$n} was cancelled" . ($order->cancellation_reason ? ': ' . $order->cancellation_reason : '.'), 'warning'],
            Order::RETURN_REQUESTED => ['Return requested', "Your return request for order {$n} was sent to the seller.", 'info'],
            Order::REFUNDED         => ['Refund approved', "The seller approved your return for order {$n}. Your refund is on its way.", 'success'],
            default                 => ['Order update', "Order {$n} is now {$order->status}.", 'info'],
        };
        $this->toBuyer($order, $buyerTitle, $buyerBody, $type);

        $sellerMessage = match ($order->status) {
            Order::CANCELLED        => ["Order {$n} cancelled", 'The order was cancelled' . ($order->cancellation_reason ? ': ' . $order->cancellation_reason : '.') . ' Its stock has been put back.'],
            Order::RETURN_REQUESTED => ["Return request for {$n}", 'The buyer asked for a return. Please review it on the order page.'],
            Order::COMPLETED        => ["Order {$n} completed", 'The buyer received the order. Your earnings for it are now available.'],
            Order::DELIVERED        => $from === Order::RETURN_REQUESTED ? null : ["Order {$n} delivered", 'The parcel reached the buyer.'],
            default                 => null,
        };
        if ($sellerMessage && $from !== Order::AWAITING_PAYMENT) {   // unpaid orders never reached the seller
            $this->toSellers($order, $sellerMessage[0], $sellerMessage[1], $type);
        }
    }

    private function toBuyer(Order $order, string $title, string $body, string $type): void
    {
        UserNotification::create([
            'user_id' => $order->user_id, 'title' => $title, 'body' => $body, 'type' => $type,
            'link'    => route('profile.orders.show', $order->id, false),
        ]);
    }

    private function toSellers(Order $order, string $title, string $body, string $type): void
    {
        foreach ($this->sellerIds($order) as $sellerId) {
            UserNotification::create([
                'user_id' => $sellerId, 'title' => $title, 'body' => $body, 'type' => $type,
                'link'    => route('seller.orders.show', $order->id, false),
            ]);
        }
    }

    /** The order's seller (older orders may hold several sellers' items). */
    private function sellerIds(Order $order): array
    {
        if ($order->seller_id) {
            return [$order->seller_id];
        }
        return $order->items()->with('product')->get()->pluck('product.seller_id')->filter()->unique()->values()->all();
    }

    private function itemsText(Order $order): string
    {
        return $order->items()->with('product')->get()
            ->map(fn ($i) => ($i->product->title ?? 'item') . ($i->variation_name ? " ({$i->variation_name})" : '') . ' ×' . $i->quantity)
            ->implode(', ');
    }
}
