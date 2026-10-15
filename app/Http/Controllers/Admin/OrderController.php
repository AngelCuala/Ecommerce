<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $query = Order::with('user')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->whereHas('user', fn ($q) =>
                $q->where('name', 'like', '%'.$request->search.'%')
                  ->orWhere('email', 'like', '%'.$request->search.'%')
            );
        }

        $orders = $query->paginate(20)->withQueryString();

        $statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Completed', 'Cancelled', 'Return Requested', 'Refunded'];

        return view('admin.orders.index', compact('orders', 'statuses'));
    }

    public function show(int $id)
    {
        $order = Order::with(['user', 'items.product.seller'])->findOrFail($id);

        $totalCommission = $order->items->sum('commission_amount');
        $totalEarnings   = $order->items->sum('seller_earning');

        return view('admin.orders.show', compact('order', 'totalCommission', 'totalEarnings'));
    }

    public function update(Request $request, int $id)
    {
        $request->validate(['status' => 'required|string|in:Pending,Processing,Shipped,Delivered,Cancelled']);

        Order::findOrFail($id)->update(['status' => $request->status]);

        return back()->with('success', 'Order status updated.');
    }

    /** Decide a disputed return: refund the buyer, or close it and complete the order. */
    public function resolveDispute(Request $request, int $id)
    {
        $order  = Order::findOrFail($id);
        $return = $order->returnRequests()->where('status', 'disputed')->latest()->first();
        if (! $return || $order->status !== Order::IN_DISPUTE) {
            return back()->with('error', 'This order has no open dispute.');
        }
        $data = $request->validate([
            'decision' => 'required|in:refund,close',
            'note'     => 'nullable|string|max:500',
        ]);

        $refund = $data['decision'] === 'refund';
        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $return, $refund, $data) {
            $return->update([
                'status'      => $refund ? 'approved' : 'closed',
                'seller_note' => trim(($return->seller_note ? $return->seller_note . ' · ' : '') . 'ALVY: ' . ($data['note'] ?? ($refund ? 'Refund approved.' : 'Closed without a refund.'))),
                'resolved_at' => now(),
            ]);
            $order->update(['status' => $refund ? Order::REFUNDED : Order::COMPLETED]);
        });
        \App\Models\ActivityLog::note('Dispute on ' . $order->number() . ($refund ? ' refunded' : ' closed'), $order);

        return back()->with('success', $refund ? 'Dispute decided: the buyer is refunded.' : 'Dispute closed without a refund; the order is completed.');
    }
}
