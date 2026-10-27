<?php

namespace App\Http\Controllers\SortingCenter;

use App\Http\Controllers\Controller;
use App\Services\SortingCenterCommission;
use App\Support\DateRange;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Commission and net earnings: each parcel the signed-in hub handled, its share of the shipping fee,
 * ALVY's commission, the hub's rider pay and what is left. Only the signed-in hub's parcels are loaded.
 */
class CommissionController extends Controller
{
    public function index(Request $request, SortingCenterCommission $commission)
    {
        $range = DateRange::fromRequest($request, 'month');
        $request->validate(['status' => 'nullable|in:' . implode(',', array_keys(SortingCenterCommission::STATUSES)), 'export' => 'nullable|in:csv,xlsx']);
        $status = $request->input('status');

        $all = $commission->lines((int) auth()->id(), $range->bounds());
        $summary = $commission->summarize($all);   // whole period, so the cards don't change with the status filter
        $lines = $status ? $all->where('status', $status)->values() : $all;

        if ($request->filled('export')) {
            $rows = $lines->map(fn ($l) => [
                $l['parcel']->tracking_number, $l['parcel']->order?->number(), $l['parcel']->created_at?->format('Y-m-d H:i'), $l['role'],
                $l['fee'], $l['hubs'], $l['share'], SortingCenterCommission::rate(), $l['commission'], $l['rider_pay'], $l['net'],
                SortingCenterCommission::STATUSES[$l['status']],
            ]);
            return \App\Support\TableExport::download('sc_commission_' . $range->from->toDateString() . '_to_' . $range->to->toDateString(),
                ['Tracking', 'Order', 'Created', 'Handled', 'Shipping fee (PHP)', 'Hubs', 'Your share (PHP)', 'Rate (%)',
                 'ALVY commission (PHP)', 'Rider pay (PHP)', 'Net (PHP)', 'Status'], $rows, $request->export);
        }

        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = new LengthAwarePaginator($lines->forPage($page, 25)->values(), $lines->count(), 25, $page,
            ['path' => $request->url(), 'query' => $request->query()]);

        return view('sc.commissions', [
            'range' => $range, 'status' => $status, 'summary' => $summary, 'items' => $items, 'rate' => SortingCenterCommission::rate(),
        ]);
    }
}
