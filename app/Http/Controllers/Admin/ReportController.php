<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'from'   => 'nullable|date',
            'to'     => 'nullable|date',
            'type'   => 'nullable|in:sales,commission,sellers',
            'seller' => 'nullable|integer',
            'export' => 'nullable|in:csv,pdf',
        ]);

        $from = Carbon::parse($request->input('from', now()->startOfMonth()))->toDateString();
        $to   = Carbon::parse($request->input('to', now()))->toDateString();
        if ($from > $to) {
            [$from, $to] = [$to, $from];     // "Feb 1 to Jan 1" means Jan 1 to Feb 1
        }
        $type = $request->input('type', 'sales'); // 'sales', 'commission' or 'sellers'

        // Picking a seller always opens that seller's own sales report.
        if ($request->filled('seller')) {
            $type = 'sellers';
        }

        $dateFilter = [$from . ' 00:00:00', $to . ' 23:59:59'];

        if ($type === 'sellers') {
            return $this->sellerReport($request, $from, $to, $dateFilter);
        }

        // Sales = orders placed in the period that were not cancelled.
        $orders = fn () => Order::whereBetween('created_at', $dateFilter)->whereNotIn('status', \App\Models\Order::NOT_SALES);
        $items  = fn () => OrderItem::join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', $dateFilter)
            ->whereNotIn('orders.status', \App\Models\Order::NOT_SALES);

        // Only the numbers for the report being shown are queried.
        $totalRevenue = $totalOrders = $totalItems = $byStatus = $topProducts = $dailySales = null;
        $totalCommission = $totalPayout = $bySellerCommission = $dailyCommission = null;
        $commissionRate  = config('marketplace.commission_rate', 10);

        // Every seller with their sales for the period (the "Sellers" box, with "View sales" links).
        $allSellers = $this->sellerTotals($dateFilter);
        $sellerBox  = $allSellers->where('id', '>', 0)->sortByDesc('gross')->values();

        if ($type === 'sales') {
            // ── Sales Summary ────────────────────────────────────
            $totalRevenue   = $orders()->sum('total_price');
            $totalOrders    = $orders()->count();
            $totalItems     = $items()->sum('order_items.quantity');

            // All statuses (cancelled included) so the admin can still see how many were cancelled.
            $byStatus = Order::whereBetween('created_at', $dateFilter)
                ->selectRaw('status, COUNT(*) as count, SUM(total_price) as total')
                ->groupBy('status')
                ->get();

            $topProducts = $items()
                ->selectRaw('order_items.product_id, SUM(order_items.quantity) as units_sold, SUM(order_items.quantity * order_items.price) as revenue')
                ->groupBy('order_items.product_id')
                ->with('product')
                ->orderByDesc('revenue')
                ->take(10)
                ->get();

            $dailySales = $orders()
                ->selectRaw("DATE(created_at) as day_date, SUM(total_price) as total")
                ->groupByRaw("DATE(created_at)")
                ->orderByRaw("DATE(created_at)")
                ->get()
                ->map(fn ($row) => $this->withDayLabel($row));

        } else {
            // ── Commission Report ────────────────────────────────
            $totalCommission = $items()->sum('order_items.commission_amount');
            $totalPayout     = $items()->sum('order_items.seller_earning');

            $bySellerCommission = $allSellers   // includes "ALVY (no seller)"
                ->filter(fn ($row) => $row->units > 0)
                ->sortByDesc('commission')
                ->map(fn ($row) => [
                    'name'       => $row->name,
                    'gross'      => $row->gross,
                    'commission' => $row->commission,
                    'payout'     => $row->payout,
                    'units'      => $row->units,
                ])->values();

            $dailyCommission = $items()
                ->selectRaw("DATE(orders.created_at) as day_date,
                    SUM(order_items.commission_amount) as commission,
                    SUM(order_items.seller_earning) as payout")
                ->groupByRaw("DATE(orders.created_at)")
                ->orderByRaw("DATE(orders.created_at)")
                ->get()
                ->map(fn ($row) => $this->withDayLabel($row));
        }

        $data = compact(
            'from', 'to', 'type',
            // Sales
            'totalRevenue', 'totalOrders', 'totalItems',
            'byStatus', 'topProducts', 'dailySales',
            // Commission
            'totalCommission', 'totalPayout', 'commissionRate',
            'bySellerCommission', 'dailyCommission',
            'sellerBox',
        );

        if ($request->query('export') === 'pdf') {
            return $this->pdf($data, ($type === 'commission' ? 'commission-report' : 'sales-summary') . "-{$from}-to-{$to}.pdf");
        }

        return view('admin.reports.index', $data);
    }

    /** "Jan 5"-style label for a daily row. */
    private function withDayLabel($row)
    {
        $row->day = Carbon::parse($row->day_date)->format('M j');
        return $row;
    }

    /** The report as a downloadable PDF (same numbers as the page). */
    private function pdf(array $data, string $filename)
    {
        return Pdf::loadView('admin.reports.pdf', $data + ['generatedAt' => now(), 'generatedBy' => auth()->user()?->name])
            ->setPaper('a4', 'portrait')
            ->setOption('isFontSubsettingEnabled', true)   // embed only the glyphs used (~25 KB instead of ~900 KB)
            ->download($filename);
    }

    /** Sales per seller for the period (see App\Services\SellerSales). */
    private function sellerTotals(array $dateFilter)
    {
        return app(\App\Services\SellerSales::class)->totals($dateFilter);
    }

    /** "Sales by Seller": all sellers for the period, or one seller's detail (?seller=ID). */
    private function sellerReport(Request $request, string $from, string $to, array $dateFilter)
    {
        $type    = 'sellers';
        $sellers = $this->sellerTotals($dateFilter)->sortByDesc('gross')->values();

        $seller = $products = $daily = null;
        if ($request->filled('seller')) {
            $seller = $sellers->firstWhere('id', (int) $request->seller);
            if (! $seller && ($user = User::with('sellerApplication')->where('role', 'seller')->find((int) $request->seller))) {
                // A seller listed in the picker with no sales in this period
                $seller = (object) ['id' => $user->id, 'name' => $user->name, 'shop' => $user->sellerApplication->shop_name ?? null,
                    'email' => $user->email, 'orders' => 0, 'units' => 0, 'gross' => 0.0, 'commission' => 0.0, 'payout' => 0.0];
            }
            abort_unless($seller, 404);

            $items = OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->whereBetween('orders.created_at', $dateFilter)
                ->whereNotIn('orders.status', \App\Models\Order::NOT_SALES)
                ->where('products.seller_id', $seller->id);

            $products = (clone $items)
                ->groupBy('products.id', 'products.title', 'products.product_code')
                ->selectRaw('products.id, products.title, products.product_code,
                    SUM(order_items.quantity) as units,
                    SUM(order_items.quantity * order_items.price - order_items.seller_discount) as gross,
                    SUM(order_items.commission_amount) as commission,
                    SUM(order_items.seller_earning) as payout')
                ->orderByDesc('gross')
                ->get();

            $daily = (clone $items)
                ->selectRaw('DATE(orders.created_at) as day,
                    COUNT(DISTINCT order_items.order_id) as orders,
                    SUM(order_items.quantity * order_items.price - order_items.seller_discount) as gross')
                ->groupByRaw('DATE(orders.created_at)')
                ->orderByRaw('DATE(orders.created_at)')
                ->get();
        }

        if ($request->query('export') === 'csv') {
            return $this->sellerCsv($from, $to, $sellers, $seller, $products);
        }

        $data = compact('from', 'to', 'type', 'sellers', 'seller', 'products', 'daily');

        if ($request->query('export') === 'pdf') {
            return $this->pdf($data, $seller
                ? 'seller-sales-' . Str::slug($seller->name) . "-{$from}-to-{$to}.pdf"
                : "seller-sales-{$from}-to-{$to}.pdf");
        }

        return view('admin.reports.index', $data);
    }

    private function sellerCsv(string $from, string $to, $sellers, $seller, $products)
    {
        $name = $seller
            ? 'seller-sales-' . Str::slug($seller->name) . "-{$from}-to-{$to}.csv"
            : "seller-sales-{$from}-to-{$to}.csv";

        return response()->streamDownload(function () use ($from, $to, $sellers, $seller, $products) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ALVY seller sales report', "{$from} to {$to}", 'Cancelled orders excluded']);
            if ($seller) {
                fputcsv($out, ['Seller', $seller->name, $seller->shop ?? '']);
                fputcsv($out, ['Product code', 'Product', 'Units sold', 'Gross sales', 'Commission', 'Seller earnings']);
                foreach ($products as $p) {
                    fputcsv($out, [$p->product_code, $p->title, $p->units, $p->gross, $p->commission, $p->payout]);
                }
                fputcsv($out, ['', 'Total', $seller->units, $seller->gross, $seller->commission, $seller->payout]);
            } else {
                fputcsv($out, ['Seller', 'Shop', 'Email', 'Orders', 'Units sold', 'Gross sales', 'Commission', 'Seller earnings']);
                foreach ($sellers as $s) {
                    fputcsv($out, [$s->name, $s->shop, $s->email, $s->orders, $s->units, $s->gross, $s->commission, $s->payout]);
                }
                fputcsv($out, ['Total', '', '', $sellers->sum('orders'), $sellers->sum('units'),
                    $sellers->sum('gross'), $sellers->sum('commission'), $sellers->sum('payout')]);
            }
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }
}
