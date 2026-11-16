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
        // export: always a PDF (older csv / xlsx links get the PDF too).
        $request->validate(['status' => 'nullable|in:' . implode(',', array_keys(SortingCenterCommission::STATUSES)), 'export' => 'nullable|in:pdf,csv,xlsx']);
        $status = $request->input('status');

        $all = $commission->lines((int) auth()->id(), $range->bounds());
        $summary = $commission->summarize($all);   // whole period, so the cards don't change with the status filter
        $lines = $status ? $all->where('status', $status)->values() : $all;

        if ($request->filled('export')) {
            return $this->pdf($lines, $summary, $range, $status);
        }

        $page = LengthAwarePaginator::resolveCurrentPage();
        $items = new LengthAwarePaginator($lines->forPage($page, 25)->values(), $lines->count(), 25, $page,
            ['path' => $request->url(), 'query' => $request->query()]);

        return view('sc.commissions', [
            'range' => $range, 'status' => $status, 'summary' => $summary, 'items' => $items, 'rate' => SortingCenterCommission::rate(),
        ]);
    }

    /** The same lines and totals as the page, as a PDF (sc.reports-pdf). */
    private function pdf($lines, array $summary, DateRange $range, ?string $status)
    {
        $rate = SortingCenterCommission::rate();
        $peso = fn ($v) => ($v < 0 ? '−₱' : '₱') . number_format(abs((float) $v), 2);
        $withCommission = $rate > 0;

        $header = ['Parcel', 'Order', 'Handled', 'Shipping fee', 'Your share', ...($withCommission ? ['ALVY commission'] : []), 'Rider pay', 'Net', 'Status'];
        $rows = $lines->map(fn ($l) => [
            $l['parcel']->tracking_number, $l['parcel']->order?->number() ?? '—', $l['role'] . ($l['hubs'] > 1 ? " ({$l['hubs']} hubs)" : ''),
            $peso($l['fee']), $peso($l['share']), ...($withCommission ? [$peso(-$l['commission'])] : []), $peso($l['rider_pay']), $peso($l['net']),
            SortingCenterCommission::STATUSES[$l['status']],
        ]);
        $totals = ['Total', $lines->count() . ' ' . \Illuminate\Support\Str::plural('parcel', $lines->count()), '', $peso($lines->sum('fee')), $peso($lines->sum('share')),
            ...($withCommission ? [$peso(-$lines->sum('commission'))] : []), $peso($lines->sum('rider_pay')), $peso($lines->sum('net')), ''];
        $right = $withCommission ? [3, 4, 5, 6, 7] : [3, 4, 5, 6];

        $pct = rtrim(rtrim(number_format($rate, 2), '0'), '.') . '%';
        $name = sprintf('sc-commission_%s_to_%s.pdf', $range->from->toDateString(), $range->to->toDateString());

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('sc.reports-pdf', [
            'title' => 'Commission & Earnings' . ($status ? ' · ' . SortingCenterCommission::STATUSES[$status] : ''),
            'header' => $header, 'rows' => $rows->values()->all(), 'totals' => $rows->isEmpty() ? null : $totals, 'right' => $right,
            'summary' => [
                'Your share of shipping fees' => $peso($summary['fees']),
                ...($withCommission ? ["ALVY commission ($pct)" => $peso(-$summary['commission'])] : []),
                'Net earnings' => $peso($summary['net']),
                'Earned · pending' => $peso($summary['earned_net']) . ' · ' . $peso($summary['pending_net']),
            ],
            'notes' => [
                'Shipping fee split: 30% pickup rider, 30% delivery rider, 40% hubs (shared equally by every hub the parcel passed through).',
                $withCommission ? "ALVY keeps $pct of your share. Net = your share − ALVY commission." : 'ALVY takes no part of the shipping fee. Net = your share.',
                'Parcels from before ' . \App\Services\RiderDispatch::shareSince()->format('M j, Y') . ' use the old rule: the whole fee is shared, and your riders\' pay (₱30 a pickup, ₱50 a delivery) is taken from net.',
                'Pending amounts can still change (for example when another hub joins after a transfer). Cancelled and refunded orders earn no fee.',
            ],
            'from' => $range->from, 'to' => $range->to, 'center' => \App\Support\SortingCenters::nameOf(auth()->user()),
            'generatedAt' => now(), 'generatedBy' => auth()->user()?->name,
        ])->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true)->download($name);
    }
}
