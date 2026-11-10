<?php

namespace App\Http\Controllers\SortingCenter;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Courier\RiderController;
use App\Models\Parcel;
use App\Models\ParcelPickup;
use App\Models\Rider;
use App\Models\RiderJobOffer;
use App\Models\RiderRemittance;
use App\Models\UserNotification;
use App\Services\RiderDispatch;
use App\Services\RiderWallet;
use Illuminate\Http\Request;

/**
 * The logistics company's side of rider work: dispatch (Auto-assign to each barangay's rider for pickups and
 * deliveries, manual assignment and reassignment), rider records and documents, COD remittances.
 */
class RiderWorkController extends Controller
{
    public function __construct(private RiderDispatch $dispatch, private RiderWallet $wallet) {}

    private function scId(): int
    {
        return (int) auth()->id();
    }

    /** Jobs waiting for a rider: pickups to collect from sellers and deliveries to buyers. */
    public function dispatchBoard()
    {
        $this->dispatch->expireStale();

        $eligibleNow = $this->dispatch->eligibleRiders($this->scId());
        $parcels = $this->dispatch->waiting($this->scId())
            ->map(function ($p) use ($eligibleNow) {
                $p->job_kind    = $this->dispatch->jobKind($p);
                $p->job_offer   = $this->dispatch->pendingOffer($p)?->load('rider');
                $p->unassigned  = $this->dispatch->isUnassigned($p);
                // Who Auto-assign would give it to: the Online rider covering the job's barangay (null: assign manually).
                $p->barangay_rider = $this->dispatch->barangayRider($p, $p->job_kind, $eligibleNow);
                return $p;
            })->values();

        // 4-wheel riders do hub-to-hub trips (Linehaul Trips), so they are not offered pickups or deliveries here.
        $riders = Rider::where('sorting_center_id', $this->scId())->where('application_status', 'approved')->with('area')->orderBy('full_name')->get()
            ->reject(fn (Rider $r) => $r->isTruck())->values()
            ->each(function ($r) {
                $r->active_jobs = $r->activeJobsCount();
                $r->online_now  = $r->isOnlineNow();
            });
        $eligible = $this->dispatch->eligibleRiders($this->scId());
        // Every rider of this hub for the "Choose rider" lists: available ones to pick, the rest shown with why not.
        $riderChoices = $this->dispatch->riderChoices($this->scId());
        $withRiders = ParcelPickup::where('sorting_center_id', $this->scId())->whereIn('status', ParcelPickup::OPEN)->with('parcel', 'rider')->latest()->get();
        // L3: jobs given to a rider who hasn't started them yet — these can still be reassigned.
        $mine = fn ($q) => $q->where('current_sorting_center_id', $this->scId());
        $notStarted = ParcelPickup::where('status', 'assigned')->whereHas('parcel', $mine)->with('parcel', 'rider')->get()
            ->map(fn ($j) => (object) ['kind' => 'pickup', 'parcel' => $j->parcel, 'rider' => $j->rider, 'since' => $j->created_at])
            ->concat(\App\Models\ParcelDelivery::where('status', 'assigned')->whereHas('parcel', $mine)->with('parcel', 'rider')->get()
                ->map(fn ($j) => (object) ['kind' => 'delivery', 'parcel' => $j->parcel, 'rider' => $j->rider, 'since' => $j->created_at]))
            ->sortBy('since')->values();

        return view('sc.dispatch', [
            'waitingPickups'    => $parcels->where('job_kind', 'pickup')->count(),
            'waitingDeliveries' => $parcels->where('job_kind', 'delivery')->count(),
            'readyPickups'      => $parcels->where('job_kind', 'pickup')->filter(fn ($p) => $p->barangay_rider)->count(),
            'readyDeliveries'   => $parcels->where('job_kind', 'delivery')->filter(fn ($p) => $p->barangay_rider)->count(),
            'jobs'       => $parcels->reject->unassigned->values(),
            'unassigned' => $parcels->filter->unassigned->values(),
            'riders'     => $riders,
            'eligible'   => $eligible,
            'riderChoices' => $riderChoices,
            'withRiders' => $withRiders,
            'notStarted' => $notStarted,
        ]);
    }

    /** One parcel: its job goes straight to the rider of its barangay. */
    public function autoAssign(Parcel $parcel)
    {
        $this->guard($parcel);
        $kind = $this->dispatch->jobKind($parcel);
        if (! $kind) {
            return back()->with('error', "{$parcel->tracking_number} is not waiting for a rider.");
        }
        try {
            $rider = $this->dispatch->autoAssign($parcel, auth()->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        if (! $rider) {
            return back()->with('error', "No Online rider covers the {$kind} barangay of {$parcel->tracking_number}. Assign a rider manually.");
        }
        \App\Models\ActivityLog::note($parcel->tracking_number . ' ' . $kind . ' auto-assigned to ' . $rider->full_name, $parcel);

        return back()->with('success', "{$parcel->tracking_number} assigned to {$rider->full_name} (barangay rider).");
    }

    /** Every waiting pickup, or every waiting delivery: each to the rider of its barangay. */
    public function autoAssignAll(string $kind)
    {
        $result = $this->dispatch->autoAssignAll(auth()->user(), $kind);
        $what = $kind === 'pickup' ? 'pickup' : 'delivery';
        if ($result['assigned'] === 0 && $result['no_rider'] === 0) {
            return back()->with('error', 'No ' . $what . ' is waiting for a rider.');
        }
        $byRider = collect($result['riders'])->map(fn ($n, $name) => "{$name} ({$n})")->implode(', ');
        if ($result['assigned'] > 0) {
            \App\Models\ActivityLog::note('Auto-assigned ' . $result['assigned'] . ' ' . \Illuminate\Support\Str::plural($what, $result['assigned']) . ': ' . $byRider);
        }
        $message = $result['assigned'] . ' ' . \Illuminate\Support\Str::plural($what, $result['assigned']) . ' assigned'
            . ($byRider ? ': ' . $byRider : '') . '.'
            . ($result['no_rider'] ? ' ' . $result['no_rider'] . ' left: no Online rider covers ' . ($result['no_rider'] === 1 ? 'its barangay' : 'their barangays') . '. Assign ' . ($result['no_rider'] === 1 ? 'it' : 'them') . ' manually.' : '');

        return back()->with($result['assigned'] > 0 ? 'success' : 'error', $message);
    }

    public function manualAssign(Request $request, Parcel $parcel)
    {
        $this->guard($parcel);
        $data = $request->validate(['rider_id' => 'required|integer'], ['rider_id.required' => 'Choose a rider.']);
        $rider = Rider::where('sorting_center_id', $this->scId())->find($data['rider_id']);
        if (! $rider) {
            return back()->with('error', 'Choose one of your own riders.');
        }

        try {
            $kind = $this->dispatch->assignManually($parcel, $rider, auth()->user());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        \App\Models\ActivityLog::note($parcel->tracking_number . ' ' . $kind . ' assigned to ' . $rider->full_name, $parcel);

        return back()->with('success', "{$parcel->tracking_number} assigned to {$rider->full_name}." . ($this->dispatch->lastNotification
            ? ' The rider was notified.'
            : ' The rider could not be notified in the app; please tell them directly.'));
    }

    // ── Rider record ────────────────────────────────────────────

    public function showRider(Rider $rider)
    {
        $this->guardRider($rider);
        $rider->load('area', 'areas', 'user');
        $sc = \App\Models\User::find($this->scId());
        $barangays = $sc?->assigned_municipality_code
            ? collect(app(\App\Services\PsgcDirectory::class)->barangaysByMunicipality($sc->assigned_municipality_code) ?? [])
                ->reject(fn ($b) => $rider->areas->contains(fn ($a) => $a->barangay_code && $a->barangay_code === ($b['code'] ?? null)
                    || strcasecmp($a->name, $b['name'] ?? '') === 0))->values()
            : collect();

        return view('sc.rider-show', ['rider' => $rider, 'wallet' => $this->wallet, 'barangays' => $barangays]);
    }

    public function riderDocument(Rider $rider, string $document)
    {
        $this->guardRider($rider);
        return RiderController::serveDocument($rider, $document);
    }

    /** The new vehicle documents were checked. */
    public function verifyRider(Rider $rider)
    {
        $this->guardRider($rider);
        $rider->update(['needs_reverification' => false]);
        \App\Models\ActivityLog::note($rider->full_name . ' vehicle re-verified (' . $rider->plate_number . ')', $rider);

        return back()->with('success', "{$rider->full_name}'s vehicle documents are verified.");
    }

    // ── COD remittances ─────────────────────────────────────────

    public function remittances(Request $request)
    {
        $request->validate(['date' => 'nullable|date'], ['date.date' => 'Choose a valid date.']);
        $date = $request->filled('date') ? \Carbon\Carbon::parse($request->date) : today();

        $remittances = RiderRemittance::where('sorting_center_id', $this->scId())->with('rider')->latest()->paginate(20);
        $approved = Rider::where('sorting_center_id', $this->scId())->where('application_status', 'approved')->orderBy('full_name')->get();
        $riders = $approved->map(fn ($r) => ['rider' => $r, 'collected' => $this->wallet->codCollected($r), 'balance' => $this->wallet->codBalance($r),
            'pending' => $this->wallet->pendingRemittance($r), 'next' => $this->wallet->toRemit($r)]);

        // L8: the day's reconciliation — COD each rider collected on the date vs what they remitted that day.
        $day = [$date->copy()->startOfDay(), $date->copy()->endOfDay()];
        $daily = $approved->map(function ($r) use ($day) {
            $orders = \App\Models\ParcelDelivery::where('rider_id', $r->id)->where('status', 'delivered')->where('cod_collected', '>', 0)
                ->whereBetween('delivered_at', $day)->with('parcel.order')->orderBy('delivered_at')->get();
            $collected = round((float) $orders->sum('cod_collected'), 2);
            $sent = RiderRemittance::where('rider_id', $r->id)->whereBetween('created_at', $day);
            $remitted  = round((float) (clone $sent)->sum('amount') + (float) $sent->sum('pay_kept'), 2);   // cash + pay the rider kept from it
            return ['rider' => $r, 'orders' => $orders, 'collected' => $collected, 'remitted' => $remitted, 'difference' => round($remitted - $collected, 2)];
        })->filter(fn ($row) => $row['collected'] > 0 || $row['remitted'] > 0)->values();

        $pendingCount = RiderRemittance::where('sorting_center_id', $this->scId())->where('status', 'pending')->count();
        $tab = in_array($request->tab, ['remittances', 'riders', 'daily'], true) ? $request->tab : ($request->filled('date') ? 'daily' : 'remittances');
        $remittances->appends(['tab' => 'remittances']);

        return view('sc.remittances', compact('remittances', 'riders', 'daily', 'date', 'pendingCount', 'tab'));
    }

    /** Only the logistics company confirms a remittance; any shortfall stays on the rider's balance. */
    public function confirmRemittance(RiderRemittance $remittance)
    {
        abort_unless((int) $remittance->sorting_center_id === $this->scId(), 403);
        if ($remittance->status !== 'pending') {
            return back()->with('error', 'This remittance is already confirmed.');
        }
        $remittance->update(['status' => 'confirmed', 'confirmed_by' => $this->scId(), 'confirmed_at' => now()]);
        \App\Models\ActivityLog::change('Pending ₱' . number_format($remittance->amount, 2), 'Confirmed ₱' . number_format($remittance->amount, 2),
            $remittance->shortfall() > 0 ? 'Short by ₱' . number_format($remittance->shortfall(), 2) : null);
        $rider = $remittance->rider;
        UserNotification::create(['user_id' => $rider->user_id, 'title' => 'COD remittance confirmed',
            'body' => 'Your remittance of ₱' . number_format($remittance->amount, 2) . ' was confirmed.'
                . ($remittance->shortfall() > 0 ? ' ₱' . number_format($remittance->shortfall(), 2) . ' is still outstanding.' : ''), 'type' => 'info',
            'link' => route('courier.rider.cod')]);
        \App\Models\ActivityLog::note('Confirmed ₱' . number_format($remittance->amount, 2) . ' COD from ' . $rider->full_name
            . ($remittance->shortfall() > 0 ? ' (short ₱' . number_format($remittance->shortfall(), 2) . ')' : ''), $remittance);

        return back()->with('success', 'Remittance confirmed.' . ($remittance->shortfall() > 0 ? ' Shortfall of ₱' . number_format($remittance->shortfall(), 2) . ' flagged.' : ''));
    }

    private function guard(Parcel $parcel): void
    {
        abort_unless((int) $parcel->current_sorting_center_id === $this->scId(), 403, 'This parcel is handled by another logistics company.');
    }

    private function guardRider(Rider $rider): void
    {
        abort_unless((int) $rider->sorting_center_id === $this->scId(), 403, 'This rider belongs to another logistics company.');
    }
}
