<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   now()->toDateString());
        $type = $request->input('type', 'sales'); // 'sales' or 'commission'

        $dateFilter = [$from . ' 00:00:00', $to . ' 23:59:59'];

        // ── Sales Summary ────────────────────────────────────
        $totalRevenue   = Order::whereBetween('created_at', $dateFilter)->sum('total_price');
        $totalOrders    = Order::whereBetween('created_at', $dateFilter)->count();
        $totalItems     = OrderItem::whereBetween('created_at', $dateFilter)->sum('quantity');

        $byStatus = Order::whereBetween('created_at', $dateFilter)
            ->selectRaw('status, COUNT(*) as count, SUM(total_price) as total')
            ->groupBy('status')
            ->get();

        $topProducts = OrderItem::whereBetween('created_at', $dateFilter)
            ->selectRaw('book_id, SUM(quantity) as units_sold, SUM(quantity * price) as revenue')
            ->groupBy('book_id')
            ->with('book')
            ->orderByDesc('revenue')
            ->take(10)
            ->get();

        $dailySales = Order::whereBetween('created_at', $dateFilter)
            ->selectRaw("DATE_FORMAT(created_at, '%b %d') as day, SUM(total_price) as total")
            ->groupByRaw("DATE_FORMAT(created_at, '%b %d'), DATE(created_at)")
            ->orderByRaw("DATE(created_at)")
            ->get();

        // ── Commission Report ────────────────────────────────
        $totalCommission = OrderItem::whereBetween('created_at', $dateFilter)->sum('commission_amount');
        $totalPayout     = OrderItem::whereBetween('created_at', $dateFilter)->sum('seller_earning');
        $commissionRate  = config('marketplace.commission_rate', 10);

        $bySellerCommission = OrderItem::whereBetween('created_at', $dateFilter)
            ->selectRaw('book_id,
                SUM(quantity * price) as gross,
                SUM(commission_amount) as commission,
                SUM(seller_earning) as payout,
                SUM(quantity) as units')
            ->groupBy('book_id')
            ->with('book.seller')
            ->orderByDesc('commission')
            ->take(20)
            ->get()
            ->groupBy(fn($i) => $i->book?->seller?->name ?? 'Unknown Seller')
            ->map(fn($items) => [
                'name'       => $items->first()->book?->seller?->name ?? 'Unknown',
                'gross'      => $items->sum('gross'),
                'commission' => $items->sum('commission'),
                'payout'     => $items->sum('payout'),
                'units'      => $items->sum('units'),
            ])->values();

        $dailyCommission = OrderItem::whereBetween('created_at', $dateFilter)
            ->selectRaw("DATE_FORMAT(created_at, '%b %d') as day,
                SUM(commission_amount) as commission,
                SUM(seller_earning) as payout")
            ->groupByRaw("DATE_FORMAT(created_at, '%b %d'), DATE(created_at)")
            ->orderByRaw("DATE(created_at)")
            ->get();

        return view('admin.reports.index', compact(
            'from', 'to', 'type',
            // Sales
            'totalRevenue', 'totalOrders', 'totalItems',
            'byStatus', 'topProducts', 'dailySales',
            // Commission
            'totalCommission', 'totalPayout', 'commissionRate',
            'bySellerCommission', 'dailyCommission',
        ));
    }
}
