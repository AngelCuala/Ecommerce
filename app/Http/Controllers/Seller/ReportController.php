<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

/**
 * Sales Reports: the seller's sales placed in a date range. Cancelled, refunded and unpaid
 * online orders are not sales, so they add nothing to the totals or the order count.
 */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date_format:Y-m-d',
            'to'   => 'nullable|date_format:Y-m-d|after_or_equal:from',
            'export' => 'nullable|in:csv,xlsx',
        ], [
            'to.after_or_equal' => 'The end date must be on or after the start date.',
        ]);

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   now()->toDateString());

        // Deleted listings still count: their past sales happened.
        $productIds = Product::withTrashed()->where('seller_id', auth()->id())->pluck('id');

        $items = OrderItem::whereIn('product_id', $productIds)
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', Order::NOT_SALES)
                ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59']))
            ->with('product', 'order')
            ->get();

        $totalRevenue      = $items->sum(fn ($i) => $i->quantity * $i->price);
        $totalSellerFunded = $items->sum(fn ($i) => (float) $i->seller_discount + (float) $i->promo_discount);
        $totalCommission   = $items->sum('commission_amount');
        $totalEarnings     = $items->sum('seller_earning');
        $totalOrders       = $items->pluck('order_id')->unique()->count();

        // By product
        $byProduct = $items->groupBy('product_id')->map(function ($group) {
            return [
                'title'    => $group->first()->product->title ?? '—',
                'qty'      => $group->sum('quantity'),
                'revenue'  => $group->sum(fn ($i) => $i->quantity * $i->price),
                'earnings' => $group->sum('seller_earning'),
            ];
        })->sortByDesc('revenue')->values();

        // Daily trend within the range
        $byDate = $items->sortBy(fn ($i) => $i->order->created_at)->groupBy(fn ($i) => $i->order->created_at->format('M d'))
            ->map(fn ($g) => $g->sum('seller_earning'))
            ->take(31);

        $commissionRate = config('marketplace.commission_rate', 10);

        // A36: the export has exactly the rows and totals on screen, as CSV or XLSX.
        if ($request->filled('export')) {
            $rows = $byProduct->map(fn ($p) => [$p['title'], $p['qty'], round($p['revenue'], 2), round($p['earnings'], 2)])->all();
            $rows[] = ['Total', $byProduct->sum('qty'), round($totalRevenue, 2), round($totalEarnings, 2)];
            return \App\Support\TableExport::download("sales-report_{$from}_to_{$to}", ['Product', 'Units sold', 'Gross sales', 'Net earnings'], $rows, $request->export);
        }

        return view('seller.reports.index', compact(
            'from', 'to',
            'totalRevenue', 'totalSellerFunded', 'totalCommission', 'totalEarnings', 'totalOrders',
            'byProduct', 'byDate', 'commissionRate'
        ));
    }
}
