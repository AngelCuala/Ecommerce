<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\Delivery;

class DashboardController extends Controller
{
    public function index()
    {
        // A rider of a logistics company without a direct-courier profile works from Hub Jobs.
        $c = auth()->user()->courier;
        if ((! $c || ! $c->isApproved()) && \App\Models\Rider::where('user_id', auth()->id())->where('application_status', 'approved')->exists()) {
            return redirect()->route('courier.rider');
        }
        $courier = DeliveryController::currentCourier();

        $with = ['order.user', 'order.items.product.seller'];

        // Available deliveries (not yet accepted by anyone, order still live)
        $available = Delivery::where('status', 'available')
            ->whereNull('courier_id')
            ->whereHas('order', fn ($q) => $q->whereNotIn('status', \App\Models\Order::LOGISTICS_DONE))
            ->whereDoesntHave('order.parcels', fn ($q) => $q->whereNotIn('status', \App\Models\Parcel::CLOSED_STATUSES))
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

        // Same total as My Earnings: logistics-company jobs at the rider rates plus older courier jobs.
        $rider = \App\Models\Rider::where('user_id', auth()->id())->where('application_status', 'approved')->first();
        $totalEarnings = app(ProfitController::class)->totalEarned(app(\App\Services\RiderWallet::class), $rider, $courier);

        return view('courier.dashboard', compact(
            'courier', 'available', 'forPickup', 'forDelivery', 'todayDone', 'totalEarnings'
        ));
    }

    /** Courier notifications feed. Marks them read on view. */
    public function notifications()
    {
        $user = auth()->user();

        $notifications = $user->notifications()->latest()->paginate(30);
        $unread = $user->notifications()->whereNull('read_at')->count();

        return view('courier.notifications', compact('notifications', 'unread'));
    }

    /** Open one notification: it is marked read, then its page opens. Only the user's own. */
    public function openNotification(int $id)
    {
        $note = auth()->user()->notifications()->findOrFail($id);
        if ($note->isUnread()) {
            $note->update(['read_at' => now()]);
        }

        return $note->link ? redirect($note->link) : redirect()->route('courier.notifications');
    }

    public function readAllNotifications()
    {
        auth()->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
