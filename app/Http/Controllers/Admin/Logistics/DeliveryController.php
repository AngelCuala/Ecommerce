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
        $request->validate(['rider_id' => 'required|exists:riders,id'], ['rider_id.required' => 'Select a rider for this parcel.']);

        $rider = Rider::findOrFail($request->rider_id);
        if ($parcel->status !== 'sorted' || $parcel->deliveries()->whereIn('status', ParcelDelivery::OPEN)->exists()) {
            return back()->with('error', "{$parcel->tracking_number} is not waiting for a delivery rider ({$parcel->statusLabel()}).");
        }
        if (! $rider->isApproved() || ! $rider->is_active) {
            return back()->with('error', "{$rider->full_name} is not an active rider.");
        }
        if ($parcel->current_sorting_center_id && (int) $rider->sorting_center_id !== (int) $parcel->current_sorting_center_id) {
            return back()->with('error', "{$rider->full_name} works for another logistics company than the one holding this parcel.");
        }

        ParcelDelivery::create(app(\App\Services\RiderDispatch::class)->deliveryJob($parcel) + [
            'parcel_id'   => $parcel->id,
            'rider_id'    => $rider->id,
            'area_id'     => $parcel->area_id,
            'assigned_by' => auth()->id(),
            'status'      => 'assigned',
        ]);

        $parcel->update(['status' => 'assigned']);
        app(\App\Services\ParcelService::class)->syncOrder($parcel->fresh());
        \App\Models\ActivityLog::note($parcel->tracking_number . ' delivery assigned to ' . $rider->full_name, $parcel);

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
        // Same rules as the logistics company's override (A10, A35): forward only, with a logged reason.
        $allowed = \App\Services\ParcelService::DELIVERY_FLOW[$delivery->status] ?? [];
        if (! $allowed) {
            return back()->with('error', 'This delivery is already ' . $delivery->statusLabel() . ', so its status can\'t be changed.');
        }
        $data = $request->validate([
            'status'  => ['required', \Illuminate\Validation\Rule::in($allowed)],
            'remarks' => 'required|string|min:10|max:300',
        ], [
            'status.in'        => 'That status change is not allowed for this delivery.',
            'remarks.required' => 'This is an override: give the reason (10 to 300 characters).',
            'remarks.min'      => 'The reason must be 10 to 300 characters.',
            'remarks.max'      => 'The reason must be 10 to 300 characters.',
        ]);

        \App\Models\ActivityLog::change($delivery->statusLabel(), ParcelDelivery::STATUS_LABELS[$data['status']] ?? $data['status'], $data['remarks']);
        $parcel = app(\App\Services\ParcelService::class)->overrideDelivery($delivery, $data['status'], $data['remarks']);
        \App\Models\ActivityLog::note('Admin override on ' . $parcel->tracking_number, $parcel);

        return back()->with('success', "{$parcel->tracking_number}: " . $parcel->statusLabel() . '.');
    }
}
