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

        $statuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];

        return view('admin.orders.index', compact('orders', 'statuses'));
    }

    public function show(int $id)
    {
        $order = Order::with(['user', 'items.book.seller'])->findOrFail($id);

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
}
