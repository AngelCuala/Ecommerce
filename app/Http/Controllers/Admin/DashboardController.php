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

        // Sales by month — group orders by month name
        $salesByMonth = Order::selectRaw("DATE_FORMAT(created_at, '%b') as month, SUM(total_price) as total")
            ->groupByRaw("DATE_FORMAT(created_at, '%b'), MONTH(created_at)")
            ->orderByRaw("MONTH(created_at)")
            ->get();

        return view('admin.dashboard', compact(
            'totalSales', 'totalOrders', 'totalCustomers', 'totalProducts',
            'recentOrders', 'lowStock', 'salesByMonth'
        ));
    }
}
