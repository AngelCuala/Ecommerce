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

    public function markPickedUp(Parcel $parcel)
    {
        $parcel->update(['status' => 'picked_up']);
        return back()->with('success', "{$parcel->tracking_number} marked as picked up.");
    }

    public function sort(Request $request, Parcel $parcel)
    {
        $request->validate(['area_id' => 'required|exists:delivery_areas,id']);

        $parcel->update([
            'area_id' => $request->area_id,
            'status'  => 'sorted',
        ]);

        return back()->with('success', "{$parcel->tracking_number} has been sorted.");
    }

    public function show(Parcel $parcel)
    {
        $parcel->load(['seller', 'area', 'parcelDelivery.rider']);
        $view = request()->routeIs('admin.*') ? 'admin.parcels.show' : 'logistics.parcels.show';
        return view($view, compact('parcel'));
    }
}
