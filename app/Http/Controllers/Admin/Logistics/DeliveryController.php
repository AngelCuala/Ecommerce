<?php

namespace App\Http\Controllers\Admin\Logistics;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\Rider;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function assignmentIndex(Request $request)
    {
        $parcels = Parcel::with('area')
            ->where('status', 'sorted')
            ->when($request->area_id, fn ($q) => $q->where('area_id', $request->area_id))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $areas = DeliveryArea::orderBy('name')->get();

        $riders = Rider::where('application_status', 'approved')
            ->where('is_active', true)
            ->when($request->area_id, fn ($q) => $q->where('area_id', $request->area_id))
            ->with('area')
            ->get();

        $view = request()->routeIs('admin.*') ? 'admin.deliveries.assign' : 'logistics.deliveries.assign';
        return view($view, compact('parcels', 'areas', 'riders'));
    }

    public function assign(Request $request, Parcel $parcel)
    {
        $request->validate(['rider_id' => 'required|exists:riders,id']);

        $rider = Rider::findOrFail($request->rider_id);

        ParcelDelivery::create([
            'parcel_id'   => $parcel->id,
            'rider_id'    => $rider->id,
            'area_id'     => $parcel->area_id,
            'assigned_by' => auth()->id(),
            'status'      => 'assigned',
        ]);

        $parcel->update(['status' => 'assigned']);

        return back()->with('success', "{$parcel->tracking_number} assigned to {$rider->full_name}.");
    }

    public function monitor(Request $request)
    {
        $deliveries = ParcelDelivery::with(['parcel', 'rider', 'area'])
            ->when($request->status,   fn ($q) => $q->where('status', $request->status))
            ->when($request->rider_id, fn ($q) => $q->where('rider_id', $request->rider_id))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $riders = Rider::where('application_status', 'approved')->get();
        $view   = request()->routeIs('admin.*') ? 'admin.deliveries.monitor' : 'logistics.deliveries.monitor';
        return view($view, compact('deliveries', 'riders'));
    }

    public function updateStatus(Request $request, ParcelDelivery $delivery)
    {
        $request->validate([
            'status'  => 'required|in:assigned,out_for_delivery,delivered,failed,returned',
            'remarks' => 'nullable|string|max:500',
        ]);

        $delivery->update([
            'status'       => $request->status,
            'remarks'      => $request->remarks,
            'delivered_at' => $request->status === 'delivered' ? now() : $delivery->delivered_at,
        ]);

        match ($request->status) {
            'delivered'        => $delivery->parcel->update(['status' => 'delivered']),
            'out_for_delivery' => $delivery->parcel->update(['status' => 'in_transit']),
            'failed'           => $delivery->parcel->update(['status' => 'failed']),
            default            => null,
        };

        return back()->with('success', 'Delivery status updated.');
    }
}
