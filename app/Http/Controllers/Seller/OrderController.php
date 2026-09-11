<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index()
    {
        // Get all order IDs that contain this seller's books
        $bookIds = Book::where('seller_id', auth()->id())->pluck('id');

        $orderIds = OrderItem::whereIn('book_id', $bookIds)
            ->pluck('order_id')
            ->unique();

        $orders = Order::whereIn('id', $orderIds)
            ->with(['items' => function ($q) use ($bookIds) {
                $q->whereIn('book_id', $bookIds)->with('book');
            }])
            ->latest()
            ->get();

        // Attach only this seller's items as a virtual property
        $orders->each(function ($order) use ($bookIds) {
            $order->sellerItems = $order->items->whereIn('book_id', $bookIds->toArray())->values();
        });

        return view('seller.orders.index', compact('orders'));
    }

    public function show(int $id)
    {
        $bookIds = Book::where('seller_id', auth()->id())->pluck('id');

        // Make sure this seller actually has items in the order
        $hasItems = OrderItem::where('order_id', $id)
            ->whereIn('book_id', $bookIds)
            ->exists();

        if (! $hasItems) {
            abort(403);
        }

        $order = Order::with(['items' => function ($q) use ($bookIds) {
            $q->whereIn('book_id', $bookIds)->with('book');
        }, 'delivery'])->findOrFail($id);

        $order->sellerItems = $order->items->whereIn('book_id', $bookIds->toArray())->values();

        return view('seller.orders.show', compact('order'));
    }

    public function update(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:Pending,Processing,Shipped,Delivered',
        ]);

        // Only update if this seller owns at least one book in the order
        $bookIds  = Book::where('seller_id', auth()->id())->pluck('id');
        $hasItems = OrderItem::where('order_id', $id)
            ->whereIn('book_id', $bookIds)
            ->exists();

        if (! $hasItems) {
            abort(403);
        }

        Order::findOrFail($id)->update(['status' => $request->status]);

        return back()->with('success', 'Order status updated to ' . $request->status . '.');
    }

    /** Schedule courier pickup & create/update delivery record */
    public function handover(Request $request, int $id)
    {
        $request->validate([
            'courier_name'        => 'required|string|max:100',
            'tracking_number'     => 'nullable|string|max:100',
            'pickup_scheduled_at' => 'required|date',
            'notes'               => 'nullable|string|max:500',
        ]);

        $bookIds  = Book::where('seller_id', auth()->id())->pluck('id');
        $hasItems = OrderItem::where('order_id', $id)->whereIn('book_id', $bookIds)->exists();
        if (! $hasItems) abort(403);

        $order = Order::findOrFail($id);

        \App\Models\Delivery::updateOrCreate(
            ['order_id' => $id],
            [
                'courier_name'        => $request->courier_name,
                'tracking_number'     => $request->tracking_number,
                'pickup_scheduled_at' => $request->pickup_scheduled_at,
                'notes'               => $request->notes,
                'status'              => 'accepted',
                'delivery_fee'        => 50,
            ]
        );

        // Advance order to Processing if still Pending
        if ($order->status === 'Pending') {
            $order->update(['status' => 'Processing']);
        }

        return back()->with('success', 'Courier pickup scheduled for ' . \Carbon\Carbon::parse($request->pickup_scheduled_at)->format('M d, Y h:i A') . '.');
    }

    /** Mark as handed over to courier → status becomes Shipped */
    public function markHandedOver(int $id)
    {
        $bookIds  = Book::where('seller_id', auth()->id())->pluck('id');
        $hasItems = OrderItem::where('order_id', $id)->whereIn('book_id', $bookIds)->exists();
        if (! $hasItems) abort(403);

        $order = Order::findOrFail($id);

        \App\Models\Delivery::where('order_id', $id)->update([
            'status'         => 'in_transit',
            'handed_over_at' => now(),
            'picked_up_at'   => now(),
        ]);

        $order->update(['status' => 'Shipped']);

        return back()->with('success', 'Order marked as handed over to courier. Status updated to Shipped.');
    }
}
