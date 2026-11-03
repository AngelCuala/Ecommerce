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
            'export' => 'nullable|in:pdf,csv,xlsx',   // always a PDF (older csv / xlsx links get the PDF too)
        ], [
            'to.after_or_equal' => 'The end date must be on or after the start date.',
        ]);

        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to   = $request->input('to',   now()->toDateString());

        // Deleted listings still count: their past sales happened.
        $productIds = Product::withTrashed()->where('seller_id', auth()->id())->pluck('id');

        $items = OrderItem::whereIn('product_id', $productIds)->whereNull('refunded_at')
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

        // Net earnings over the range: every day (₱0 days too, so the chart shows the real gaps), or every
        // month when the range is longer than 62 days. Empty when nothing sold ("No sales in this period").
        [$byDate, $byDateUnit] = $this->trend($items, $from, $to);

        $commissionRate = config('marketplace.commission_rate', 10);

        // A36: the export has exactly the rows and totals on screen, as a PDF.
        if ($request->filled('export')) {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('seller.reports.pdf', [
                'from' => $from, 'to' => $to, 'store' => auth()->user()->storeName(),
                'byProduct' => $byProduct, 'commissionRate' => $commissionRate,
                'totals' => ['orders' => $totalOrders, 'units' => (int) $byProduct->sum('qty'), 'revenue' => $totalRevenue,
                             'sellerFunded' => $totalSellerFunded, 'commission' => $totalCommission, 'earnings' => $totalEarnings],
                'generatedAt' => now(),
            ])->setPaper('a4', 'portrait')
                ->setOption('isFontSubsettingEnabled', true)   // embed only the glyphs used
                ->download("sales-report_{$from}_to_{$to}.pdf");
        }

        return view('seller.reports.index', compact(
            'from', 'to',
            'totalRevenue', 'totalSellerFunded', 'totalCommission', 'totalEarnings', 'totalOrders',
            'byProduct', 'byDate', 'byDateUnit', 'commissionRate'
        ));
    }

    /**
     * [label => net earnings] for each day from $from to $to (or each month when the range is over 62 days),
     * in order, with ₱0 for days without sales; and the unit ('day' or 'month').
     *
     * @return array{0: \Illuminate\Support\Collection, 1: string}
     */
    private function trend(\Illuminate\Support\Collection $items, string $from, string $to): array
    {
        $start = \Carbon\Carbon::parse($from)->startOfDay();
        $end   = \Carbon\Carbon::parse($to)->startOfDay();
        $monthly = $start->diffInDays($end) + 1 > 62;
        $unit  = $monthly ? 'month' : 'day';
        if ($items->isEmpty()) {
            return [collect(), $unit];
        }

        $label = fn (\Carbon\CarbonInterface $d) => $monthly ? $d->format('M Y') : $d->format('M d');
        $series = [];
        for ($d = $monthly ? $start->copy()->startOfMonth() : $start->copy(); $d->lte($end); $monthly ? $d->addMonthNoOverflow() : $d->addDay()) {
            $series[$label($d)] = 0.0;
        }
        foreach ($items as $item) {
            $key = $label($item->order->created_at);
            if (array_key_exists($key, $series)) {
                $series[$key] = round($series[$key] + (float) $item->seller_earning, 2);
            }
        }

        return [collect($series), $unit];
    }
}
