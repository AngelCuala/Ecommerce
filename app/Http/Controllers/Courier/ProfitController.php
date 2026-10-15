<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use Illuminate\Support\Carbon;

class ProfitController extends Controller
{
    public function index()
    {
        $courier = DeliveryController::currentCourier();

        $base = fn () => Delivery::where('courier_id', $courier->id)->where('status', 'delivered');

        // Sum the delivered records (also counts deliveries the buyer confirmed).
        $totalEarnings   = (float) $base()->sum('delivery_fee');
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
        // Grouped in PHP so it works on any database (not only MySQL's DATE_FORMAT).
        $earningsByMonth = $base()->whereNotNull('delivered_at')->get(['delivered_at', 'delivery_fee'])
            ->groupBy(fn ($d) => $d->delivered_at->format('Y-m'))
            ->sortKeys()
            ->map(fn ($rows, $ym) => (object) [
                'ym'    => $ym,
                'count' => $rows->count(),
                'total' => (float) $rows->sum('delivery_fee'),
                'month' => Carbon::createFromFormat('Y-m-d', $ym . '-01')->format('M Y'),
            ])->values();

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
