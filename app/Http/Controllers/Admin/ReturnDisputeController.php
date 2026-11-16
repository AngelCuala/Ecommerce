<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\ReturnShipment;
use App\Services\ReturnDecisions;
use Illuminate\Http\Request;

/**
 * Return Disputes: returns the seller rejected and the buyer escalated to ALVY. The admin sees the whole case (order,
 * items, the buyer's photos and reason, the seller's rejection, the buyer's dispute note, the timeline) and decides:
 * refund the buyer (overrides the seller) or close it without a refund. Both sides are told, with the admin's reason.
 */
class ReturnDisputeController extends Controller
{
    public function __construct(private ReturnDecisions $decisions) {}

    public function index(Request $request)
    {
        $tab = $request->query('tab') === 'decided' ? 'decided' : 'open';
        $search = trim((string) $request->query('q'));

        $query = ReturnRequest::with(['order', 'user', 'seller'])->whereNotNull('escalated_at')
            ->when($tab === 'open', fn ($q) => $q->where('status', 'disputed')->oldest('escalated_at'))
            ->when($tab === 'decided', fn ($q) => $q->where('status', '!=', 'disputed')->latest('admin_decided_at'))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                ->orWhereHas('order', fn ($o) => $o->where('id', (int) ltrim(preg_replace('/\D/', '', $search), '0') ?: 0))));

        $disputes = $query->paginate(20)->withQueryString();
        $counts = [
            'open'    => ReturnDecisions::openDisputes()->count(),
            'decided' => ReturnRequest::whereNotNull('escalated_at')->where('status', '!=', 'disputed')->count(),
        ];

        return view('admin.disputes.index', compact('disputes', 'counts', 'tab', 'search'));
    }

    public function show(ReturnRequest $return)
    {
        abort_unless($return->escalated_at, 404);
        $return->load(['order.items.product', 'order.events', 'user', 'seller']);
        $order = $return->order;
        $items = $return->isPartial() ? $order->items->whereIn('id', $return->item_ids) : $order->items;
        $shipment = ReturnShipment::where('return_request_id', $return->id)->latest('id')->first();
        $decider = $return->admin_decided_by ? \App\Models\User::find($return->admin_decided_by) : null;
        $sellerName = $return->seller?->storeName() ?? $return->seller?->name ?? 'the seller';

        return view('admin.disputes.show', compact('return', 'order', 'items', 'shipment', 'decider', 'sellerName'));
    }

    public function decide(Request $request, ReturnRequest $return)
    {
        $data = $request->validate([
            'decision' => 'required|in:refund,close',
            'reason'   => 'required|string|min:10|max:500',
        ], [
            'reason.required' => 'Write the reason for your decision. The buyer and the seller see it.',
            'reason.min'      => 'The reason must be 10 to 500 characters.',
            'reason.max'      => 'The reason must be 10 to 500 characters.',
        ]);

        try {
            $message = $this->decisions->decideDispute($return, $request->user(), $data['decision'] === 'refund', trim($data['reason']));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.disputes.show', $return)->with('success', $message . ' The buyer and the seller were told.');
    }
}
