<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HubRemittance;
use App\Services\HubRemittances;
use Illuminate\Http\Request;

/** Hub remittances to ALVY: check each report against its orders and the amount received; confirm or flag (PAYMENTS_SPEC §3.2). */
class HubRemittanceController extends Controller
{
    public function __construct(private HubRemittances $remit) {}

    public function index()
    {
        $waiting = HubRemittance::where('status', 'submitted')->with('sortingCenter')->withCount('items')->oldest()->get();
        $done = HubRemittance::whereIn('status', ['confirmed', 'flagged'])->with('sortingCenter', 'confirmer')->withCount('items')->latest('confirmed_at')->paginate(30);

        return view('admin.hub-remittances.index', compact('waiting', 'done'));
    }

    public function show(HubRemittance $remittance)
    {
        $remittance->load('items.order', 'sortingCenter', 'submitter', 'confirmer');

        return view('admin.hub-remittances.show', ['r' => $remittance]);
    }

    /** received: what arrived in ALVY's bank (negative when ALVY paid the hub). */
    public function review(Request $request, HubRemittance $remittance)
    {
        $data = $request->validate(['received' => 'required|numeric|between:-10000000,10000000'], ['received.required' => 'Enter the amount ALVY received.']);
        try {
            $r = $this->remit->review($remittance, auth()->user(), (float) $data['received']);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with($r->status === 'confirmed' ? 'success' : 'error', $r->status === 'confirmed'
            ? "{$r->reference()} confirmed: every order matches." : "{$r->reference()} flagged: {$r->note}.");
    }
}
