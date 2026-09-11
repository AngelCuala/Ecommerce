<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\OrderItem;

class DashboardController extends Controller
{
    public function index()
    {
        $bookIds = Book::where('seller_id', auth()->id())->pluck('id');

        $books = Book::whereIn('id', $bookIds)
            ->with('category')
            ->latest()
            ->get();

        // Use seller_earning (after commission deduction)
        $totalEarnings = OrderItem::whereIn('book_id', $bookIds)->sum('seller_earning');
        $totalRevenue  = OrderItem::whereIn('book_id', $bookIds)
            ->selectRaw('SUM(quantity * price) as total')
            ->value('total') ?? 0;
        $totalCommission = OrderItem::whereIn('book_id', $bookIds)->sum('commission_amount');
        $totalOrders   = OrderItem::whereIn('book_id', $bookIds)
            ->distinct('order_id')->count('order_id');

        $recentOrders = OrderItem::whereIn('book_id', $bookIds)
            ->with(['book', 'order.user'])
            ->latest()
            ->take(10)
            ->get();

        $commissionRate = config('marketplace.commission_rate', 10);

        return view('seller.dashboard', compact(
            'books', 'totalEarnings', 'totalRevenue',
            'totalCommission', 'totalOrders', 'recentOrders', 'commissionRate'
        ));
    }
}
