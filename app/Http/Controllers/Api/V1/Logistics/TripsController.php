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
 * destination scan parcels in, mark damage and close receiving. Also the couriers' duty board.
 */
class TripsController extends Controller
{
    public function __construct(private readonly Linehaul $linehaul) {}

    /** The sending company's trip (assign, add / remove parcels, cancel). */
    private function mine(LinehaulTrip $trip): LinehaulTrip
    {
        abort_unless((int) $trip->sorting_center_id === (int) auth()->id(), 403, 'Only the sending sorting center can do this.');

        return $trip;
    }

    /** A trip the company sends or receives (both can see and track it). */
    private function visible(LinehaulTrip $trip): LinehaulTrip
    {
        abort_unless($trip->involves((int) auth()->id()), 403, 'This trip is not sent to or by your sorting center.');

        return $trip;
    }

    /** The receiving company's trip (accept, receive). */
    private function receiving(LinehaulTrip $trip): LinehaulTrip
    {
        abort_unless($trip->receiverId() === (int) auth()->id(), 403, 'Only the receiving sorting center can do this.');

        return $trip;
    }

    /** A waiting parcel as the Trips screens list it, with the hub nearest its buyer. */
    private function waitingParcel(Parcel $p): array
    {
        $best = $this->linehaul->bestHubFor($p, auth()->user());

        return [
            'id' => $p->id, 'tracking' => $p->tracking_number, 'weight_kg' => (float) $p->weight_kg, 'receiver' => $p->receiver_name,
            'destination' => trim($p->destination_municipality . ', ' . $p->destination_province, ', '), 'status_label' => $p->statusLabel(),
            'best_hub_id' => $best?->id, 'best_hub' => $best ? \App\Support\SortingCenters::nameOf($best->company) . ' (' . $best->code . ')' : null,
        ];
    }

    private function show(LinehaulTrip $trip, string $message = ''): JsonResponse
    {
        $trip = $trip->fresh();

        $sender = (int) $trip->sorting_center_id === (int) auth()->id();
        $open = $sender && in_array($trip->status, LinehaulTrip::BEFORE_DEPARTURE, true);

        return response()->json(array_filter(['message' => $message]) + [
            'trip' => LinehaulJson::trip($trip, true),
            'role' => $sender ? 'sender' : 'receiver',
            'assignable' => $sender && in_array($trip->status, [...LinehaulTrip::BEFORE_DEPARTURE, 'in_transit'], true)
                ? $this->linehaul->assignable(auth()->user(), $trip)->map(fn ($c) => LinehaulJson::courier($c))->values() : [],
            // Parcels that can still go on it (sender, before departure); the ones nearest this destination first.
            'addable' => $open ? $this->linehaul->eligibleParcels(auth()->user(), $trip->origin)->load('order')
                ->map(fn ($p) => $this->waitingParcel($p))
                ->sortBy(fn ($p) => $p['best_hub_id'] === (int) $trip->destination_hub_id ? 0 : 1)->values() : [],
        ]);
    }

    /** status: active (default), scheduled, in_transit, arrived, done, cancelled, all · page. */
    public function index(Request $r): JsonResponse
    {
        $tabs = ['active' => ['scheduled', ...LinehaulTrip::ACTIVE], 'scheduled' => ['scheduled'], 'in_transit' => ['loading', 'in_transit'],
            'arrived' => ['arrived'], 'done' => LinehaulTrip::DONE, 'cancelled' => ['cancelled']];
        $tab = array_key_exists($r->query('status'), $tabs) || $r->query('status') === 'all' ? $r->query('status') : 'active';
        $me = (int) auth()->id();
        $mine = LinehaulTrip::involving($me);
        $toAccept = fn ($q) => $q->where('destination_sorting_center_id', $me)->where('sorting_center_id', '!=', $me)->where('handover_status', 'pending');
        if ($r->query('status') === 'incoming') {
            $tab = 'incoming';
        }
        $trips = (clone $mine)->when($tab === 'incoming', $toAccept)
            ->when(! in_array($tab, ['all', 'incoming'], true), fn ($q) => $q->whereIn('status', $tabs[$tab]))
            ->with(['origin', 'destination', 'courier', 'company', 'destinationCompany'])->latest('id')->paginate(15);

        return response()->json(ApiJson::page($trips, fn ($t) => LinehaulJson::trip($t)) + [
            'tabs' => collect(['incoming' => 'To accept', 'active' => 'Active', 'scheduled' => 'Unassigned', 'in_transit' => 'Loading / In Transit', 'arrived' => 'Arrived', 'done' => 'Completed', 'cancelled' => 'Cancelled', 'all' => 'All'])
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => match ($key) {
                    'all' => (clone $mine)->count(),
                    'incoming' => $toAccept(clone $mine)->count(),
                    default => (clone $mine)->whereIn('status', $tabs[$key])->count(),
                }])->values(),
        ]);
    }

    /** origin_hub_id: the parcels waiting there. */
    public function form(Request $r): JsonResponse
    {
        $hubs = LogisticsHub::forCompany(auth()->user());
        $origin = $hubs->firstWhere('id', (int) $r->query('origin_hub_id')) ?? $hubs->first();

        $parcels = $origin ? $this->linehaul->eligibleParcels(auth()->user(), $origin)->load('order')->map(fn (Parcel $p) => $this->waitingParcel($p))->values() : collect();

        return response()->json([
            'hubs' => $hubs->map(fn ($h) => LinehaulJson::hub($h))->values(),
            'origin_hub_id' => $origin?->id,
            // Linehaul is hub to hub between sorting centers: the destinations are the other sorting centers' hubs.
            'destinations' => LogisticsHub::with('company')->where('sorting_center_id', '!=', auth()->id())->get()
                ->map(fn ($h) => LinehaulJson::hub($h) + ['company' => \App\Support\SortingCenters::nameOf($h->company), 'municipality' => $h->company?->assigned_municipality])
                ->sortBy('company')->values(),
            'parcels' => $parcels,
            // Auto-assign all: how many waiting parcels go to each sorting center (by the hub nearest the buyer).
            'outbound' => $parcels->whereNotNull('best_hub_id')->groupBy('best_hub')->map(fn ($list, $hub) => ['hub' => $hub, 'count' => $list->count()])->values(),
        ]);
    }

    /** origin_hub_id, destination_hub_id, parcel_ids[], scheduled_departure_at. */
    public function store(Request $r): JsonResponse
    {
        $r->validate(['scheduled_departure_at' => 'nullable|date|after:now'], ['scheduled_departure_at.after' => 'The departure time must be in the future.']);
        $trip = $this->linehaul->createTrip(auth()->user(), $r->only(['origin_hub_id', 'destination_hub_id', 'parcel_ids', 'scheduled_departure_at']));
        // auto: every parcel waiting here whose nearest hub to the buyer is this destination.
        $auto = $r->boolean('auto') ? $this->linehaul->autoAddParcels(auth()->user(), $trip) : 0;

        return $this->show($trip, "Delivery {$trip->code} was created ({$trip->route()}) with {$trip->onBoard()->count()} parcel(s)"
            . ($auto ? " ({$auto} auto-assigned)" : '') . '. It waits for ' . \App\Support\SortingCenters::nameOf($trip->destinationCompany) . ' to accept it.')->setStatusCode(201);
    }

    public function trip(LinehaulTrip $trip): JsonResponse
    {
        return $this->show($this->visible($trip));
    }

    /** origin_hub_id: every waiting parcel into a delivery to the hub nearest its buyer (new deliveries when needed). */
    public function autoAssignAll(Request $r): JsonResponse
    {
        $origin = LogisticsHub::where('sorting_center_id', auth()->id())->findOrFail((int) $r->input('origin_hub_id'));
        $done = $this->linehaul->autoAssignAll(auth()->user(), $origin);
        $total = collect($done)->sum('added');

        return response()->json([
            'message' => $done ? "{$total} parcel(s) auto-assigned: " . collect($done)->map(fn ($d) => "{$d['trip']->code} → "
                . \App\Support\SortingCenters::nameOf($d['trip']->destinationCompany) . " {$d['added']}" . ($d['new'] ? ' (new)' : ''))->implode(' · ') . '.'
                : "No waiting parcel at {$origin->code} needs to go to another sorting center.",
            'deliveries' => collect($done)->map(fn ($d) => ['id' => $d['trip']->id, 'code' => $d['trip']->code, 'added' => $d['added'], 'new' => $d['new']])->values(),
        ]);
    }

    /** parcel_ids[]: add parcels before departure (no limit). */
    public function addParcels(Request $r, LinehaulTrip $trip): JsonResponse
    {
        $n = $this->linehaul->addParcels(auth()->user(), $this->mine($trip), (array) $r->input('parcel_ids', []));

        return $this->show($trip, "{$n} parcel(s) added to {$trip->code}.");
    }

    /** Every waiting parcel whose nearest hub to the buyer is this destination. */
    public function autoAdd(LinehaulTrip $trip): JsonResponse
    {
        $n = $this->linehaul->autoAddParcels(auth()->user(), $this->mine($trip));

        return $this->show($trip, $n ? "{$n} parcel(s) going to {$trip->destination->code} were added." : "No waiting parcel is nearest to {$trip->destination->code}.");
    }

    /** decision: accept / reject, note (the reason, required to reject). */
    public function respond(Request $r, LinehaulTrip $trip): JsonResponse
    {
        $accept = $r->input('decision') === 'accept';
        $t = $this->linehaul->respondHandover(auth()->user(), $this->receiving($trip), $accept, $r->input('note'));

        return $this->show($t, $accept ? "You accepted {$t->code}. The truck can load and depart." : "You rejected {$t->code}. It was cancelled.");
    }

    /** body: a message to the other sorting center on this trip. */
    public function message(Request $r, LinehaulTrip $trip): JsonResponse
    {
        $this->linehaul->postMessage(auth()->user(), $this->visible($trip), $r->input('body'));

        return $this->show($trip);
    }

    /** found[]: the parcels on the truck (the rest become Missing in Transit); they then wait in Parcel Sorting. */
    public function receiveAll(Request $r, LinehaulTrip $trip): JsonResponse
    {
        $this->receiving($trip);
        $onTrip = $trip->onBoard()->whereIn('status', ['pending', 'loaded'])->pluck('parcel_id')->map(fn ($id) => (int) $id)->all();
        $notFound = array_values(array_diff($onTrip, array_map('intval', (array) $r->input('found', $onTrip))));
        $t = $this->linehaul->receiveAll(auth()->user(), $trip, $notFound);

        return $this->show($t, (count($onTrip) - count($notFound)) . " parcel(s) received at {$t->destination->code}. They are in Parcel Sorting now."
            . ($notFound ? ' ' . count($notFound) . ' not found: Missing in Transit.' : ''));
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

    /** destination_hub_id (before a truck is assigned), scheduled_departure_at (empty: as soon as loaded). */
    public function update(Request $r, LinehaulTrip $trip): JsonResponse
    {
        $r->validate(['destination_hub_id' => 'nullable|integer', 'scheduled_departure_at' => 'nullable|date']);
        $changes = $this->linehaul->updateTrip(auth()->user(), $this->mine($trip), $r->only(['destination_hub_id', 'scheduled_departure_at']));

        return $this->show($trip->fresh(), $changes ? "{$trip->code} " . implode('; ', $changes) . '.' : 'Nothing was changed.');
    }

    /** Remove a trip for good (not departed, or cancelled). */
    public function remove(LinehaulTrip $trip): JsonResponse
    {
        $code = $this->linehaul->remove(auth()->user(), $this->mine($trip));

        return response()->json(['message' => "{$code} was removed. Its parcels stay at the origin hub."]);
    }

    // ── Receiving at the destination hub ──

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
        $t = $this->linehaul->markDamaged($this->receiving($trip), $parcel, $r->file('photo'));

        return $this->show($t, "{$parcel->tracking_number} was marked Damaged.");
    }

    /** confirm: close even with parcels not scanned in. */
    public function close(Request $r, LinehaulTrip $trip): JsonResponse
    {
        $t = $this->linehaul->closeReceiving($this->receiving($trip), $r->boolean('confirm'));

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
