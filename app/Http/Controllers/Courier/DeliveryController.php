<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    private function myCourier()
    {
        return auth()->user()->courier;
    }

    /** Accept an available delivery — first come, first served */
    public function accept(int $deliveryId)
    {
        $delivery = Delivery::where('id', $deliveryId)
            ->where('status', 'available')
            ->firstOrFail();

        $courier = $this->myCourier();

        $delivery->update([
            'courier_id'  => $courier->id,
            'status'      => 'accepted',
            'accepted_at' => now(),
        ]);

        // Update order status
        $delivery->order->update(['status' => 'Processing']);

        return back()->with('success', 'Delivery accepted! Proceed to the seller\'s location to pick up the order.');
    }

    /** Confirm item pickup from seller */
    public function pickup(int $deliveryId)
    {
        $delivery = $this->getCourierDelivery($deliveryId, 'accepted');

        $delivery->update([
            'status'       => 'picked_up',
            'picked_up_at' => now(),
        ]);

        $delivery->order->update(['status' => 'Shipped']);

        return back()->with('success', 'Pickup confirmed! Now deliver to the buyer.');
    }

    /** Mark in transit */
    public function inTransit(int $deliveryId)
    {
        $delivery = $this->getCourierDelivery($deliveryId, 'picked_up');
        $delivery->update(['status' => 'in_transit']);
        return back()->with('success', 'Status updated to In Transit.');
    }

    /** Complete delivery */
    public function complete(Request $request, int $deliveryId)
    {
        $delivery = $this->getCourierDelivery($deliveryId, 'in_transit');

        $delivery->update([
            'status'       => 'delivered',
            'delivered_at' => now(),
            'notes'        => $request->input('notes'),
        ]);

        // Update order
        $delivery->order->update(['status' => 'Delivered']);

        // Add earnings to courier
        $courier = $this->myCourier();
        $courier->increment('total_earnings', $delivery->delivery_fee);

        \App\Models\UserNotification::create([
            'user_id' => auth()->id(),
            'title'   => 'Delivery completed',
            'body'    => 'Order #' . str_pad($delivery->order_id, 6, '0', STR_PAD_LEFT)
                        . ' delivered. ₱' . number_format($delivery->delivery_fee, 2) . ' added to your earnings.',
            'type'    => 'info',
            'link'    => route('courier.profit'),
        ]);

        return back()->with('success', 'Delivery completed! ₱' . number_format($delivery->delivery_fee, 2) . ' added to your earnings.');
    }

    /** View delivery history with optional filters (status, order id, date) */
    public function history(Request $request)
    {
        $courier = $this->myCourier();

        $status  = $request->query('status');
        $orderId = $request->query('order_id');
        $date    = $request->query('date');

        $deliveries = Delivery::where('courier_id', $courier->id)
            ->when($status,  fn ($q) => $q->where('status', $status))
            ->when($orderId, fn ($q) => $q->where('order_id', (int) ltrim($orderId, '#0') ?: 0))
            ->when($date,    fn ($q) => $q->whereDate('delivered_at', $date))
            ->with(['order.user', 'order.items.book'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('courier.history', compact('deliveries', 'courier', 'status', 'orderId', 'date'));
    }

    /** View a single delivery detail */
    public function show(int $deliveryId)
    {
        $courier  = $this->myCourier();
        $delivery = Delivery::where('id', $deliveryId)
            ->where('courier_id', $courier->id)
            ->with(['order.user', 'order.items.book'])
            ->firstOrFail();

        return view('courier.delivery-show', compact('delivery'));
    }

    private function getCourierDelivery(int $id, string $expectedStatus): Delivery
    {
        return Delivery::where('id', $id)
            ->where('courier_id', $this->myCourier()->id)
            ->where('status', $expectedStatus)
            ->firstOrFail();
    }
}
