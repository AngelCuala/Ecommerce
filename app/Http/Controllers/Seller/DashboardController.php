<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\SellerCommission;
use App\Support\DateRange;
use Illuminate\Http\Request;

/**
 * The seller's own dashboard. Every number comes from the signed-in seller's products only.
 * Money follows the Sales Reports rule (SellerCommission): cancelled, refunded and unpaid orders
 * are not sales, so they add nothing to sales, commission or earnings.
 */
class DashboardController extends Controller
{
    public function index(Request $request, SellerCommission $commission)
    {
        $range    = DateRange::fromRequest($request, 'month');
        $sellerId = (int) auth()->id();
        $bookIds  = Product::withTrashed()->where('seller_id', $sellerId)->pluck('id');

        $books = Product::where('seller_id', $sellerId)->with('category')->latest()->get();

        // ── Money for the period ─────────────────────────────────
        $periodItems = $commission->lines($sellerId, $range->bounds())->get();
        $money = $commission->summarize($periodItems);
        $commissionRate = config('marketplace.commission_rate', 10);

        // ── Orders for the period, by status ─────────────────────
        $ordersByStatus = Order::whereBetween('created_at', $range->bounds())
            ->whereHas('items', fn ($q) => $q->whereIn('product_id', $bookIds))
            ->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $count = fn (array $statuses) => (int) collect($statuses)->sum(fn ($s) => $ordersByStatus[$s] ?? 0);
        $orderCounts = [
            'total'       => (int) $ordersByStatus->sum(),
            'in_progress' => $count([Order::AWAITING_PAYMENT, Order::PENDING, Order::PROCESSING, Order::SHIPPED, Order::DELIVERED, Order::RETURN_REQUESTED, Order::IN_DISPUTE]),
            'completed'   => $count([Order::COMPLETED]),
            'cancelled'   => $count([Order::CANCELLED]),
            'refunded'    => $count([Order::REFUNDED]),
        ];
        // To ship is about now, not the period: everything still waiting on the seller.
        $toShip = Order::whereIn('status', [Order::PENDING, Order::PROCESSING])
            ->whereHas('items', fn ($q) => $q->whereIn('product_id', $bookIds))->count();

        // ── Best sellers in the period (sales only) ──────────────
        $bestSellers = $periodItems->filter(fn ($i) => in_array($commission->statusOf($i), ['pending', 'finalized'], true))
            ->groupBy('product_id')
            ->map(fn ($g) => (object) [
                'product_id' => $g->first()->product_id,
                'title'      => $g->first()->product->title ?? 'Removed product',
                'units'      => (int) $g->sum('quantity'),
                'sales'      => round($g->sum(fn ($i) => SellerCommission::gross($i)), 2),
            ])
            ->sortByDesc('units')->take(5)->values();

        // ── Recent orders in the period ──────────────────────────
        $recentOrders = OrderItem::whereIn('product_id', $bookIds)
            ->whereHas('order', fn ($q) => $q->whereBetween('created_at', $range->bounds()))
            ->with(['product' => fn ($p) => $p->withTrashed(), 'order.user'])
            ->latest()->take(10)->get();

        // ── Earnings per month (last 6 months) for the chart: sales only ──
        $since = now()->subMonths(5)->startOfMonth();
        $monthly = $commission->lines($sellerId, [$since->toDateTimeString(), now()->endOfDay()->toDateTimeString()])->get()
            ->filter(fn ($i) => in_array($commission->statusOf($i), ['pending', 'finalized'], true));
        $earningsChart = collect(range(5, 0))->map(function ($back) use ($monthly) {
            $month = now()->subMonths($back);
            return (object) [
                'label' => $month->format('M'),
                'value' => round((float) $monthly->filter(fn ($i) => $i->order->created_at->isSameMonth($month))->sum('seller_earning'), 2),
            ];
        });

        // ── Products ─────────────────────────────────────────────
        $active = $books->where('is_archived', false);
        $productCounts = [
            'total'        => $books->count(),
            'active'       => $active->whereNotIn('status', ['draft'])->count(),
            'inactive'     => $books->where('is_archived', true)->count() + $active->where('status', 'draft')->count(),
            'out_of_stock' => $active->where('stock', 0)->count(),
            'low_stock'    => $active->filter(fn ($b) => $b->isLowStock())->count(),
        ];
        $lowStock = Product::where('seller_id', $sellerId)->active()->needsRestock()->orderBy('stock')->take(6)->get();

        return view('seller.dashboard', compact(
            'range', 'books', 'money', 'commissionRate', 'orderCounts', 'toShip',
            'bestSellers', 'recentOrders', 'earningsChart', 'productCounts', 'lowStock'
        ));
    }

    /** "I understand" on a warning from ALVY: it leaves the dashboard (it stays on the admin's record). */
    public function acknowledgeViolation(int $violation)
    {
        $v = \App\Models\SellerViolation::where('seller_id', auth()->id())->findOrFail($violation);
        $v->update(['acknowledged' => true]);

        return back()->with('success', 'Thanks. Please follow ALVY\'s seller policies to avoid further action.');
    }

    /** Seller notifications (orders, parcels, product reviews, payouts…), newest first. */
    public function notifications()
    {
        $user = auth()->user();
        $notifications = $user->notifications()->latest()->paginate(30);
        $unread = $user->notifications()->whereNull('read_at')->count();

        return view('seller.notifications', compact('notifications', 'unread'));
    }

    /** Open one notification: marked read, then its page opens. Only the seller's own. */
    public function openNotification(int $id)
    {
        $note = auth()->user()->notifications()->findOrFail($id);
        if ($note->isUnread()) {
            $note->update(['read_at' => now()]);
        }

        return $note->link ? redirect($note->link) : redirect()->route('seller.notifications');
    }

    public function readAllNotifications()
    {
        auth()->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
