<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\Delivery;

class ProfitController extends Controller
{
    public function index()
    {
        $courier = auth()->user()->courier;

        $totalEarnings  = $courier->total_earnings;
        $totalDeliveries = Delivery::where('courier_id', $courier->id)->where('status', 'delivered')->count();

        $earningsByMonth = Delivery::where('courier_id', $courier->id)
            ->where('status', 'delivered')
            ->selectRaw("DATE_FORMAT(delivered_at, '%b') as month, SUM(delivery_fee) as total")
            ->groupByRaw("DATE_FORMAT(delivered_at, '%b'), MONTH(delivered_at)")
            ->orderByRaw("MONTH(delivered_at)")
            ->get();

        $recentDeliveries = Delivery::where('courier_id', $courier->id)
            ->where('status', 'delivered')
            ->with('order.user')
            ->latest('delivered_at')
            ->take(10)
            ->get();

        return view('courier.profit', compact(
            'courier', 'totalEarnings', 'totalDeliveries',
            'earningsByMonth', 'recentDeliveries'
        ));
    }
}
