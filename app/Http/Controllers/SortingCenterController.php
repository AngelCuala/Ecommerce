<?php

namespace App\Http\Controllers;

use App\Models\DeliveryArea;
use App\Models\Message;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\ParcelTransfer;
use App\Models\Rider;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SortingCenterController extends Controller
{
    /** The currently authenticated sorting-center account. */
    private function sc(): User
    {
        return auth()->user();
    }

    /** IDs of the delivery areas (barangays) this SC manages. */
    private function myAreaIds(): array
    {
        return DeliveryArea::where('sorting_center_id', auth()->id())->pluck('id')->all();
    }

    private function parcels(): \App\Services\ParcelService
    {
        return app(\App\Services\ParcelService::class);
    }

    /** Parcels currently held by this sorting center. */
    private function myParcels()
    {
        return Parcel::where('current_sorting_center_id', auth()->id());
    }

    /** Pickup requests this SC can act on: routed to it, or not yet routed to anyone. */
    private function pickupScope()
    {
        return Parcel::where(fn ($q) => $q->where('current_sorting_center_id', auth()->id())
            ->orWhereNull('current_sorting_center_id'));
    }

    /**
     * Stop unless this SC holds the parcel (403) and it is in one of the expected
     * statuses (redirect back with a message, e.g. after a double-click).
     */
    private function guardParcel(Parcel $parcel, array $statuses): void
    {
        if ((int) $parcel->current_sorting_center_id !== (int) auth()->id()) {
            abort(403, 'This parcel is handled by another sorting center.');
        }
        if (! in_array($parcel->status, $statuses, true)) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                back()->with('error', "{$parcel->tracking_number} is already {$parcel->statusLabel()}.")
            );
        }
    }

    /** Delivery assignments for parcels held by this SC. */
    private function myDeliveries()
    {
        return ParcelDelivery::whereHas('parcel', fn ($q) => $q->where('current_sorting_center_id', auth()->id()));
    }

    // ── Dashboard ─────────────────────────────────────────────
    public function dashboard()
    {
        $stats = [
            'total_parcels_today' => $this->myParcels()->whereDate('received_at', today())->count(),
            'in_transit'          => $this->myDeliveries()->where('status', 'out_for_delivery')->count(),
            'delivered_today'     => $this->myDeliveries()->where('status', 'delivered')->whereDate('delivered_at', today())->count(),
            'active_riders'       => Rider::where('sorting_center_id', auth()->id())
                                        ->where('application_status', 'approved')->where('is_active', true)->count(),
            'pending_pickup'      => $this->pickupScope()->where('status', 'pending_pickup')->count(),
            'sorting_queue'       => $this->myParcels()->where('status', 'picked_up')->count(),
        ];

        $recentParcels = $this->myDeliveries()->with(['parcel.seller', 'rider', 'area'])
            ->latest()->take(6)->get();

        $areaRates = DeliveryArea::where('delivery_areas.sorting_center_id', auth()->id())
            ->select('delivery_areas.id', 'delivery_areas.name',
                DB::raw('COUNT(pd.id) as total'),
                DB::raw("SUM(CASE WHEN pd.status='delivered' THEN 1 ELSE 0 END) as delivered")
            )
            ->leftJoin('parcel_deliveries as pd', 'pd.area_id', '=', 'delivery_areas.id')
            ->groupBy('delivery_areas.id', 'delivery_areas.name')
            ->get();

        return view('sc.dashboard', compact('stats', 'recentParcels', 'areaRates'));
    }

    // ── PSGC barangays of the SC municipality ─────────────────

    private function psgc(): \App\Services\PsgcDirectory
    {
        return app(\App\Services\PsgcDirectory::class);
    }

    /** Every PSA barangay of this SC's assigned municipality ([] when none / unknown). */
    private function municipalityBarangays(User $sc): array
    {
        return $sc->hasAssignedMunicipality()
            ? ($this->psgc()->barangaysByMunicipality($sc->assigned_municipality_code) ?? [])
            : [];
    }

    /** Form rules for the barangay dropdown (value = 10-digit PSGC; optional 9-digit code). */
    private function barangayRules(): array
    {
        return [
            'barangay_psgc' => ['required', 'regex:/^\d{10}$/'],
            'barangay_code' => ['nullable', 'regex:/^\d{9}$/'],
        ];
    }

    private function barangayMessages(): array
    {
        return [
            'barangay_psgc.required' => 'Please select a barangay.',
            'barangay_psgc.regex'    => 'Please select a barangay from the list.',
            'barangay_code.regex'    => 'The barangay code is not valid.',
        ];
    }

    /**
     * Server-side check of a submitted barangay: it must be a PSA barangay whose parent is
     * this SC's assigned municipality (never a municipality taken from the request).
     */
    private function resolveScBarangay(User $sc, array $data): array
    {
        $addr = $this->psgc()->resolveBarangayIn($sc->assigned_municipality_code, $data, 'barangay_psgc');

        if ((string) $addr['municipality_code'] !== (string) $sc->assigned_municipality_code) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'barangay_psgc' => 'That barangay is outside your municipality coverage.',
            ]);
        }
        if (! $addr['barangay_code']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'barangay_psgc' => "{$addr['barangay']} has no PSA correspondence code yet, so it cannot be used.",
            ]);
        }
        return $addr;
    }

    /** This SC's coverage area for a barangay: by PSGC code, or a legacy name-only row. */
    private function findCoverageArea(User $sc, array $addr): ?DeliveryArea
    {
        return DeliveryArea::where('sorting_center_id', $sc->id)->where('barangay_code', $addr['barangay_code'])->first()
            ?? DeliveryArea::where('sorting_center_id', $sc->id)->whereNull('barangay_code')
                ->whereRaw('LOWER(name) = ?', [strtolower($addr['barangay'])])->first();
    }

    private function createCoverageArea(User $sc, array $addr): DeliveryArea
    {
        return DeliveryArea::create([
            'sorting_center_id' => $sc->id,
            'name'              => $addr['barangay'],
            'code'              => 'BGY-' . $addr['barangay_code'] . '-' . $sc->id,
            'description'       => $addr['barangay'] . ', ' . $addr['municipality'],
            'municipality'      => $addr['municipality'],
            'region_code'       => $addr['region_code'],
            'province_code'     => $addr['province_code'],
            'municipality_code' => $addr['municipality_code'],
            'barangay_code'     => $addr['barangay_code'],
        ]);
    }

    // ── Coverage: barangays / delivery areas within the SC municipality ──
    public function areas()
    {
        $sc = $this->sc();

        $areas = DeliveryArea::where('sorting_center_id', $sc->id)
            ->withCount('riders')
            ->orderBy('name')
            ->get();

        // PSA barangays of the municipality that are not yet in coverage.
        $coveredCodes = $areas->pluck('barangay_code')->filter()->all();
        $legacyNames  = $areas->whereNull('barangay_code')->pluck('name')->map(fn ($n) => strtolower($n))->all();
        $allBarangays = $this->municipalityBarangays($sc);
        $barangays    = array_values(array_filter($allBarangays, fn ($b) =>
            ! in_array($b['code'], $coveredCodes, true) && ! in_array(strtolower($b['name']), $legacyNames, true)));
        $barangayTotal = count($allBarangays);

        return view('sc.areas', compact('sc', 'areas', 'barangays', 'barangayTotal'));
    }

    public function storeArea(Request $request)
    {
        $sc = $this->sc();

        if (! $sc->hasAssignedMunicipality()) {
            return back()->with('error', 'Your account has not been assigned a municipality yet. Contact an administrator.');
        }

        $data = $request->validate($this->barangayRules(), $this->barangayMessages());
        $addr = $this->resolveScBarangay($sc, $data);

        if ($this->findCoverageArea($sc, $addr)) {
            return back()->with('error', $addr['barangay'] . ' is already in your coverage list.');
        }

        $this->createCoverageArea($sc, $addr);

        return back()->with('success', $addr['barangay'] . ' added to your coverage.');
    }

    public function destroyArea(DeliveryArea $area)
    {
        // Only allow deleting an area this SC owns
        if ((int) $area->sorting_center_id !== (int) auth()->id()) {
            abort(403);
        }

        if ($area->riders()->exists() || $area->parcels()->exists()) {
            return back()->with('error', 'Cannot remove ' . $area->name . ' — it still has riders or parcels linked to it.');
        }

        $name = $area->name;
        $area->delete();

        return back()->with('success', $name . ' removed from your coverage.');
    }

    // ── Riders ────────────────────────────────────────────────
    public function riders(Request $request)
    {
        $tab = $request->get('tab', 'all');
        $scId = auth()->id();

        // Only this sorting center's riders
        $base = fn() => Rider::where('sorting_center_id', $scId);

        $query = $base()->with(['user', 'area']);

        if ($tab === 'pending')  $query->where('application_status', 'pending');
        if ($tab === 'active')   $query->where('application_status', 'approved')->where('is_active', true);
        if ($tab === 'inactive') $query->where('application_status', 'approved')->where('is_active', false);
        if ($tab === 'rejected') $query->where('application_status', 'rejected');

        $riders = $query->latest()->paginate(15)->withQueryString();

        $counts = [
            'all'      => $base()->count(),
            'pending'  => $base()->where('application_status', 'pending')->count(),
            'active'   => $base()->where('application_status', 'approved')->where('is_active', true)->count(),
            'inactive' => $base()->where('application_status', 'approved')->where('is_active', false)->count(),
            'rejected' => $base()->where('application_status', 'rejected')->count(),
        ];

        $sc    = $this->sc();
        $areas = DeliveryArea::where('sorting_center_id', $scId)->orderBy('name')->get();

        // Every PSA barangay of the SC municipality; the ones already in coverage are marked.
        $barangays    = $this->municipalityBarangays($sc);
        $coveredCodes = $areas->pluck('barangay_code')->filter()->values()->all();

        return view('sc.riders', compact('riders', 'counts', 'sc', 'areas', 'barangays', 'coveredCodes'));
    }

    /**
     * Add a rider under this sorting center for one PSA barangay of its municipality.
     * The rider is linked to that barangay's coverage area (created if missing), which is
     * what parcel sorting and delivery assignment match on.
     */
    public function storeRider(Request $request)
    {
        $sc = $this->sc();

        if (! $sc->hasAssignedMunicipality()) {
            return back()->with('error', 'Assign a municipality to your account before adding riders.');
        }

        $data = $request->validate([
            'full_name'    => 'required|string|max:150',
            'phone'        => 'nullable|string|max:30',
            'vehicle_type' => 'nullable|string|max:60',
        ] + $this->barangayRules(), $this->barangayMessages());

        $addr = $this->resolveScBarangay($sc, $data);

        $addedToCoverage = false;
        DB::transaction(function () use ($sc, $data, $addr, &$addedToCoverage) {
            $area = $this->findCoverageArea($sc, $addr);
            if (! $area) {
                $area = $this->createCoverageArea($sc, $addr);
                $addedToCoverage = true;
            }

            Rider::create([
                'user_id'            => $sc->id, // owner reference; SC-managed rider
                'sorting_center_id'  => $sc->id,
                'full_name'          => $data['full_name'],
                'phone'              => $data['phone'] ?? null,
                'vehicle_type'       => $data['vehicle_type'] ?? null,
                'area_id'            => $area->id,
                'region_code'        => $addr['region_code'],
                'province_code'      => $addr['province_code'],
                'municipality_code'  => $addr['municipality_code'],
                'barangay_code'      => $addr['barangay_code'],
                'application_status' => 'approved',
                'is_active'          => true,
                'approved_at'        => now(),
                'approved_by'        => $sc->id,
            ]);
        });

        return back()->with('success', "{$data['full_name']} added as a rider for {$addr['barangay']}."
            . ($addedToCoverage ? " {$addr['barangay']} was added to your coverage areas." : ''));
    }

    /** Ensure the rider belongs to the current sorting center. */
    private function guardRider(Rider $rider): void
    {
        if ((int) $rider->sorting_center_id !== (int) auth()->id()) {
            abort(403, 'This rider is managed by another sorting center.');
        }
    }

    public function approveRider(Rider $rider)
    {
        $this->guardRider($rider);
        $rider->update(['application_status' => 'approved', 'is_active' => true, 'approved_at' => now(), 'approved_by' => auth()->id()]);
        return back()->with('success', "{$rider->full_name} approved.");
    }

    public function rejectRider(Request $request, Rider $rider)
    {
        $this->guardRider($rider);
        $rider->update(['application_status' => 'rejected', 'rejection_reason' => $request->reason, 'is_active' => false]);
        return back()->with('success', "{$rider->full_name} rejected.");
    }

    public function toggleRider(Rider $rider)
    {
        $this->guardRider($rider);
        $rider->update(['is_active' => !$rider->is_active]);
        return back()->with('success', "{$rider->full_name} " . ($rider->is_active ? 'activated' : 'deactivated') . '.');
    }

    // ── Pickup Requests ───────────────────────────────────────
    public function pickupRequests(Request $request)
    {
        $tab = $request->get('tab', 'pending');

        $tabStatuses = [
            'pending'   => ['pending_pickup'],
            'confirmed' => ['pickup_approved'],
            'received'  => ['picked_up', 'sorted', 'assigned', 'in_transit', 'delivered', 'failed', 'returned'],
            'rejected'  => ['pickup_rejected'],
        ];

        $query = $this->pickupScope()->with(['seller.sellerApplication', 'order']);
        if (isset($tabStatuses[$tab])) $query->whereIn('status', $tabStatuses[$tab]);

        $requests = $query->latest()->paginate(15)->withQueryString();

        $counts = collect($tabStatuses)->map(fn ($s) => $this->pickupScope()->whereIn('status', $s)->count());

        return view('sc.pickup-requests', compact('requests', 'counts', 'tab'));
    }

    /** Confirm/verify a seller's pickup request — the SC takes responsibility for it. */
    public function approvePickup(Parcel $parcel)
    {
        $me = auth()->id();
        if ($parcel->current_sorting_center_id && (int) $parcel->current_sorting_center_id !== (int) $me) {
            abort(403, 'This pickup request belongs to another sorting center.');
        }
        if ($parcel->status !== 'pending_pickup') {
            return back()->with('error', "{$parcel->tracking_number} is already {$parcel->statusLabel()}.");
        }

        // The buyer cancelled the order before pickup — close the request instead.
        if ($parcel->order && $parcel->order->status === 'Cancelled') {
            $parcel->update([
                'status' => 'pickup_rejected', 'failure_reason' => 'Order was cancelled by the buyer.',
                'current_sorting_center_id' => $me,
            ]);
            return back()->with('error', "{$parcel->tracking_number}: the order was cancelled, so the pickup was closed.");
        }

        // One route per order: a direct courier is already handling it.
        if ($parcel->order_id && \App\Models\Delivery::where('order_id', $parcel->order_id)
                ->whereIn('status', \App\Models\Delivery::OPEN_STATUSES)->exists()) {
            return back()->with('error', "{$parcel->tracking_number}: this order is already being delivered by a direct courier.");
        }

        $parcel->update([
            'status'                    => 'pickup_approved',
            'verified_by'               => $me,
            'verified_at'               => now(),
            'current_sorting_center_id' => $me,
            'failure_reason'            => null,
        ]);

        $svc = $this->parcels();
        $svc->syncOrder($parcel);
        $svc->notifySeller($parcel, 'Pickup confirmed',
            "{$svc->orderRef($parcel)}: {$this->sc()->name} confirmed your pickup request ({$parcel->tracking_number}). Please have the parcel packed and labeled.");

        return back()->with('success', "Pickup for {$parcel->tracking_number} confirmed.");
    }

    public function rejectPickup(Request $request, Parcel $parcel)
    {
        $me = auth()->id();
        if ($parcel->current_sorting_center_id && (int) $parcel->current_sorting_center_id !== (int) $me) {
            abort(403, 'This pickup request belongs to another sorting center.');
        }
        if ($parcel->status !== 'pending_pickup') {
            return back()->with('error', "{$parcel->tracking_number} is already {$parcel->statusLabel()}.");
        }

        $data = $request->validate(['reason' => 'required|string|max:500']);

        $parcel->update([
            'status'                    => 'pickup_rejected',
            'failure_reason'            => $data['reason'],
            'current_sorting_center_id' => $me,
        ]);

        $svc = $this->parcels();
        $svc->notifySeller($parcel, 'Pickup request rejected',
            "{$svc->orderRef($parcel)}: pickup {$parcel->tracking_number} was rejected. Reason: {$data['reason']}. You can reschedule it from the order page.",
            'warning');

        return back()->with('success', "Pickup request {$parcel->tracking_number} rejected.");
    }

    // ── Incoming Parcels (receive + scan) ─────────────────────
    public function incomingParcels(Request $request)
    {
        $tab = $request->get('tab', 'all');

        $query = $this->myParcels()->with(['seller', 'area', 'order']);
        if ($tab !== 'all') $query->where('status', $tab);
        else $query->whereIn('status', ['pickup_approved', 'picked_up', 'sorted', 'assigned']);

        if ($request->search) {
            $q = $request->search;
            $query->where(fn($x) => $x->where('tracking_number', 'like', "%$q%")
                ->orWhere('receiver_name', 'like', "%$q%"));
        }

        $parcels = $query->latest()->paginate(15)->withQueryString();

        $miniStats = [
            'awaiting'    => $this->myParcels()->where('status', 'pickup_approved')->count(),
            'received'    => $this->myParcels()->where('status', 'picked_up')->whereDate('received_at', today())->count(),
            'for_sorting' => $this->myParcels()->where('status', 'picked_up')->count(),
            'sorted'      => $this->myParcels()->where('status', 'sorted')->count(),
        ];

        return view('sc.incoming-parcels', compact('parcels', 'miniStats'));
    }

    /** Receive a parcel at the sorting center (row button). */
    public function advanceParcel(Parcel $parcel)
    {
        $this->guardParcel($parcel, ['pickup_approved']);
        return $this->markReceived($parcel);
    }

    /** Scan a parcel by tracking number to receive it. */
    public function scanParcel(Request $request)
    {
        $data = $request->validate(['tracking_number' => 'required|string|max:50']);

        $parcel = $this->myParcels()->where('tracking_number', trim($data['tracking_number']))->first();

        if (! $parcel) {
            return back()->with('error', "No parcel {$data['tracking_number']} is expected at your sorting center.");
        }
        if ($parcel->status !== 'pickup_approved') {
            return back()->with('error', "{$parcel->tracking_number} is already {$parcel->statusLabel()}.");
        }

        return $this->markReceived($parcel);
    }

    private function markReceived(Parcel $parcel)
    {
        // The buyer cancelled after pickup was confirmed: close the parcel instead of receiving it.
        if ($parcel->order && $parcel->order->status === 'Cancelled') {
            $parcel->update(['status' => 'cancelled', 'failure_reason' => 'Order was cancelled by the buyer.']);
            $this->parcels()->notifySeller($parcel, 'Pickup cancelled',
                "{$this->parcels()->orderRef($parcel)} was cancelled by the buyer. Parcel {$parcel->tracking_number} will not be delivered.",
                'warning');
            return back()->with('error', "{$parcel->tracking_number}: the order was cancelled by the buyer, so the parcel was closed.");
        }

        $parcel->update(['status' => 'picked_up', 'received_at' => now()]);

        $svc = $this->parcels();
        $svc->syncOrder($parcel);
        $svc->notifyBuyer($parcel, 'Parcel at sorting center',
            "{$svc->orderRef($parcel)} arrived at {$this->sc()->name} and is being sorted for delivery.");

        return back()->with('success', "{$parcel->tracking_number} received at the sorting center. Ready for sorting.");
    }

    // ── Parcel Sorting ────────────────────────────────────────
    public function parcelSorting()
    {
        $sc = $this->sc();

        $areas = DeliveryArea::where('sorting_center_id', $sc->id)
            ->withCount(['parcels as sorted_count' => fn($q) => $q->where('status', 'sorted')])
            ->orderBy('name')->get();

        // Received parcels waiting to be sorted (not mid-transfer).
        $pending = $this->myParcels()->with(['area', 'order'])
            ->where('status', 'picked_up')
            ->whereNull('transfer_status')
            ->oldest('received_at')->get();

        // Read each delivery address -> determine the delivery area.
        $svc = $this->parcels();
        $suggested = [];
        $covered   = [];
        foreach ($pending as $p) {
            $covered[$p->id]   = $svc->coversDestination($p, $sc);
            $suggested[$p->id] = $covered[$p->id] ? $svc->suggestArea($p, $areas)?->id : null;
        }

        $sortedToday = $this->myParcels()->where('status', 'sorted')->whereDate('sorted_at', today())->count();

        return view('sc.parcel-sorting', compact('areas', 'pending', 'sortedToday', 'suggested', 'covered'));
    }

    public function sortParcel(Request $request, Parcel $parcel)
    {
        $this->guardParcel($parcel, ['picked_up']);

        if ($parcel->transfer_status === 'outgoing') {
            return back()->with('error', "{$parcel->tracking_number} is being transferred to another sorting center.");
        }

        // The chosen barangay must be one this SC covers (within its municipality).
        $request->validate([
            'area_id' => ['required', 'integer', \Illuminate\Validation\Rule::in($this->myAreaIds())],
        ], [
            'area_id.required' => 'Choose the destination barangay for this parcel.',
            'area_id.in'       => 'That barangay is outside your municipality coverage.',
        ]);

        $parcel->update(['area_id' => $request->area_id, 'status' => 'sorted', 'sorted_at' => now()]);
        $this->parcels()->syncOrder($parcel);

        return back()->with('success', "{$parcel->tracking_number} sorted to {$parcel->area->name}.");
    }

    // ── Delivery Assignment ───────────────────────────────────
    public function deliveryAssignment(Request $request)
    {
        $sc      = $this->sc();
        $myAreas = $this->myAreaIds();

        // Parcels this SC sorted into one of its barangays.
        $parcels = $this->myParcels()->with(['area', 'order'])
            ->where('status', 'sorted')
            ->whereNull('transfer_status') // not while a transfer to another SC is pending
            ->whereIn('area_id', $myAreas ?: [0])
            ->when($request->area_id, fn($q) => $q->where('area_id', $request->area_id))
            ->oldest('sorted_at')
            ->paginate(15)->withQueryString();

        // Only this SC's active riders.
        $riders = Rider::where('sorting_center_id', $sc->id)
            ->where('application_status', 'approved')->where('is_active', true)
            ->with('area')->orderBy('full_name')->get();

        // Only this SC's barangays for the area tabs.
        $areas = DeliveryArea::where('sorting_center_id', $sc->id)->orderBy('name')->get();

        return view('sc.delivery-assignment', compact('sc', 'parcels', 'riders', 'areas'));
    }

    public function assignParcel(Request $request, Parcel $parcel)
    {
        $this->guardParcel($parcel, ['sorted']);

        if ($parcel->transfer_status === 'outgoing') {
            return back()->with('error', "{$parcel->tracking_number} is being transferred to another sorting center.");
        }
        if ($parcel->order && $parcel->order->status === 'Cancelled') {
            return back()->with('error', "{$parcel->tracking_number}: the order was cancelled, so it cannot be assigned.");
        }

        $request->validate(['rider_id' => 'required|exists:riders,id'], [
            'rider_id.required' => 'Select a rider for this parcel.',
        ]);
        $rider = Rider::findOrFail($request->rider_id);

        // The rider must belong to this SC and be active.
        if ((int) $rider->sorting_center_id !== (int) auth()->id()) {
            return back()->with('error', 'You can only assign riders that belong to your sorting center.');
        }
        if (! $rider->isApproved() || ! $rider->is_active) {
            return back()->with('error', "{$rider->full_name} is not an active rider.");
        }

        // The rider must cover the parcel's destination area.
        if ((int) $rider->area_id !== (int) $parcel->area_id) {
            return back()->with('error', "{$rider->full_name} is not assigned to {$parcel->area->name}. Choose a rider for that area.");
        }

        ParcelDelivery::create([
            'parcel_id'   => $parcel->id,
            'rider_id'    => $rider->id,
            'area_id'     => $parcel->area_id,
            'assigned_by' => auth()->id(),
            'status'      => 'assigned',
        ]);

        $parcel->update(['status' => 'assigned']);
        $this->parcels()->syncOrder($parcel);

        return back()->with('success', "{$parcel->tracking_number} assigned to {$rider->full_name}.");
    }

    // ── Delivery Monitoring ───────────────────────────────────
    public function deliveryMonitoring(Request $request)
    {
        $deliveries = $this->myDeliveries()->with(['parcel.order', 'rider', 'area'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()->paginate(20)->withQueryString();

        // Delivery attempts per parcel
        $attempts = ParcelDelivery::whereIn('parcel_id', $deliveries->pluck('parcel_id'))
            ->selectRaw('parcel_id, COUNT(*) as n')->groupBy('parcel_id')->pluck('n', 'parcel_id');

        $monitorStats = [
            'dispatched'       => $this->myDeliveries()->where('status', 'assigned')->count(),
            'out_for_delivery' => $this->myDeliveries()->where('status', 'out_for_delivery')->count(),
            'delivered'        => $this->myDeliveries()->where('status', 'delivered')->whereDate('delivered_at', today())->count(),
            'failed'           => $this->myDeliveries()->where('status', 'failed')->count(),
            'returned'         => $this->myDeliveries()->where('status', 'returned')->count(),
        ];

        return view('sc.delivery-monitoring', compact('deliveries', 'monitorStats', 'attempts'));
    }

    /** Allowed next delivery statuses (the SC records the rider's progress). */
    private const DELIVERY_FLOW = [
        'assigned'         => ['out_for_delivery', 'failed'],
        'out_for_delivery' => ['delivered', 'failed'],
    ];

    public function updateDeliveryStatus(Request $request, ParcelDelivery $delivery)
    {
        $parcel = $delivery->parcel;
        $this->guardParcel($parcel, ['assigned', 'in_transit']);

        $allowed = self::DELIVERY_FLOW[$delivery->status] ?? [];
        $data = $request->validate([
            'status'  => ['required', \Illuminate\Validation\Rule::in($allowed)],
            'remarks' => 'nullable|string|max:500|required_if:status,failed',
        ], [
            'status.in'           => 'That status change is not allowed for this delivery.',
            'remarks.required_if' => 'Record the reason the delivery failed.',
        ]);

        $svc = $this->parcels();
        $ref = $svc->orderRef($parcel);

        switch ($data['status']) {
            case 'out_for_delivery':
                $delivery->update(['status' => 'out_for_delivery', 'picked_up_at' => now()]);
                $parcel->update(['status' => 'in_transit']);
                $svc->notifyBuyer($parcel, 'Out for delivery',
                    "{$ref} is out for delivery with {$delivery->rider->full_name}.");
                break;

            case 'delivered':
                $delivery->update(['status' => 'delivered', 'delivered_at' => now(), 'remarks' => $data['remarks'] ?? null]);
                $parcel->update(['status' => 'delivered']);
                $svc->notifyBuyer($parcel, 'Parcel delivered',
                    "{$ref} was delivered. Please confirm with \"Order Received\" to complete your order.");
                $svc->notifySeller($parcel, 'Parcel delivered',
                    "{$ref} ({$parcel->tracking_number}) was delivered to the buyer.");
                break;

            case 'failed':
                $delivery->update(['status' => 'failed', 'remarks' => $data['remarks']]);
                $parcel->update(['status' => 'failed', 'failure_reason' => $data['remarks']]);
                $svc->notifyBuyer($parcel, 'Delivery attempt failed',
                    "{$ref} could not be delivered: {$data['remarks']}. The sorting center will reschedule it.", 'warning');
                break;
        }

        $svc->syncOrder($parcel->fresh());

        return back()->with('success', "{$parcel->tracking_number}: " . $parcel->fresh()->statusLabel() . '.');
    }

    /** Failed delivery -> back to the sorted queue for a new rider assignment. */
    public function rescheduleDelivery(ParcelDelivery $delivery)
    {
        $parcel = $delivery->parcel;
        $this->guardParcel($parcel, ['failed']);

        $parcel->update(['status' => 'sorted']);

        $svc = $this->parcels();
        $svc->notifyBuyer($parcel, 'Delivery rescheduled',
            "{$svc->orderRef($parcel)} has been rescheduled for another delivery attempt.");

        return redirect()->route('sc.delivery-assignment', ['area_id' => $parcel->area_id])
            ->with('success', "{$parcel->tracking_number} rescheduled. Assign a rider for the next attempt.");
    }

    /** Failed delivery -> return the parcel to the seller. */
    public function returnParcel(ParcelDelivery $delivery)
    {
        $parcel = $delivery->parcel;
        $this->guardParcel($parcel, ['failed']);

        $delivery->update(['status' => 'returned']);
        $parcel->update(['status' => 'returned']);

        $svc = $this->parcels();
        $reason = $parcel->failure_reason ?: 'delivery could not be completed';
        $svc->notifySeller($parcel, 'Parcel returned',
            "{$svc->orderRef($parcel)} ({$parcel->tracking_number}) is being returned to you. Reason: {$reason}.", 'warning');
        $svc->notifyBuyer($parcel, 'Parcel returned to seller',
            "{$svc->orderRef($parcel)} could not be delivered and was returned to the seller. Reason: {$reason}.", 'warning');
        $svc->syncOrder($parcel->fresh());

        return back()->with('success', "{$parcel->tracking_number} marked as returned to seller.");
    }

    // ── Reports ───────────────────────────────────────────────
    public function reports(Request $request)
    {
        $from = $request->from ? Carbon::parse($request->from) : now()->subDays(5);
        $to   = $request->to   ? Carbon::parse($request->to)->endOfDay() : now()->endOfDay();

        // ── Daily Summary ─────────────────────────────────────
        $days   = collect();
        $cursor = $from->copy();
        while ($cursor <= $to) {
            $date = $cursor->toDateString();
            $days->push((object)[
                'date'      => $date,
                'received'  => $this->myParcels()->whereDate('received_at', $date)->count(),
                'sorted'    => $this->myParcels()->whereDate('sorted_at', $date)->count(),
                'delivered' => $this->myDeliveries()->where('status','delivered')->whereDate('delivered_at',$date)->count(),
                'failed'    => $this->myDeliveries()->where('status','failed')->whereDate('updated_at',$date)->count(),
                'returned'  => $this->myDeliveries()->where('status','returned')->whereDate('updated_at',$date)->count(),
            ]);
            $cursor->addDay();
        }

        // ── Rider Performance ─────────────────────────────────
        $riderPerformance = Rider::with(['user', 'area'])
            ->where('sorting_center_id', auth()->id())
            ->where('application_status', 'approved')
            ->get()
            ->map(function ($rider) use ($from, $to) {
                $base  = $rider->parcelDeliveries()
                    ->whereBetween('created_at', [$from, $to]);

                $total     = (clone $base)->count();
                $delivered = (clone $base)->where('status', 'delivered')->count();
                $failed    = (clone $base)->where('status', 'failed')->count();
                $returned  = (clone $base)->where('status', 'returned')->count();
                $rate      = $total > 0 ? round(($delivered / $total) * 100) : 0;

                return (object)[
                    'id'        => $rider->id,
                    'name'      => $rider->full_name,
                    'area'      => $rider->area->name ?? '—',
                    'total'     => $total,
                    'delivered' => $delivered,
                    'failed'    => $failed,
                    'returned'  => $returned,
                    'rate'      => $rate,
                    'active'    => $rider->is_active,
                ];
            })
            ->sortByDesc('delivered');

        // ── Area Summary ──────────────────────────────────────
        $areaSummary = DeliveryArea::where('sorting_center_id', auth()->id())->get()->map(function ($area) use ($from, $to) {
            $base  = ParcelDelivery::where('area_id', $area->id)
                ->whereBetween('created_at', [$from, $to]);

            $total     = (clone $base)->count();
            $delivered = (clone $base)->where('status', 'delivered')->count();
            $failed    = (clone $base)->where('status', 'failed')->count();
            $returned  = (clone $base)->where('status', 'returned')->count();
            $pending   = Parcel::where('area_id', $area->id)
                ->whereIn('status', ['sorted', 'assigned'])->count();
            $rate      = $total > 0 ? round(($delivered / $total) * 100) : 0;

            return (object)[
                'name'      => $area->name,
                'code'      => $area->code,
                'total'     => $total,
                'delivered' => $delivered,
                'failed'    => $failed,
                'returned'  => $returned,
                'pending'   => $pending,
                'rate'      => $rate,
            ];
        })->sortByDesc('delivered');

        if ($request->boolean('export')) {
            return $this->exportReport($request->get('tab', 'daily'), $from, $to, $days, $riderPerformance, $areaSummary);
        }

        return view('sc.reports', compact('from', 'to', 'riderPerformance', 'areaSummary')
            + ['dailyData' => $days]);
    }

    /** Download the current report tab as CSV. */
    private function exportReport(string $tab, $from, $to, $days, $riders, $areas)
    {
        [$header, $rows] = match ($tab) {
            'rider' => [
                ['Rider', 'Area', 'Assigned', 'Delivered', 'Failed', 'Returned', 'Success Rate %', 'Active'],
                $riders->map(fn ($r) => [$r->name, $r->area, $r->total, $r->delivered, $r->failed, $r->returned, $r->rate, $r->active ? 'Yes' : 'No']),
            ],
            'area' => [
                ['Area', 'Code', 'Assigned', 'Delivered', 'Failed', 'Returned', 'Pending', 'Success Rate %'],
                $areas->map(fn ($a) => [$a->name, $a->code, $a->total, $a->delivered, $a->failed, $a->returned, $a->pending, $a->rate]),
            ],
            default => [
                ['Date', 'Received', 'Sorted', 'Delivered', 'Failed', 'Returned'],
                $days->map(fn ($d) => [$d->date, $d->received, $d->sorted, $d->delivered, $d->failed, $d->returned]),
            ],
        };

        $name = sprintf('sc-%s-report_%s_to_%s.csv', $tab, $from->toDateString(), $to->toDateString());

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            foreach ($rows as $row) fputcsv($out, $row);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    // ── Chat ──────────────────────────────────────────────────
    public function chat(Request $request)
    {
        $contacts = User::where('id', '!=', auth()->id())
            ->whereIn('role', ['courier', 'courier_pending', 'seller', 'admin'])
            ->orderBy('name')->get();

        $activeContact = null;
        $messages      = collect();

        if ($request->contact_id) {
            $activeContact = User::findOrFail($request->contact_id);
            $messages = Message::where(function($q) use($activeContact) {
                    $q->where('sender_id', auth()->id())->where('receiver_id', $activeContact->id);
                })->orWhere(function($q) use($activeContact) {
                    $q->where('sender_id', $activeContact->id)->where('receiver_id', auth()->id());
                })->oldest()->get();

            Message::where('sender_id', $activeContact->id)
                ->where('receiver_id', auth()->id())
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        return view('sc.chat', compact('contacts', 'activeContact', 'messages'));
    }

    public function sendMessage(Request $request)
    {
        $request->validate(['receiver_id' => 'required|exists:users,id', 'body' => 'required|string|max:2000']);
        Message::create(['sender_id' => auth()->id(), 'receiver_id' => $request->receiver_id, 'body' => $request->body]);
        return back()->with('success', 'Message sent.');
    }

    public function pollMessages(Request $request)
    {
        $request->validate(['contact_id' => 'required|exists:users,id', 'after_id' => 'nullable|integer']);

        $messages = Message::where(function($q) use($request) {
                $q->where('sender_id', auth()->id())->where('receiver_id', $request->contact_id);
            })->orWhere(function($q) use($request) {
                $q->where('sender_id', $request->contact_id)->where('receiver_id', auth()->id());
            })
            ->when($request->after_id, fn($q) => $q->where('id', '>', $request->after_id))
            ->oldest()->get();

        return response()->json($messages);
    }

    // ── Account ───────────────────────────────────────────────
    public function account()
    {
        $sc = $this->sc();

        // Only this center's own details — no access to other centers' accounts.
        $summary = [
            'areas'    => DeliveryArea::where('sorting_center_id', $sc->id)->count(),
            'riders'   => Rider::where('sorting_center_id', $sc->id)->count(),
            'handled'  => $this->myParcels()->count(),
            'delivered'=> $this->myParcels()->where('status', 'delivered')->count(),
        ];

        return view('sc.account', compact('sc', 'summary'));
    }

    public function updateAccount(Request $request)
    {
        $user = auth()->user();
        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $user->update($request->only('name', 'email'));

        if ($request->filled('current_password')) {
            $request->validate([
                'current_password' => 'required|current_password',
                'password'         => 'required|confirmed|min:8',
            ]);
            $user->update(['password' => Hash::make($request->password)]);
        }

        return back()->with('success', 'Account updated successfully.');
    }

    // ── Logout ────────────────────────────────────────────────
    public function logout(Request $request)
    {
        \Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('sc.login');
    }

    // ══════════════════════════════════════════════════════════════
    //  SC-TO-SC TRANSFERS
    // ══════════════════════════════════════════════════════════════

    /**
     * Show the transfer hub: outgoing transfers I initiated,
     * incoming transfers addressed to me (pending acceptance).
     */
    public function transfers(Request $request)
    {
        $me = auth()->id();

        // Parcels I currently hold that can be transferred
        $myParcels = Parcel::with('seller')
            ->where('current_sorting_center_id', $me)
            ->whereIn('status', ['picked_up', 'sorted'])
            ->whereNull('transfer_status')
            ->when($request->search, fn ($q) => $q->where('tracking_number', 'like', "%{$request->search}%"))
            ->latest()
            ->get();

        // Other operating sorting centers (approved, with a municipality), excluding me
        $otherCenters = User::where('role', 'sorting_center')
            ->where('id', '!=', $me)
            ->whereNotNull('assigned_municipality_code')
            ->orderBy('name')
            ->get();

        // Outgoing transfers I initiated (still pending)
        $outgoing = ParcelTransfer::with(['parcel', 'toSortingCenter'])
            ->where('from_sorting_center_id', $me)
            ->where('status', 'pending')
            ->latest()
            ->get();

        // Incoming transfers addressed to me (pending my acceptance)
        $incoming = ParcelTransfer::with(['parcel', 'fromSortingCenter'])
            ->where('to_sorting_center_id', $me)
            ->where('status', 'pending')
            ->latest()
            ->get();

        // Transfer history (received/rejected)
        $history = ParcelTransfer::with(['parcel', 'fromSortingCenter', 'toSortingCenter'])
            ->where(fn ($q) => $q->where('from_sorting_center_id', $me)
                ->orWhere('to_sorting_center_id', $me))
            ->whereIn('status', ['received', 'rejected'])
            ->latest()
            ->take(30)
            ->get();

        return view('sc.transfers', compact(
            'myParcels', 'otherCenters', 'outgoing', 'incoming', 'history'
        ));
    }

    /**
     * Initiate a transfer — mark parcel as outgoing and create a transfer record.
     */
    public function initiateTransfer(Request $request)
    {
        $request->validate([
            'parcel_id'               => 'required|exists:parcels,id',
            'to_sorting_center_id'    => 'required|exists:users,id',
            'reason'                  => 'nullable|string|max:500',
        ]);
        $parcel = Parcel::with('order')->findOrFail($request->parcel_id);
        $me     = (int) auth()->id();

        // Guard: only the SC currently holding the parcel can transfer it
        if ((int) $parcel->current_sorting_center_id !== $me) {
            return back()->withErrors(['parcel_id' => 'You can only transfer parcels currently held by your sorting center.']);
        }

        // Guard: can't transfer if already outgoing
        if ($parcel->transfer_status === 'outgoing') {
            return back()->withErrors(['parcel_id' => 'This parcel already has a pending outgoing transfer.']);
        }

        // Guard: only parcels physically at this SC and not yet with a rider can move.
        if (! in_array($parcel->status, ['picked_up', 'sorted'], true)) {
            return back()->withErrors(['parcel_id' => "{$parcel->tracking_number} is {$parcel->statusLabel()} and cannot be transferred."]);
        }
        if ($parcel->order && $parcel->order->status === 'Cancelled') {
            return back()->withErrors(['parcel_id' => "{$parcel->tracking_number}: the order was cancelled."]);
        }

        $destination = User::where('id', $request->to_sorting_center_id)
            ->where('role', 'sorting_center')
            ->whereNotNull('assigned_municipality_code')
            ->first();
        if (! $destination || (int) $destination->id === $me) {
            return back()->withErrors(['to_sorting_center_id' => 'Choose another operating sorting center.']);
        }

        // Create transfer record
        ParcelTransfer::create([d
        ParcelTransfer::create([
            'parcel_id'               => $parcel->id,
            'from_sorting_center_id'  => $me,
            'to_sorting_center_id'    => $destination->id,
            'initiated_by'            => $me,
            'reason'                  => $request->reason,
            'status'                  => 'pending',
        ]);

        // Mark parcel as outgoing transfer
        $parcel->update(['transfer_status' => 'outgoing']);

        return back()->with('success',
            "Transfer of {$parcel->tracking_number} to {$destination->name} initiated. Awaiting their acceptance.");
    }

    /**
     * Destination SC accepts the incoming transfer.
     */
    public function acceptTransfer(ParcelTransfer $transfer)
    {
        $me = (int) auth()->id();

        // Only the destination SC can accept
        if ((int) $transfer->to_sorting_center_id !== $me) {
            abort(403, 'Only the destination sorting center can accept this transfer.');
        }

        if (! $transfer->isPending()) {
            return back()->with('error', 'This transfer has already been processed.');
        }

        $parcel = $transfer->parcel;

        DB::transaction(function () use ($transfer, $parcel, $me) {
            $transfer->update([
                'status'      => 'received',
                'received_by' => $me,
                'received_at' => now(),
            ]);

            // Hand ownership to this SC. The old barangay belongs to the sending SC, so the
            // parcel goes back to the "At Sorting Center" queue here to be sorted again.
            $parcel->update([
                'current_sorting_center_id' => $me,
                'transfer_status'           => null,
                'status'                    => 'picked_up',
                'area_id'                   => null,
                'received_at'               => now(),
                'sorted_at'                 => null,
            ]);
        });

        $svc = $this->parcels();
        $svc->syncOrder($parcel);
        $svc->notifyBuyer($parcel, 'Parcel transferred',
            "{$svc->orderRef($parcel)} arrived at {$this->sc()->name} and will be sorted for delivery.");

        return back()->with('success',
            "Parcel {$parcel->tracking_number} received from {$transfer->fromSortingCenter->name}. It is now in your sorting queue.");
    }

    /**
     * Destination SC rejects the incoming transfer.
     */
    public function rejectTransfer(Request $request, ParcelTransfer $transfer)
    {
        $me = (int) auth()->id();

        if ((int) $transfer->to_sorting_center_id !== $me) {
            abort(403, 'Only the destination sorting center can reject this transfer.');
        }

        if (! $transfer->isPending()) {
            return back()->with('error', 'This transfer has already been processed.');
        }

        $parcel = $transfer->parcel;

        $transfer->update([
            'status'      => 'rejected',
            'received_by' => $me,
            'received_at' => now(),
        ]);

        // Return parcel to the originating SC, clear transfer flag
        $parcel->update(['transfer_status' => null]);

        return back()->with('success',
            "Transfer of {$parcel->tracking_number} rejected. It remains with {$transfer->fromSortingCenter->name}.");
    }

    /**
     * Cancel an outgoing transfer that hasn't been accepted yet.
     */
    public function cancelTransfer(ParcelTransfer $transfer)
    {
        $me = (int) auth()->id();

        if ((int) $transfer->from_sorting_center_id !== $me) {
            abort(403, 'Only the originating sorting center can cancel this transfer.');
        }

        if (! $transfer->isPending()) {
            return back()->with('error', 'This transfer has already been processed.');
        }

        $parcel = $transfer->parcel;
        $transfer->update(['status' => 'rejected', 'received_at' => now()]);
        $parcel->update(['transfer_status' => null]);

        return back()->with('success', "Transfer of {$parcel->tracking_number} cancelled.");
    }
}
