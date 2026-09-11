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

        // Mark delivery record as delivered
        if ($order->delivery) {
            $order->delivery->update([
                'status'       => 'delivered',
                'delivered_at' => now(),
            ]);
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
