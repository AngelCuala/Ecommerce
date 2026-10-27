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
        $request->validate(['status' => 'nullable|in:' . implode(',', array_keys(SellerCommission::STATUSES)), 'export' => 'nullable|in:csv,xlsx']);
        $status   = $request->input('status');
        $sellerId = (int) auth()->id();

        // Totals for the whole period (all statuses), so the cards don't change with the status filter.
        $summary = $commission->summarize($commission->lines($sellerId, $range->bounds())->get());

        $lines = $commission->lines($sellerId, $range->bounds());
        if ($status) {
            $commission->whereStatus($lines, $status);
        }
        $lines->orderByDesc('order_id')->orderBy('id');

        if ($request->filled('export')) {
            $rows = $lines->get()->map(fn ($i) => [
                $i->order?->number(), $i->order?->created_at?->format('Y-m-d H:i'), $i->product->title ?? 'Removed product', $i->variation_name,
                $i->quantity, SellerCommission::gross($i), (float) $i->commission_rate, (float) $i->commission_amount, (float) $i->seller_earning,
                SellerCommission::STATUSES[$commission->statusOf($i)],
            ]);
            return \App\Support\TableExport::download('commission_' . $range->from->toDateString() . '_to_' . $range->to->toDateString(),
                ['Order', 'Placed', 'Product', 'Option', 'Qty', 'Gross (PHP)', 'Rate (%)', 'Commission (PHP)', 'Net (PHP)', 'Status'], $rows, $request->export);
        }

        $items = $lines->paginate(25)->withQueryString();
        $configuredRate = config('marketplace.commission_rate');

        return view('seller.commissions.index', compact('range', 'status', 'summary', 'items', 'configuredRate', 'commission'));
    }
}
