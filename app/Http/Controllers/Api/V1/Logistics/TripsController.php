<?php

namespace App\Http\Controllers\Api\V1\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\LinehaulTrip;
use App\Models\LogisticsHub;
use App\Models\Parcel;
use App\Services\Linehaul;
use App\Support\ApiJson;
use App\Support\LinehaulJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The company's hub-to-hub trips: create, assign or reassign a courier, take parcels off, cancel; at the
 * destination check the seal, scan parcels in, mark damage and close receiving. Also the couriers' duty board.
 */
class TripsController extends Controller
{
    public function __construct(private readonly Linehaul $linehaul) {}

    private function mine(LinehaulTrip $trip): LinehaulTrip
    {
        abort_unless((int) $trip->sorting_center_id === (int) auth()->id(), 403, 'This trip belongs to another logistics company.');

        return $trip;
    }

    private function show(LinehaulTrip $trip, string $message = ''): JsonResponse
    {
        $trip = $trip->fresh();

        return response()->json(array_filter(['message' => $message]) + [
            'trip' => LinehaulJson::trip($trip, true),
            'assignable' => in_array($trip->status, [...LinehaulTrip::BEFORE_DEPARTURE, 'in_transit'], true)
                ? $this->linehaul->assignable(auth()->user(), $trip)->map(fn ($c) => LinehaulJson::courier($c))->values() : [],
        ]);
    }

    /** status: active (default), scheduled, in_transit, arrived, done, cancelled, all · page. */
    public function index(Request $r): JsonResponse
    {
        $tabs = ['active' => ['scheduled', ...LinehaulTrip::ACTIVE], 'scheduled' => ['scheduled'], 'in_transit' => ['loading', 'in_transit'],
            'arrived' => ['arrived'], 'done' => LinehaulTrip::DONE, 'cancelled' => ['cancelled']];
        $tab = array_key_exists($r->query('status'), $tabs) || $r->query('status') === 'all' ? $r->query('status') : 'active';
        $mine = LinehaulTrip::where('sorting_center_id', auth()->id());
        $trips = (clone $mine)->when($tab !== 'all', fn ($q) => $q->whereIn('status', $tabs[$tab]))->with(['origin', 'destination', 'courier'])->latest('id')->paginate(15);

        return response()->json(ApiJson::page($trips, fn ($t) => LinehaulJson::trip($t)) + [
            'tabs' => collect(['active' => 'Active', 'scheduled' => 'Unassigned', 'in_transit' => 'Loading / In Transit', 'arrived' => 'Arrived', 'done' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All'])
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => $key === 'all' ? (clone $mine)->count() : (clone $mine)->whereIn('status', $tabs[$key])->count()])->values(),
        ]);
    }

    /** origin_hub_id: the parcels waiting there. */
    public function form(Request $r): JsonResponse
    {
        $hubs = LogisticsHub::forCompany(auth()->user());
        $origin = $hubs->firstWhere('id', (int) $r->query('origin_hub_id')) ?? $hubs->first();

        return response()->json([
            'hubs' => $hubs->map(fn ($h) => LinehaulJson::hub($h))->values(),
            'origin_hub_id' => $origin?->id,
            'parcels' => $origin ? $this->linehaul->eligibleParcels(auth()->user(), $origin)->map(fn (Parcel $p) => [
                'id' => $p->id, 'tracking' => $p->tracking_number, 'weight_kg' => (float) $p->weight_kg, 'receiver' => $p->receiver_name,
                'destination' => trim($p->destination_municipality . ', ' . $p->destination_province, ', '), 'status_label' => $p->statusLabel(),
            ])->values() : [],
        ]);
    }

    /** origin_hub_id, destination_hub_id, parcel_ids[], scheduled_departure_at. */
    public function store(Request $r): JsonResponse
    {
        $r->validate(['scheduled_departure_at' => 'nullable|date|after:now'], ['scheduled_departure_at.after' => 'The departure time must be in the future.']);
        $trip = $this->linehaul->createTrip(auth()->user(), $r->only(['origin_hub_id', 'destination_hub_id', 'parcel_ids', 'scheduled_departure_at']));

        return $this->show($trip, "{$trip->code} was created ({$trip->route()}). Assign an On Duty courier.")->setStatusCode(201);
    }

    public function trip(LinehaulTrip $trip): JsonResponse
    {
        return $this->show($this->mine($trip));
    }

    /** courier_id, reason (when reassigning). */
    public function assign(Request $r, LinehaulTrip $trip): JsonResponse
    {
        $this->mine($trip);
        $had = $trip->courier_id;
        $t = $this->linehaul->assign(auth()->user(), $trip, (int) $r->input('courier_id'), $r->input('reason'));

        return $this->show($t, ($had ? 'Reassigned' : 'Assigned') . " {$t->code} to {$t->courier->fullName()}.");
    }

    public function removeParcel(LinehaulTrip $trip, Parcel $parcel): JsonResponse
    {
        $t = $this->linehaul->removeParcel($this->mine($trip), $parcel);

        return $this->show($t, "{$parcel->tracking_number} was taken off {$t->code}; it stays at {$t->origin->code}.");
    }

    public function cancel(Request $r, LinehaulTrip $trip): JsonResponse
    {
        $r->validate(['reason' => 'required|string|min:10|max:300'], ['reason.*' => 'Give the reason in 10 to 300 characters.']);
        $t = $this->linehaul->cancel($this->mine($trip), $r->input('reason'));

        return $this->show($t, "{$t->code} was cancelled.");
    }

    // ── Receiving at the destination hub ──

    /** seal_number, note (when it does not match). */
    public function seal(Request $r, LinehaulTrip $trip): JsonResponse
    {
        $t = $this->linehaul->confirmSeal($this->mine($trip), $r->input('seal_number'), $r->input('note'));

        return $this->show($t, $t->seal_discrepancy ? 'Seal discrepancy recorded. You can scan the parcels in.' : 'Seal matches. Scan the parcels in.');
    }

    /** hub_id, tracking. */
    public function receive(Request $r): JsonResponse
    {
        $res = $this->linehaul->receiveScan(auth()->user(), (int) $r->input('hub_id'), (string) $r->input('tracking'));

        return $this->show(LinehaulTrip::find($res['trip_id']), $res['message']);
    }

    /** photo (JPG/PNG, 5 MB). */
    public function damaged(Request $r, LinehaulTrip $trip, Parcel $parcel): JsonResponse
    {
        $r->validate(['photo' => 'nullable|file|mimes:jpg,jpeg,png|mimetypes:image/jpeg,image/png|max:5120'], ['photo.*' => 'The photo must be a JPG or PNG of up to 5 MB.']);
        $t = $this->linehaul->markDamaged($this->mine($trip), $parcel, $r->file('photo'));

        return $this->show($t, "{$parcel->tracking_number} was marked Damaged.");
    }

    /** confirm: close even with parcels not scanned in. */
    public function close(Request $r, LinehaulTrip $trip): JsonResponse
    {
        $t = $this->linehaul->closeReceiving($this->mine($trip), $r->boolean('confirm'));

        return $this->show($t, "{$t->code} is {$t->label()}. ₱" . number_format((float) $t->pay, 2) . ' goes to ' . ($t->paidCourier?->fullName() ?? 'nobody') . '.');
    }

    // ── Couriers: duty board, suspend, verify ──

    public function couriers(): JsonResponse
    {
        $list = Courier::where('sorting_center_id', auth()->id())->whereNotNull('home_hub_id')->whereIn('status', ['approved', 'suspended'])
            ->with('homeHub')->orderBy('first_name')->get();

        return response()->json(['couriers' => $list->map(fn ($c) => LinehaulJson::courier($c) + ['trip' => $c->activeTrip()?->code])->values()]);
    }

    /** reason (10–300). */
    public function suspend(Request $r, Courier $courier): JsonResponse
    {
        abort_unless((int) $courier->sorting_center_id === (int) auth()->id(), 403);
        $r->validate(['reason' => 'required|string|min:10|max:300'], ['reason.*' => 'Give the reason in 10 to 300 characters.']);
        $this->linehaul->suspend($courier, $r->input('reason'));

        return response()->json(['message' => $courier->fullName() . ' was suspended. Trips not yet departed are Unassigned again.']);
    }

    public function reactivate(Courier $courier): JsonResponse
    {
        abort_unless((int) $courier->sorting_center_id === (int) auth()->id(), 403);
        $courier->forceFill(['status' => 'approved', 'rejection_reason' => null])->save();

        return response()->json(['message' => $courier->fullName() . ' is active again.']);
    }

    /** The courier's changed capacity, plate or documents were checked. */
    public function verify(Courier $courier): JsonResponse
    {
        abort_unless((int) $courier->sorting_center_id === (int) auth()->id(), 403);
        $courier->forceFill(['needs_reverification' => false])->save();

        return response()->json(['message' => $courier->fullName() . '\'s vehicle and documents are verified.']);
    }
}
