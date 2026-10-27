<?php

namespace App\Http\Controllers\Admin\Logistics;

use App\Http\Controllers\Controller;
use App\Models\ParcelDelivery;
use App\Models\Parcel;
use App\Models\Rider;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from)            : now()->subDays(30);
        $to   = $request->to   ? Carbon::parse($request->to)->endOfDay()  : now()->endOfDay();

        $summary = [
            'total_parcels'     => Parcel::whereBetween('created_at', [$from, $to])->count(),
            'delivered'         => ParcelDelivery::where('status', 'delivered')
                                      ->whereBetween('delivered_at', [$from, $to])->count(),
            'failed'            => ParcelDelivery::where('status', 'failed')
                                      ->whereBetween('updated_at', [$from, $to])->count(),
            'new_rider_signups' => Rider::whereBetween('created_at', [$from, $to])->count(),
        ];

        $deliveries = ParcelDelivery::with(['parcel', 'rider', 'area'])
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $view = 'logistics.reports.index'; // admin.reports.index is the main admin sales report
        return view($view, compact('summary', 'deliveries', 'from', 'to'));
    }

    public function export(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from)           : now()->subDays(30);
        $to   = $request->to   ? Carbon::parse($request->to)->endOfDay() : now()->endOfDay();

        $deliveries = ParcelDelivery::with(['parcel', 'rider', 'area'])
            ->whereBetween('created_at', [$from, $to])
            ->get();

        $rows = $deliveries->map(fn ($d) => [
            $d->parcel->tracking_number ?? '-',
            $d->rider->full_name        ?? '-',
            $d->area->name              ?? '-',
            $d->status,
            (string) $d->created_at,
            (string) $d->delivered_at,
        ])->all();
        $format = in_array($request->query('format'), \App\Support\TableExport::FORMATS, true) ? $request->query('format') : 'csv';

        return \App\Support\TableExport::download('delivery-report-' . now()->format('Y-m-d'),
            ['Tracking #', 'Rider', 'Area', 'Status', 'Assigned At', 'Delivered At'], $rows, $format,
            'Delivery Report', $from->format('M d, Y') . ' – ' . $to->format('M d, Y'));
    }
}
