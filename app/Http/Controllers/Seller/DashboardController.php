<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\OrderItem;

class DashboardController extends Controller
{
    public function index()
    {
        $bookIds = Product::where('seller_id', auth()->id())->pluck('id');

        $books = Product::whereIn('id', $bookIds)
            ->with('category')
            ->latest()
            ->get();

        // Use seller_earning (after commission deduction)
        $totalEarnings = OrderItem::whereIn('product_id', $bookIds)->sum('seller_earning');
        $totalRevenue  = OrderItem::whereIn('product_id', $bookIds)
            ->selectRaw('SUM(quantity * price) as total')
            ->value('total') ?? 0;
        $totalCommission = OrderItem::whereIn('product_id', $bookIds)->sum('commission_amount');
        $totalOrders   = OrderItem::whereIn('product_id', $bookIds)
            ->distinct('order_id')->count('order_id');

        $recentOrders = OrderItem::whereIn('product_id', $bookIds)
            ->with(['product', 'order.user'])
            ->latest()
            ->take(10)
            ->get();

        $commissionRate = config('marketplace.commission_rate', 10);

        // ── Earnings per month (last 6 months) for the chart ──────
        $since = now()->subMonths(5)->startOfMonth();
        $monthlyItems = OrderItem::whereIn('product_id', $bookIds)
            ->where('created_at', '>=', $since)
            ->get(['seller_earning', 'created_at']);
        $earningsChart = collect(range(5, 0))->map(function ($back) use ($monthlyItems) {
            $month = now()->subMonths($back);
            return (object) [
                'label' => $month->format('M'),
                'value' => (float) $monthlyItems
                    ->filter(fn ($i) => $i->created_at->isSameMonth($month))
                    ->sum('seller_earning'),
            ];
        });
        $thisMonthEarnings = $earningsChart->last()->value;
        $lastMonthEarnings = $earningsChart->slice(-2, 1)->first()->value ?? 0;

        // ── Orders by status (orders that include this seller's products) ──
        $ordersByStatus = OrderItem::whereIn('order_items.product_id', $bookIds)
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->selectRaw('orders.status, COUNT(DISTINCT orders.id) as c')
            ->groupBy('orders.status')
            ->pluck('c', 'orders.status');
        $toShip = (int) ($ordersByStatus['Pending'] ?? 0) + (int) ($ordersByStatus['Processing'] ?? 0);

        // ── Products running low or sold out ──────────────────────
        $lowStock = Product::whereIn('id', $bookIds)->active()->where('stock', '<=', 5)
            ->orderBy('stock')->take(5)->get();

        return view('seller.dashboard', compact(
            'books', 'totalEarnings', 'totalRevenue',
            'totalCommission', 'totalOrders', 'recentOrders', 'commissionRate',
            'earningsChart', 'thisMonthEarnings', 'lastMonthEarnings',
            'ordersByStatus', 'toShip', 'lowStock'
        ));
    }
}
