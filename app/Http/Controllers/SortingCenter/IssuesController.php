<?php

namespace App\Http\Controllers\SortingCenter;

use App\Http\Controllers\Controller;
use App\Models\ParcelDelivery;
use App\Models\ShipmentException;
use Illuminate\Http\Request;

/**
 * Problems to look at, each on its own page:
 *  - Failed Deliveries: every failed delivery attempt of this hub's riders, with the reason and what happened next
 *    (buyer not home → rescheduled automatically; wrong address → Return to Seller; refused / 3rd try → cancelled);
 *  - Flagged Parcels: parcels flagged at the hub (damaged, missing, wrong center…). They are on hold until resolved.
 */
class IssuesController extends Controller
{
    private function me(): int { return (int) auth()->id(); }

    public function failed(Request $request)
    {
        $reason = $request->input('reason');
        $failed = ParcelDelivery::where('status', 'failed')
            ->whereHas('parcel', fn ($p) => $p->where('current_sorting_center_id', $this->me()))
            ->when(in_array($reason, ParcelDelivery::FAILURE_REASONS, true), fn ($q) => $q->where('failure_reason', $reason))
            ->with(['parcel.order', 'parcel.seller', 'parcel.area', 'rider'])
            ->latest('updated_at')->paginate(25)->withQueryString();

        $counts = ParcelDelivery::where('status', 'failed')
            ->whereHas('parcel', fn ($p) => $p->where('current_sorting_center_id', $this->me()))
            ->selectRaw('failure_reason, COUNT(*) as n')->groupBy('failure_reason')->pluck('n', 'failure_reason');
        // Parcels still waiting for a decision (a logistics override recorded a failure without the automatic step).
        $waiting = \App\Models\Parcel::where('current_sorting_center_id', $this->me())->where('status', 'failed')->count();

        return view('sc.failed-deliveries', compact('failed', 'counts', 'reason', 'waiting'));
    }

    public function flagged()
    {
        $me = $this->me();
        $scope = fn ($q) => $q->where(fn ($w) => $w->where('sorting_center_id', $me)
            ->orWhereHas('parcel', fn ($p) => $p->where('current_sorting_center_id', $me)));

        $open = ShipmentException::where('status', 'open')->where($scope)
            ->with(['parcel.order', 'parcel.seller', 'reporter'])->latest('id')->get();
        $resolved = ShipmentException::where('status', 'resolved')->where($scope)
            ->with(['parcel', 'resolver'])->latest('resolved_at')->limit(20)->get();

        return view('sc.flagged', compact('open', 'resolved'));
    }
}
