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
 * The logistics company's side of rider work: dispatch (auto-assign with 60-second offers,
 * manual assignment, the Unassigned queue), rider records and documents, COD remittances.
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

        $parcels = Parcel::where('current_sorting_center_id', $this->scId())
            ->whereIn('status', ['pickup_approved', 'sorted'])->with(['seller', 'order', 'area'])->oldest()->get()
            ->filter(fn ($p) => $this->dispatch->jobKind($p) !== null)
            ->map(function ($p) {
                $p->job_kind    = $this->dispatch->jobKind($p);
                $p->job_offer   = $this->dispatch->pendingOffer($p)?->load('rider');
                $p->unassigned  = $this->dispatch->isUnassigned($p);
                return $p;
            })->values();

        $riders = Rider::where('sorting_center_id', $this->scId())->where('application_status', 'approved')->orderBy('full_name')->get()
            ->each(function ($r) {
                $r->active_jobs = $r->activeJobsCount();
                $r->online_now  = $r->isOnlineNow();
            });
        $eligible = $this->dispatch->eligibleRiders($this->scId());
        $withRiders = ParcelPickup::where('sorting_center_id', $this->scId())->whereIn('status', ParcelPickup::OPEN)->with('parcel', 'rider')->latest()->get();

        return view('sc.dispatch', [
            'jobs'       => $parcels->reject->unassigned->values(),
            'unassigned' => $parcels->filter->unassigned->values(),
            'riders'     => $riders,
            'eligible'   => $eligible,
            'withRiders' => $withRiders,
        ]);
    }

    public function autoAssign(Parcel $parcel)
    {
        $this->guard($parcel);
        if (! $this->dispatch->jobKind($parcel)) {
            return back()->with('error', "{$parcel->tracking_number} is not waiting for a rider.");
        }
        if ($this->dispatch->isUnassigned($parcel)) {
            return back()->with('error', "{$parcel->tracking_number} is in the Unassigned queue: 3 riders declined or let it expire. Assign a rider manually.");
        }
        $offer = $this->dispatch->offer($parcel);

        return back()->with($offer ? 'success' : 'error', $offer
            ? "{$parcel->tracking_number} offered to {$offer->rider->full_name} for " . RiderDispatch::OFFER_SECONDS . ' seconds.'
            : ($this->dispatch->isUnassigned($parcel->fresh())
                ? "{$parcel->tracking_number} moved to the Unassigned queue."
                : 'No rider can take this job right now (none Online with room for another job).'));
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
        UserNotification::create(['user_id' => $rider->user_id, 'title' => 'New ' . $kind . ' job',
            'body' => 'You were assigned the ' . $kind . ' of ' . $parcel->tracking_number . '.', 'type' => 'info', 'link' => route('courier.rider')]);
        \App\Models\ActivityLog::note($parcel->tracking_number . ' ' . $kind . ' assigned to ' . $rider->full_name, $parcel);

        return back()->with('success', "{$parcel->tracking_number} assigned to {$rider->full_name}.");
    }

    // ── Rider record ────────────────────────────────────────────

    public function showRider(Rider $rider)
    {
        $this->guardRider($rider);
        $rider->load('area', 'user');

        return view('sc.rider-show', ['rider' => $rider, 'wallet' => $this->wallet]);
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

    public function remittances()
    {
        $remittances = RiderRemittance::where('sorting_center_id', $this->scId())->with('rider')->latest()->paginate(20);
        $riders = Rider::where('sorting_center_id', $this->scId())->where('application_status', 'approved')->orderBy('full_name')->get()
            ->map(fn ($r) => ['rider' => $r, 'collected' => $this->wallet->codCollected($r), 'balance' => $this->wallet->codBalance($r), 'pending' => $this->wallet->pendingRemittance($r)]);

        return view('sc.remittances', compact('remittances', 'riders'));
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
