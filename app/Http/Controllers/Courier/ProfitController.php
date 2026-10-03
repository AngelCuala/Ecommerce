<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use Illuminate\Support\Carbon;

class ProfitController extends Controller
{
    public function index()
    {
        $courier = auth()->user()->courier;

        $base = fn () => Delivery::where('courier_id', $courier->id)->where('status', 'delivered');

        $totalEarnings   = (float) $courier->total_earnings;
        $totalDeliveries = $base()->count();

        // Time-window earnings (based on delivered_at)
        $todayEarnings = (float) $base()->whereDate('delivered_at', today())->sum('delivery_fee');
        $weekEarnings  = (float) $base()->whereBetween('delivered_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('delivery_fee');
        $monthEarnings = (float) $base()->whereMonth('delivered_at', now()->month)
            ->whereYear('delivered_at', now()->year)->sum('delivery_fee');

        // Pending earnings = fees on deliveries in progress (not yet completed/failed)
        $pendingEarnings = (float) Delivery::where('courier_id', $courier->id)
            ->whereIn('status', ['accepted', 'picked_up', 'in_transit'])
            ->sum('delivery_fee');

        $avgPerDelivery = $totalDeliveries > 0 ? $totalEarnings / $totalDeliveries : 0.0;

        // Earnings per month for the chart + table
        $earningsByMonth = $base()
            ->selectRaw("DATE_FORMAT(delivered_at, '%Y-%m') as ym, COUNT(*) as count, SUM(delivery_fee) as total")
            ->groupByRaw("DATE_FORMAT(delivered_at, '%Y-%m')")
            ->orderByRaw("DATE_FORMAT(delivered_at, '%Y-%m')")
            ->get()
            ->map(function ($row) {
                [$year, $mon] = explode('-', $row->ym);
                $row->month = Carbon::createFromDate($year, (int) $mon, 1)->format('M Y');
                return $row;
            });

        $recentDeliveries = Delivery::where('courier_id', $courier->id)
            ->where('status', 'delivered')
            ->with('order.user')
            ->latest('delivered_at')
            ->take(10)
            ->get();

        return view('courier.profit', compact(
            'courier', 'totalEarnings', 'totalDeliveries', 'avgPerDelivery',
            'todayEarnings', 'weekEarnings', 'monthEarnings', 'pendingEarnings',
            'earningsByMonth', 'recentDeliveries'
        ));
    }
}
