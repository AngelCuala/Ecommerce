<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\Delivery;
use App\Services\DeliveryService;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function __construct(private DeliveryService $deliveries) {}

    /** The signed-in user's approved courier profile (403 otherwise, e.g. an admin with no profile). */
    public static function currentCourier(): Courier
    {
        $courier = auth()->user()->courier;
        abort_unless($courier && $courier->isApproved(), 403, 'An approved courier profile is required to use the courier portal.');
        return $courier;
    }

    private function myCourier(): Courier
    {
        return self::currentCourier();
    }

    /** Accept an available delivery — first come, first served. */
    public function accept(int $deliveryId)
    {
        $courier = $this->myCourier();

        // Atomic claim: only succeeds if nobody else took it first and the order is still live.
        $claimed = Delivery::where('id', $deliveryId)
            ->where('status', 'available')
            ->whereNull('courier_id')
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', ['Cancelled', 'Delivered']))
            ->update([
                'courier_id'   => $courier->id,
                'courier_name' => $courier->fullName(),
                'status'       => 'accepted',
                'accepted_at'  => now(),
                'updated_at'   => now(),
            ]);

        if (! $claimed) {
            return back()->with('error', 'This delivery request is no longer available.');
        }

        $delivery = Delivery::with('order.items.book', 'courier')->findOrFail($deliveryId);
        $this->deliveries->advanceOrder($delivery->order, 'Processing');

        $ref = $this->deliveries->ref($delivery);
        $this->deliveries->notifyCourier($delivery, 'New delivery assigned',
            "You accepted {$ref}. Pick it up from {$delivery->pickupName()}.");
        $this->deliveries->notifySellers($delivery, 'Courier assigned',
            "{$courier->fullName()} will pick up {$ref}.");
        $this->deliveries->notifyBuyer($delivery, 'Courier assigned',
            "A courier has been assigned to your {$ref}.");

        return back()->with('success', 'Delivery accepted! Proceed to the seller\'s location to pick up the order.');
    }

    /** Confirm item pickup from seller. */
    public function pickup(int $deliveryId)
    {
        $delivery = $this->getCourierDelivery($deliveryId, 'accepted');

        $delivery->update([
            'status'       => 'picked_up',
            'picked_up_at' => now(),
        ]);
        $this->deliveries->advanceOrder($delivery->order, 'Shipped');

        $ref = $this->deliveries->ref($delivery);
        $this->deliveries->notifyCourier($delivery, 'Pickup confirmed', "{$ref} is now with you. Start the delivery when you're on the way.");
        $this->deliveries->notifySellers($delivery, 'Parcel picked up', "The courier picked up {$ref}.");
        $this->deliveries->notifyBuyer($delivery, 'Order picked up', "Your {$ref} has been picked up by the courier.");

        return back()->with('success', 'Pickup confirmed! Now deliver to the buyer.');
    }

    /** Mark out for delivery. */
    public function inTransit(int $deliveryId)
    {
        $delivery = $this->getCourierDelivery($deliveryId, 'picked_up');
        $delivery->update(['status' => 'in_transit']);
        $this->deliveries->advanceOrder($delivery->order, 'Shipped');

        $this->deliveries->notifyBuyer($delivery, 'Out for delivery',
            'Your ' . $this->deliveries->ref($delivery) . ' is on its way.');

        return back()->with('success', 'Status updated to Out for Delivery.');
    }

    /** Complete delivery. */
    public function complete(Request $request, int $deliveryId)
    {
        $request->validate(['notes' => 'nullable|string|max:500']);

        $delivery = $this->getCourierDelivery($deliveryId, 'in_transit');

        $delivery->update([
            'status'       => 'delivered',
            'delivered_at' => now(),
            // Keep the seller's pickup notes unless the courier adds delivery notes.
            'notes'        => $request->filled('notes') ? $request->input('notes') : $delivery->notes,
        ]);

        $this->deliveries->advanceOrder($delivery->order, 'Delivered');

        // Running total kept in sync; the portal itself sums delivered records.
        $this->myCourier()->increment('total_earnings', $delivery->delivery_fee);

        $ref = $this->deliveries->ref($delivery);
        $this->deliveries->notify(auth()->id(), 'Delivery completed',
            $ref . ' delivered. ₱' . number_format($delivery->delivery_fee, 2) . ' added to your earnings.',
            'info', route('courier.profit'));
        $this->deliveries->notifySellers($delivery, 'Order delivered', "{$ref} was delivered to the buyer.");
        $this->deliveries->notifyBuyer($delivery, 'Order delivered', "Your {$ref} has been delivered.");

        return back()->with('success', 'Delivery completed! ₱' . number_format($delivery->delivery_fee, 2) . ' added to your earnings.');
    }

    /** View delivery history with optional filters (status, order id, date). */
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

    /** View a single delivery detail (only the courier's own jobs). */
    public function show(int $deliveryId)
    {
        $courier  = $this->myCourier();
        $delivery = Delivery::where('id', $deliveryId)
            ->where('courier_id', $courier->id)
            ->with(['order.user', 'order.items.book'])
            ->firstOrFail();

        return view('courier.delivery-show', compact('delivery'));
    }

    /** A job owned by this courier in the expected status; 404 for anyone else's job. */
    private function getCourierDelivery(int $id, string $expectedStatus): Delivery
    {
        return Delivery::where('id', $id)
            ->where('courier_id', $this->myCourier()->id)
            ->where('status', $expectedStatus)
            ->with(['order.items.book', 'courier'])
            ->firstOrFail();
    }
}
