<?php

namespace App\Http\Controllers\Admin\Logistics;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\Parcel;
use Illuminate\Http\Request;

class ParcelController extends Controller
{
    public function sorting(Request $request)
    {
        $areas   = DeliveryArea::withCount(['parcels' => fn ($q) => $q->where('status', 'sorted')])->orderBy('name')->get();
        $pending = Parcel::with('area')
            ->where('status', 'picked_up')
            ->when($request->search, fn ($q) => $q->where(function ($q2) use ($request) {
                $q2->where('tracking_number', 'like', "%{$request->search}%")
                   ->orWhere('receiver_name', 'like', "%{$request->search}%");
            }))
            ->latest()
            ->get();

        $sortedToday = Parcel::where('status', 'sorted')
            ->whereDate('updated_at', today())->count();

        $view = request()->routeIs('admin.*') ? 'admin.parcels.sorting' : 'logistics.parcels.sorting';
        return view($view, compact('areas', 'pending', 'sortedToday'));
    }

    public function index(Request $request)
    {
        $parcels = Parcel::with(['seller', 'area'])
            ->whereIn('status', ['picked_up', 'sorted', 'assigned', 'in_transit'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where('tracking_number', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $areas = DeliveryArea::orderBy('name')->get();
        $view  = request()->routeIs('admin.*') ? 'admin.parcels.index' : 'logistics.parcels.index';
        return view($view, compact('parcels', 'areas'));
    }

    /** Admin override: record that the hub has the parcel. Needs a reason; it is kept on the tracking event. */
    public function markPickedUp(Request $request, Parcel $parcel)
    {
        $data = $request->validate(['reason' => 'required|string|min:10|max:300'], [
            'reason.required' => 'This is an override: give the reason (10 to 300 characters).',
            'reason.min'      => 'The reason must be 10 to 300 characters.',
        ]);
        if (! in_array($parcel->status, ['pickup_approved', 'in_transit_to_hub'], true)) {
            return back()->with('error', "{$parcel->tracking_number} is {$parcel->statusLabel()}; only a parcel on its way to the hub can be marked received.");
        }
        $parcel->withTracking(['note' => 'Administrator override: ' . $data['reason']])
            ->update(['status' => 'picked_up', 'received_at' => now(), 'received_by' => auth()->id()]);
        \App\Models\ActivityLog::note('Admin override: ' . $parcel->tracking_number . ' marked received. ' . $data['reason'], $parcel);

        return back()->with('success', "{$parcel->tracking_number} marked as received at the hub.");
    }

    public function sort(Request $request, Parcel $parcel)
    {
        $data = $request->validate([
            'area_id' => 'required|exists:delivery_areas,id',
            'reason'  => 'required|string|min:10|max:300',
        ], ['reason.required' => 'This is an override: give the reason (10 to 300 characters).']);
        if (! in_array($parcel->status, ['picked_up', 'sorting'], true)) {
            return back()->with('error', "{$parcel->tracking_number} is {$parcel->statusLabel()}; only a received parcel can be sorted.");
        }
        $parcel->withTracking(['note' => 'Administrator override: ' . $data['reason']])
            ->update(['area_id' => $data['area_id'], 'status' => 'sorted', 'sorted_at' => now(), 'sorted_by' => auth()->id()]);

        return back()->with('success', "{$parcel->tracking_number} has been sorted.");
    }

    public function show(Parcel $parcel)
    {
        $parcel->load(['seller', 'area', 'parcelDelivery.rider']);
        $view = request()->routeIs('admin.*') ? 'admin.parcels.show' : 'logistics.parcels.show';
        return view($view, compact('parcel'));
    }
}
