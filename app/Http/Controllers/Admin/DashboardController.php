<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Order;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $totalSales     = Order::sum('total_price');
        $totalOrders    = Order::count();
        $totalCustomers = User::where('role', 'buyer')->orWhereNull('role')->count();
        $totalProducts  = Book::count();

        $recentOrders = Order::with('user')
            ->latest()
            ->take(6)
            ->get();

        $lowStock = Book::where('stock', '<=', 5)
            ->with('category')
            ->orderBy('stock')
            ->take(5)
            ->get();

        // Sales by month — group orders by month name (SQLite-compatible)
        $salesByMonth = Order::selectRaw("strftime('%m', created_at) as month_num, strftime('%Y', created_at) as year, SUM(total_price) as total")
            ->groupByRaw("strftime('%Y-%m', created_at)")
            ->orderByRaw("strftime('%Y-%m', created_at)")
            ->get()
            ->map(function ($row) {
                $months = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'May','06'=>'Jun',
                           '07'=>'Jul','08'=>'Aug','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec'];
                $row->month = ($months[$row->month_num] ?? $row->month_num) . ' ' . $row->year;
                return $row;
            });

        return view('admin.dashboard', compact(
            'totalSales', 'totalOrders', 'totalCustomers', 'totalProducts',
            'recentOrders', 'lowStock', 'salesByMonth'
        ));
    }
}
