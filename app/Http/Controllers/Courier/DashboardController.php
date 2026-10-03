<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\Delivery;

class DashboardController extends Controller
{
    public function index()
    {
        $courier = auth()->user()->courier;

        $with = ['order.user', 'order.items.book.seller'];

        // Available deliveries (not yet accepted by anyone)
        $available = Delivery::where('status', 'available')
            ->with($with)->latest()->get();

        // Items to pick up from the seller (accepted but not yet collected)
        $forPickup = Delivery::where('courier_id', $courier->id)
            ->where('status', 'accepted')
            ->with($with)->latest()->get();

        // Items already picked up and heading to / arriving at the buyer
        $forDelivery = Delivery::where('courier_id', $courier->id)
            ->whereIn('status', ['picked_up', 'in_transit'])
            ->with($with)->latest()->get();

        // Today's completed
        $todayDone = Delivery::where('courier_id', $courier->id)
            ->where('status', 'delivered')
            ->whereDate('delivered_at', today())
            ->count();

        return view('courier.dashboard', compact(
            'courier', 'available', 'forPickup', 'forDelivery', 'todayDone'
        ));
    }

    /** Courier notifications feed. Marks them read on view. */
    public function notifications()
    {
        $user = auth()->user();

        $notifications = $user->notifications()->latest()->get();

        $user->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return view('courier.notifications', compact('notifications'));
    }
}
