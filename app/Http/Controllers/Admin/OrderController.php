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
        $request->validate([
            'status' => 'required|string|in:Pending,Processing,Shipped,Delivered,Cancelled',
            'reason' => 'required|string|min:10|max:300',
        ], [
            'reason.required' => 'Changing the status by hand is an override: give the reason (10 to 300 characters).',
            'reason.min'      => 'The reason must be 10 to 300 characters.',
            'reason.max'      => 'The reason must be 10 to 300 characters.',
        ]);

        $order = Order::findOrFail($id);
        $old = $order->status;
        $order->update(['status' => $request->status] + ($request->status === Order::CANCELLED ? ['cancellation_reason' => $request->reason] : []));
        \App\Models\ActivityLog::change($old, $request->status, $request->reason);
        \App\Models\ActivityLog::note('Status override on order ' . $order->number(), $order);

        return back()->with('success', 'Order status updated. The change was logged.');
    }
}
