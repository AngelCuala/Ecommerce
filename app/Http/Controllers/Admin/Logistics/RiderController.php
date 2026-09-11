<?php

namespace App\Http\Controllers\Admin\Logistics;

use App\Http\Controllers\Controller;
use App\Models\DeliveryArea;
use App\Models\Rider;
use Illuminate\Http\Request;

class RiderController extends Controller
{
    public function index(Request $request)
    {
        $riders = Rider::with(['user', 'area'])
            ->when($request->status,  fn ($q) => $q->where('application_status', $request->status))
            ->when($request->search,  fn ($q) => $q->where('full_name', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $view = request()->routeIs('admin.*') ? 'admin.riders.index' : 'logistics.riders.index';
        return view($view, compact('riders'));
    }

    public function show(Rider $rider)
    {
        $rider->load(['user', 'area', 'parcelDeliveries.parcel']);
        $view = request()->routeIs('admin.*') ? 'admin.riders.show' : 'logistics.riders.show';
        return view($view, compact('rider'));
    }

    public function approve(Rider $rider)
    {
        $rider->update([
            'application_status' => 'approved',
            'approved_at'        => now(),
            'approved_by'        => auth()->id(),
            'is_active'          => true,
        ]);

        return back()->with('success', "{$rider->full_name}'s application has been approved.");
    }

    public function disapprove(Request $request, Rider $rider)
    {
        $request->validate(['rejection_reason' => 'nullable|string|max:500']);

        $rider->update([
            'application_status' => 'rejected',
            'rejection_reason'   => $request->rejection_reason,
            'is_active'          => false,
        ]);

        return back()->with('success', "{$rider->full_name}'s application has been disapproved.");
    }

    public function toggleActive(Rider $rider)
    {
        if ($rider->application_status !== 'approved') {
            return back()->with('error', 'Only approved riders can be activated or deactivated.');
        }

        $rider->update(['is_active' => ! $rider->is_active]);
        $state = $rider->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "{$rider->full_name} has been {$state}.");
    }
}
