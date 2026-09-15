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
            ->selectRaw("strftime('%m', delivered_at) as month_num, strftime('%Y', delivered_at) as year, SUM(delivery_fee) as total")
            ->groupByRaw("strftime('%Y-%m', delivered_at)")
            ->orderByRaw("strftime('%Y-%m', delivered_at)")
            ->get()
            ->map(function ($row) {
                $months = ['01'=>'Jan','02'=>'Feb','03'=>'Mar','04'=>'Apr','05'=>'May','06'=>'Jun',
                           '07'=>'Jul','08'=>'Aug','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec'];
                $row->month = ($months[$row->month_num] ?? $row->month_num) . ' ' . $row->year;
                return $row;
            });

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
