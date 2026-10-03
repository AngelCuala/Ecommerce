<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Buyer cancels an order (only while Pending or Processing).
     */
    public function cancelByBuyer(Request $request, int $id)
    {
        $order = Order::where('user_id', auth()->id())->findOrFail($id);

        if (! $order->isCancellableByBuyer()) {
            return back()->with('error', 'This order can no longer be cancelled.');
        }

        $request->validate([
            'cancellation_reason' => 'required|string|max:500',
        ]);

        $order->update([
            'status'               => 'Cancelled',
            'cancellation_reason'  => $request->cancellation_reason,
        ]);

        // A courier job that hasn't been picked up yet can no longer be completed.
        $delivery = Delivery::where('order_id', $order->id)
            ->whereIn('status', ['available', 'accepted'])->with('courier')->first();
        if ($delivery) {
            $delivery->update(['status' => 'failed', 'notes' => 'Order cancelled by buyer.']);
            app(\App\Services\DeliveryService::class)->notifyCourier($delivery, 'Delivery cancelled',
                'Order #' . str_pad($order->id, 6, '0', STR_PAD_LEFT) . ' was cancelled by the buyer. No pickup needed.', 'warning');
        }

        return back()->with('success', 'Your order has been cancelled.');
    }

    /**
     * Buyer confirms they received the order.
     * - Marks order as Delivered
     * - Marks delivery as delivered
     * - Sends a system message to each seller in the order notifying them
     */
    public function confirmDelivery(int $id)
    {
        $order = Order::with(['items.book.seller', 'delivery'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        // Only allow confirmation when order is Shipped
        if ($order->status !== 'Shipped') {
            return back()->with('error', 'This order cannot be confirmed at this time.');
        }

        // Mark order as delivered
        $order->update([
            'status'         => 'Delivered',
            'payment_status' => 'Paid', // COD — payment collected on delivery
        ]);

        // Mark delivery record as delivered (once) and credit the courier who carried it.
        $delivery = $order->delivery;
        if ($delivery && ! in_array($delivery->status, ['delivered', 'failed'], true)) {
            $delivery->update([
                'status'       => 'delivered',
                'delivered_at' => now(),
            ]);

            if ($delivery->courier) {
                $delivery->courier->increment('total_earnings', $delivery->delivery_fee);
                app(\App\Services\DeliveryService::class)->notifyCourier($delivery, 'Buyer confirmed delivery',
                    'Order #' . str_pad($order->id, 6, '0', STR_PAD_LEFT) . ' was confirmed received. ₱'
                    . number_format($delivery->delivery_fee, 2) . ' added to your earnings.');
            }
        }

        // Notify each unique seller via the messaging system
        $sellerIds = $order->items
            ->pluck('book.seller_id')
            ->filter()
            ->unique();

        foreach ($sellerIds as $sellerId) {
            Message::create([
                'order_id'    => $order->id,
                'sender_id'   => auth()->id(),   // buyer sends
                'receiver_id' => $sellerId,       // to seller
                'body'        => "✅ Your order #" . str_pad($order->id, 6, '0', STR_PAD_LEFT)
                               . " has been received by the buyer ("
                               . auth()->user()->name
                               . "). Delivery confirmed on "
                               . now()->format('M d, Y h:i A') . ".",
                'is_read'     => false,
            ]);
        }

        return back()->with('success', 'Order confirmed as received! The seller has been notified.');
    }
}
