<?php

namespace App\Http\Controllers\Admin\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Parcel;
use Illuminate\Http\Request;

class PickupRequestController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->get('tab', 'all');

        $requests = Parcel::with('seller')
            ->when($tab === 'pending',   fn ($q) => $q->where('status', 'pending_pickup'))
            ->when($tab === 'confirmed', fn ($q) => $q->where('status', 'pickup_approved'))
            ->when($tab === 'approved',  fn ($q) => $q->whereIn('status', ['picked_up','sorted','assigned','in_transit','delivered']))
            ->when($tab === 'rejected',  fn ($q) => $q->where('status', 'pickup_rejected'))
            ->when($tab === 'all',       fn ($q) => $q) // no filter
            ->when($request->search,     fn ($q) => $q->where('tracking_number', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $view = request()->routeIs('admin.*') ? 'admin.pickup-requests.index' : 'logistics.pickup-requests.index';
        return view($view, compact('requests'));
    }

    public function approve(Parcel $parcel)
    {
        $parcel->update([
            'status'      => 'pickup_approved',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        return back()->with('success', "Pickup for {$parcel->tracking_number} has been approved.");
    }

    public function reject(Request $request, Parcel $parcel)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        $parcel->update([
            'status' => 'pickup_rejected',
            'notes'  => trim(($parcel->notes ?? '') . "\nRejection reason: " . $request->reason),
        ]);

        return back()->with('success', "Pickup request for {$parcel->tracking_number} was rejected.");
    }
}
