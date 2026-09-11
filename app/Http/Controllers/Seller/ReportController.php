<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   now()->toDateString());

        $bookIds = Book::where('seller_id', auth()->id())->pluck('id');

        // Order items in date range
        $items = OrderItem::whereIn('book_id', $bookIds)
            ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
            ->with('book', 'order')
            ->get();

        $totalRevenue    = $items->sum(fn($i) => $i->quantity * $i->price);
        $totalCommission = $items->sum('commission_amount');
        $totalEarnings   = $items->sum('seller_earning');
        $totalOrders     = $items->pluck('order_id')->unique()->count();

        // By product
        $byProduct = $items->groupBy('book_id')->map(function ($group) {
            return [
                'title'    => $group->first()->book->title ?? '—',
                'qty'      => $group->sum('quantity'),
                'revenue'  => $group->sum(fn($i) => $i->quantity * $i->price),
                'earnings' => $group->sum('seller_earning'),
            ];
        })->sortByDesc('revenue')->values();

        // Monthly trend (within selected range, grouped by date)
        $byDate = $items->groupBy(fn($i) => $i->created_at->format('M d'))
            ->map(fn($g) => $g->sum('seller_earning'))
            ->take(30);

        $commissionRate = config('marketplace.commission_rate', 10);

        return view('seller.reports.index', compact(
            'from', 'to',
            'totalRevenue', 'totalCommission', 'totalEarnings', 'totalOrders',
            'byProduct', 'byDate', 'commissionRate'
        ));
    }
}
