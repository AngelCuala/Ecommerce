<?php

namespace App\Http\Controllers\SortingCenter;

use App\Http\Controllers\Controller;
use App\Models\HubRemittance;
use App\Services\HubRemittances;
use Illuminate\Http\Request;

/** ALVY Remittance: the item price the hub holds goes to ALVY with a report of each delivery (App\Services\HubRemittances). */
class AlvyRemittanceController extends Controller
{
    public function __construct(private HubRemittances $remit) {}

    public function index(Request $request)
    {
        $ready = $this->remit->ready((int) auth()->id());
        // History: only this company's remittances; filter by status and dates.
        $request->validate(['status' => 'nullable|in:submitted,confirmed,flagged', 'from' => 'nullable|date', 'to' => 'nullable|date']);
        $tz = config('app.business_timezone');
        $sent = HubRemittance::where('sorting_center_id', auth()->id())->withCount('items')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->from, fn ($q, $d) => $q->where('created_at', '>=', \Carbon\Carbon::parse($d, $tz)->startOfDay()->utc()))
            ->when($request->to, fn ($q, $d) => $q->where('created_at', '<=', \Carbon\Carbon::parse($d, $tz)->endOfDay()->utc()))
            ->latest('id')->paginate(20)->withQueryString();

        return view('sc.alvy-remittances', compact('ready', 'sent'));
    }

    public function show(HubRemittance $remittance)
    {
        abort_unless((int) $remittance->sorting_center_id === (int) auth()->id(), 403);
        $remittance->load('items', 'confirmer', 'submitter');

        return view('sc.alvy-remittance-show', ['r' => $remittance]);
    }

    /** deposit_reference: the hub's deposit to ALVY's bank (not needed when ALVY owes the hub). */
    public function submit(Request $request)
    {
        $data = $request->validate(['deposit_reference' => 'nullable|string|min:4|max:60']);
        try {
            $r = $this->remit->submit(auth()->user(), auth()->user(), isset($data['deposit_reference']) ? trim($data['deposit_reference']) : null);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('sc.alvy-remittances.show', $r)->with('success', "Remittance {$r->reference()} sent to ALVY with " . $r->items()->count() . ' line(s).');
    }
}
