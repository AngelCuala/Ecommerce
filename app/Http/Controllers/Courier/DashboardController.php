<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\Delivery;

class DashboardController extends Controller
{
    public function index()
    {
        $courier = auth()->user()->courier;

        // Available deliveries (not yet accepted by anyone)
        $available = Delivery::where('status', 'available')
            ->with(['order.user', 'order.items.book'])
            ->latest()
            ->get();

        // My active deliveries
        $myActive = Delivery::where('courier_id', $courier->id)
            ->whereNotIn('status', ['delivered', 'failed'])
            ->with(['order.user', 'order.items.book'])
            ->latest()
            ->get();

        // Today's completed
        $todayDone = Delivery::where('courier_id', $courier->id)
            ->where('status', 'delivered')
            ->whereDate('delivered_at', today())
            ->count();

        return view('courier.dashboard', compact('courier', 'available', 'myActive', 'todayDone'));
    }
}
