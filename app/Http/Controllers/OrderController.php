<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Message;
use App\Models\Order;
use App\Models\ReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public const CANCEL_REASONS = [
        'Changed my mind', 'Found a better price', 'Ordered by mistake', 'Need to change the address',
        'Need to change the quantity or option', 'Seller is taking too long',
    ];

    /**
     * Buyer cancels an order — only until the seller books the pickup ("Ready for Pickup").
     * Stock and vouchers come back (Order model), and the seller is notified.
     */
    public function cancelByBuyer(Request $request, int $id)
    {
        $order = Order::where('user_id', auth()->id())->findOrFail($id);

        if (! $order->isCancellableByBuyer()) {
            return back()->with('error', $order->isReadyForPickup()
                ? 'This order is already ready for pickup, so it can no longer be cancelled. Please message the seller.'
                : 'This order can no longer be cancelled.');
        }

        $request->validate([
            'cancellation_reason' => ['required', 'string', 'max:500'],
        ], [
            'cancellation_reason.required' => 'Please choose a reason for cancelling.',
        ]);

        $order->update([
            'status'              => Order::CANCELLED,
            'cancellation_reason' => $request->cancellation_reason,
        ]);
        \App\Models\ActivityLog::note('Order ' . $order->number() . ' · reason: ' . $request->cancellation_reason, $order);

        // A courier job that hasn't been picked up yet can no longer be completed.
        $delivery = Delivery::where('order_id', $order->id)
            ->whereIn('status', ['available', 'accepted'])->with('courier')->first();
        if ($delivery) {
            $delivery->update(['status' => 'failed', 'notes' => 'Order cancelled by buyer.']);
            app(\App\Services\DeliveryService::class)->notifyCourier($delivery, 'Delivery cancelled',
                'Order ' . $order->number() . ' was cancelled by the buyer. No pickup needed.', 'warning');
        }

        return back()->with('success', 'Your order has been cancelled.');
    }

    /**
     * "Order Received": the buyer confirms a delivered order, which completes it.
     * (Orders also complete by themselves Order::RETURN_DAYS days after delivery.)
     */
    public function confirmDelivery(int $id)
    {
        $order = Order::with(['items.product.seller', 'delivery'])
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        if (! $order->canConfirmReceived()) {
            return back()->with('error', 'You can confirm the order once it has been delivered.');
        }

        $order->update([
            'status'         => Order::COMPLETED,
            'payment_status' => 'Paid', // COD — payment collected on delivery
        ]);

        // A courier delivery the courier didn't close: close it now and credit the courier.
        $delivery = $order->delivery;
        if ($delivery && ! in_array($delivery->status, ['delivered', 'failed'], true)) {
            $delivery->update(['status' => 'delivered', 'delivered_at' => now()]);

            if ($delivery->courier) {
                $delivery->courier->increment('total_earnings', $delivery->delivery_fee);
                app(\App\Services\DeliveryService::class)->notifyCourier($delivery, 'Buyer confirmed delivery',
                    'Order ' . $order->number() . ' was confirmed received. ₱'
                    . number_format($delivery->delivery_fee, 2) . ' added to your earnings.');
            }
        }

        foreach ($order->items->pluck('product.seller_id')->filter()->unique() as $sellerId) {
            Message::create([
                'order_id'    => $order->id,
                'sender_id'   => auth()->id(),
                'receiver_id' => $sellerId,
                'body'        => '✅ Order ' . $order->number() . ' was received by the buyer (' . auth()->user()->name
                               . ') on ' . now()->format('M d, Y h:i A') . '.',
                'is_read'     => false,
            ]);
        }

        return back()->with('success', 'Thanks! The order is complete. You can now rate your items.');
    }

    /** Return / refund request: within Order::RETURN_DAYS of delivery, before the order is completed. */
    public function requestReturn(Request $request, int $id)
    {
        $order = Order::where('user_id', auth()->id())->findOrFail($id);

        if (! $order->returnWindowOpen()) {
            return back()->with('error', $order->status === Order::COMPLETED
                ? 'This order is already completed, so a return can no longer be requested.'
                : 'A return can be requested only within ' . Order::RETURN_DAYS . ' days after the order is delivered.');
        }
        if ($order->returnRequests()->where('status', 'requested')->exists()) {
            return back()->with('error', 'You already have a return request waiting for this order.');
        }

        $data = $request->validate([
            'reason'   => ['required', Rule::in(ReturnRequest::REASONS)],
            'details'  => 'nullable|string|max:1000',
            'evidence' => 'required|file|mimes:jpg,jpeg,png|mimetypes:image/jpeg,image/png|max:5120',
        ], [
            'evidence.required'  => 'Please add a photo of the item as evidence.',
            'evidence.mimes'     => 'The evidence must be a JPG or PNG photo.',
            'evidence.mimetypes' => 'The evidence must be a real JPG or PNG photo.',
            'evidence.max'       => 'The photo can be at most 5 MB.',
        ]);

        $order->returnRequests()->create([
            'user_id'       => auth()->id(),
            'seller_id'     => $order->seller_id ?? $order->items()->with('product')->first()?->product?->seller_id,
            'reason'        => $data['reason'],
            'details'       => $data['details'] ?? null,
            'evidence_path' => $request->file('evidence')->store('returns', 'public'),
            'refund_amount' => $order->total_price,
            'status'        => 'requested',
        ]);
        $order->update(['status' => Order::RETURN_REQUESTED]);
        \App\Models\ActivityLog::note('Order ' . $order->number() . ' · return requested: ' . $data['reason'], $order);

        return back()->with('success', 'Return request sent. The seller will review it.');
    }
}
