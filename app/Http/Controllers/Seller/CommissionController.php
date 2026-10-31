<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\SellerCommission;
use App\Support\DateRange;
use Illuminate\Http\Request;

/**
 * Commission details: every line of the seller's orders with the rate stored at purchase, the
 * commission, and the net earning, filtered by period and commission status. Only the signed-in
 * seller's own lines are ever loaded.
 */
class CommissionController extends Controller
{
    public function index(Request $request, SellerCommission $commission)
    {
        $range = DateRange::fromRequest($request, 'month');
        $request->validate(['status' => 'nullable|in:' . implode(',', array_keys(SellerCommission::STATUSES)), 'export' => 'nullable|in:pdf,csv,xlsx']);
        $status   = $request->input('status');
        $sellerId = (int) auth()->id();

        // Totals for the whole period (all statuses), so the cards don't change with the status filter.
        $summary = $commission->summarize($commission->lines($sellerId, $range->bounds())->get());

        $lines = $commission->lines($sellerId, $range->bounds());
        if ($status) {
            $commission->whereStatus($lines, $status);
        }
        $lines->orderByDesc('order_id')->orderBy('id');

        // The export is always a PDF (older csv / xlsx links get the PDF too), with the lines on screen.
        if ($request->filled('export')) {
            $rows = $lines->get()->map(fn ($i) => [
                'order' => $i->order?->number(), 'placed' => $i->order?->created_at,
                'product' => $i->product->title ?? 'Removed product', 'option' => $i->variation_name, 'qty' => (int) $i->quantity,
                'gross' => SellerCommission::gross($i), 'rate' => (float) $i->commission_rate,
                'commission' => (float) $i->commission_amount, 'net' => (float) $i->seller_earning,
                'status' => $commission->statusOf($i),
            ]);
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('seller.commissions.pdf', [
                'rows' => $rows, 'summary' => $summary, 'range' => $range, 'status' => $status,
                'store' => auth()->user()->storeName(), 'generatedAt' => now(),
            ])->setPaper('a4', 'landscape')
                ->setOption('isFontSubsettingEnabled', true)   // embed only the glyphs used
                ->download('commission_' . $range->from->toDateString() . '_to_' . $range->to->toDateString() . '.pdf');
        }

        $items = $lines->paginate(25)->withQueryString();
        $configuredRate = config('marketplace.commission_rate');

        return view('seller.commissions.index', compact('range', 'status', 'summary', 'items', 'configuredRate', 'commission'));
    }
}
