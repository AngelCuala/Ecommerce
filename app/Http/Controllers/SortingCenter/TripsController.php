<?php

namespace App\Http\Controllers\SortingCenter;

use App\Http\Controllers\Controller;
use App\Models\LinehaulTrip;
use App\Models\LogisticsHub;
use App\Models\Parcel;
use App\Services\Linehaul;
use App\Support\SortingCenters;
use Illuminate\Http\Request;

/**
 * Linehaul Trips (website), which replace SC Transfers: a hub-to-hub trip of the company's courier (truck /
 * 4-wheeler) to another sorting center's hub nearer the buyer. That sorting center
 * accepts the trip before it departs; both companies track it and message each other on it; on arrival the
 * receiving company checks the seal and receives the parcels, which then wait in its Parcel Sorting.
 */
class TripsController extends Controller
{
    public function __construct(private readonly Linehaul $linehaul) {}

    /** The status tabs: key => [label, statuses]. */
    private const TABS = [
        'active'     => ['Active', ['scheduled', 'assigned', 'accepted', 'loading', 'in_transit', 'arrived']],
        'in_transit' => ['On the road', ['loading', 'in_transit']],
        'arrived'    => ['Arrived', ['arrived']],
        'done'       => ['Completed', LinehaulTrip::DONE],
        'cancelled'  => ['Cancelled', ['cancelled']],
    ];

    public const EVENT_LABELS = [
        'created' => 'Trip created', 'assigned' => 'Rider assigned', 'reassigned' => 'Rider reassigned', 'accepted' => 'Rider accepted',
        'declined' => 'Rider declined', 'unassigned' => 'Rider removed', 'reassigned_away' => 'Reassigned to another rider',
        'handover_accepted' => 'Receiving sorting center accepted the trip', 'handover_rejected' => 'Receiving sorting center rejected the trip',
        'loading' => 'Loading started', 'departed' => 'Departed (sealed)', 'delay' => 'Delay reported', 'breakdown' => 'Vehicle breakdown reported',
        'arrived' => 'Arrived at the destination hub', 'seal_ok' => 'Seal checked: matches', 'seal_discrepancy' => 'Seal does not match',
        'damaged' => 'Parcel marked damaged', 'missing' => 'Parcel missing', 'parcel_removed' => 'Parcel taken off the trip',
        'rescanned' => 'Parcels rescanned', 'parcels_added' => 'Parcels added', 'courier_note' => 'Rider note', 'completed' => 'Received and completed',
        'completed_discrepancy' => 'Completed with discrepancy', 'cancelled' => 'Trip cancelled',
    ];

    public const PARCEL_LABELS = [
        'pending' => 'Waiting to load', 'loaded' => 'On the truck', 'received' => 'Received', 'damaged' => 'Damaged',
        'missing' => 'Missing', 'missing_in_transit' => 'Missing in transit', 'removed' => 'Taken off',
    ];

    private function me(): \App\Models\User
    {
        return auth()->user();
    }

    private function visible(LinehaulTrip $trip): LinehaulTrip
    {
        abort_unless($trip->involves((int) auth()->id()), 403, 'This trip is not sent to or by your sorting center.');

        return $trip;
    }

    private function sent(LinehaulTrip $trip): LinehaulTrip
    {
        abort_unless((int) $trip->sorting_center_id === (int) auth()->id(), 403, 'Only the sending sorting center can do this.');

        return $trip;
    }

    // ── List and create ─────────────────────────────────────────

    public function index(Request $request)
    {
        $me = (int) auth()->id();
        $mine = LinehaulTrip::involving($me);
        $status = (string) $request->query('status', '');
        $tab = array_key_exists($status, self::TABS) || in_array($status, ['all', 'incoming'], true) ? $status : 'active';

        $trips = (clone $mine)
            ->when($tab === 'incoming', fn ($q) => $q->where('destination_sorting_center_id', $me)->where('sorting_center_id', '!=', $me)->where('handover_status', 'pending'))
            ->when(! in_array($tab, ['all', 'incoming'], true), fn ($q) => $q->whereIn('status', self::TABS[$tab][1]))
            ->with(['origin', 'destination', 'courier', 'company', 'destinationCompany'])->withCount(['onBoard as parcels_count'])
            ->latest('id')->paginate(15)->withQueryString();

        $toAccept = (clone $mine)->where('destination_sorting_center_id', $me)->where('sorting_center_id', '!=', $me)->where('handover_status', 'pending')->count();
        $tabs = collect([['key' => 'incoming', 'label' => 'To accept', 'count' => $toAccept]])
            ->concat(collect(self::TABS)->map(fn ($t, $key) => ['key' => $key, 'label' => $t[0], 'count' => (clone $mine)->whereIn('status', $t[1])->count()])->values())
            ->push(['key' => 'all', 'label' => 'All', 'count' => (clone $mine)->count()]);

        $stats = [
            'to_accept' => $toAccept,
            'on_road'   => (clone $mine)->whereIn('status', ['loading', 'in_transit'])->count(),
            'incoming'  => LinehaulTrip::where('destination_sorting_center_id', $me)->where('sorting_center_id', '!=', $me)->whereIn('status', ['loading', 'in_transit', 'arrived'])->count(),
            'arrived'   => (clone $mine)->where('status', 'arrived')->count(),
            'delayed'   => (clone $mine)->whereIn('status', ['loading', 'in_transit'])->where('delayed', true)->count(),
        ];

        // The create form: the company's hub as the origin, another sorting center's hub as the destination.
        $hubs = LogisticsHub::forCompany($this->me());
        $origin = $hubs->firstWhere('id', (int) $request->query('origin_hub_id')) ?? $hubs->first();
        $parcels = $origin ? $this->linehaul->eligibleParcels($this->me(), $origin)->load('order')
            ->each(fn (Parcel $p) => $p->best_hub = $this->linehaul->bestHubFor($p, $this->me())?->load('company')) : collect();
        // Other sorting centers' hubs, nearest to the origin hub first (with the distance).
        $destinations = LogisticsHub::with('company')->where('sorting_center_id', '!=', $me)->get()
            ->each(fn ($h) => $h->km = $origin && $origin->latitude !== null ? ($h->distanceTo((float) $origin->latitude, (float) $origin->longitude) ?? null) : null)
            ->sortBy(fn ($h) => [$h->km === null ? 1 : 0, $h->km ?? 0, SortingCenters::nameOf($h->company) . ' ' . $h->code])->values();
        // Picked for them (they can change it): the sorting center most waiting parcels are nearest to (by buyer),
        // otherwise the nearest sorting center to this hub.
        $byParcels = $parcels->pluck('best_hub.id')->filter()->countBy()->sortDesc();
        $suggested = $byParcels->isNotEmpty() ? $destinations->firstWhere('id', $byParcels->keys()->first()) : null;
        $suggestedWhy = $suggested
            ? $byParcels->first() . ' of ' . $parcels->count() . ' waiting ' . \Illuminate\Support\Str::plural('parcel', $parcels->count()) . ' are nearest to it'
            : null;
        if (! $suggested && ($suggested = $destinations->first(fn ($h) => $h->km !== null))) {
            $suggestedWhy = 'the nearest sorting center to ' . $origin->code . ' (' . number_format($suggested->km / 1000, 1) . ' km)';
        }
        $preselect = array_map('intval', (array) $request->query('parcel', []));

        return view('sc.trips', compact('trips', 'tabs', 'tab', 'stats', 'hubs', 'origin', 'parcels', 'destinations', 'preselect', 'me', 'suggested', 'suggestedWhy'));
    }

    public function store(Request $request)
    {
        $request->validate(['scheduled_departure_at' => 'nullable|date|after:now'], ['scheduled_departure_at.after' => 'The departure time must be in the future.']);
        // Step 1: only the route. Parcels, then the truck, are assigned on the delivery's own page.
        $trip = $this->linehaul->createTrip($this->me(), $request->only(['origin_hub_id', 'destination_hub_id', 'scheduled_departure_at']));
        \App\Models\ActivityLog::note("Linehaul trip {$trip->code} created ({$trip->route()})", $trip);

        return redirect()->route('sc.trips.show', $trip)->with('success', "Delivery {$trip->code} to {$trip->destination->code} was created. Next: assign its parcels here."
            . ($trip->crossCompany() ? ' ' . SortingCenters::nameOf($trip->destinationCompany) . ' accepts it first.' : ''));
    }

    /** One button: every waiting parcel goes into a delivery to the hub nearest its buyer (new deliveries when needed). */
    public function autoAssignAll(Request $request)
    {
        $origin = LogisticsHub::where('sorting_center_id', auth()->id())->findOrFail((int) $request->input('origin_hub_id'));
        $done = $this->linehaul->autoAssignAll($this->me(), $origin);
        if (! $done) {
            return back()->with('error', "No waiting parcel at {$origin->code} needs to go to another sorting center.");
        }
        $lines = collect($done)->map(fn ($d) => "{$d['trip']->code} → " . SortingCenters::nameOf($d['trip']->destinationCompany) . ": {$d['added']}" . ($d['new'] ? ' (new)' : ''))->implode(' · ');
        $total = collect($done)->sum('added');
        \App\Models\ActivityLog::note("Auto-assigned {$total} parcel(s) to linehaul deliveries: {$lines}");

        return back()->with('success', "{$total} parcel(s) auto-assigned. {$lines}. New deliveries wait for the receiving sorting center to accept them.");
    }

    // ── One trip ────────────────────────────────────────────────

    public function show(LinehaulTrip $trip)
    {
        $this->visible($trip);
        $trip->load(['origin', 'destination', 'courier', 'company', 'destinationCompany']);
        $me = (int) auth()->id();

        $items    = $trip->items()->with('parcel')->orderBy('id')->get();
        $events   = $trip->events()->with(['courier', 'user'])->get();
        $messages = $trip->messages()->with('user')->get();
        $last     = $trip->locations()->reorder('recorded_at', 'desc')->first();
        $sender   = (int) $trip->sorting_center_id === $me;
        $receiver = $trip->receiverId() === $me;
        $open = $sender && in_array($trip->status, LinehaulTrip::BEFORE_DEPARTURE, true);
        $couriers = $open ? $this->linehaul->assignable($this->me(), $trip) : collect();
        $offDuty = $open ? \App\Models\Courier::where('sorting_center_id', $me)->whereNotNull('home_hub_id')->where('status', 'approved')
            ->whereNotIn('id', $couriers->pluck('id'))->orderBy('first_name')->get() : collect();
        // Parcels that can still go on the trip; the ones whose nearest hub is this destination come first.
        $addable = $open ? $this->linehaul->eligibleParcels($this->me(), $trip->origin)->load('order')
            ->each(fn (Parcel $p) => $p->best_hub = $this->linehaul->bestHubFor($p, $this->me())?->load('company'))
            ->sortBy(fn (Parcel $p) => $p->best_hub?->id === (int) $trip->destination_hub_id ? 0 : 1)->values() : collect();

        return view('sc.trip-show', compact('trip', 'items', 'events', 'messages', 'last', 'sender', 'receiver', 'couriers', 'offDuty', 'addable'));
    }

    /** The sending company assigns (or reassigns) its On Duty courier. */
    public function assign(Request $request, LinehaulTrip $trip)
    {
        $this->sent($trip);
        $this->linehaul->assign($this->me(), $trip, (int) $request->input('courier_id'), $request->input('reason'));

        return back()->with('success', "{$trip->code} assigned to {$trip->fresh()->courier?->fullName()}.");
    }

    /** Add parcels to the trip before it departs (no limit). */
    public function addParcels(Request $request, LinehaulTrip $trip)
    {
        $this->sent($trip);
        $n = $this->linehaul->addParcels($this->me(), $trip, (array) $request->input('parcel_ids', []));

        return back()->with('success', "{$n} parcel(s) added to {$trip->code}.");
    }

    /** Auto-assign: every waiting parcel whose nearest hub to the buyer is this trip's destination. */
    public function autoAdd(LinehaulTrip $trip)
    {
        $this->sent($trip);
        $n = $this->linehaul->autoAddParcels($this->me(), $trip);

        return back()->with($n ? 'success' : 'error', $n
            ? "{$n} parcel(s) going to {$trip->destination->code} were added to {$trip->code}."
            : "No waiting parcel at {$trip->origin->code} is nearest to {$trip->destination->code}.");
    }

    /** Take a parcel off the trip before it departs (it stays at the origin hub). */
    public function removeParcel(LinehaulTrip $trip, Parcel $parcel)
    {
        $this->sent($trip);
        $this->linehaul->removeParcel($trip, $parcel);

        return back()->with('success', "{$parcel->tracking_number} was taken off {$trip->code}.");
    }

    /** The sending company cancels a trip that has not departed (the parcels stay at the origin hub). */
    public function cancel(Request $request, LinehaulTrip $trip)
    {
        $this->sent($trip);
        $reason = trim((string) $request->input('reason'));
        if (mb_strlen($reason) < 5) {
            return back()->withErrors(['reason' => 'Say why the trip is cancelled (at least 5 characters).']);
        }
        $this->linehaul->cancel($trip, $reason);

        return back()->with('success', "{$trip->code} was cancelled. Its parcels stay at {$trip->origin->code}.");
    }

    /** The receiving sorting center accepts or rejects the trip before it departs. */
    public function respond(Request $request, LinehaulTrip $trip)
    {
        $this->visible($trip);
        $accept = $request->input('decision') === 'accept';
        $this->linehaul->respondHandover($this->me(), $trip, $accept, $request->input('note'));

        return back()->with('success', $accept ? "You accepted {$trip->code}. The truck can load and depart." : "You rejected {$trip->code}. It was cancelled.");
    }

    /** A message to the other sorting center on this trip. */
    public function message(Request $request, LinehaulTrip $trip)
    {
        $this->visible($trip);
        $this->linehaul->postMessage($this->me(), $trip, $request->input('body'));

        return redirect()->to(route('sc.trips.show', $trip) . '#messages');
    }

    /** The receiving company checks the seal on arrival. */
    public function seal(Request $request, LinehaulTrip $trip)
    {
        $this->visible($trip);
        abort_unless($trip->receiverId() === (int) auth()->id(), 403, 'Only the receiving sorting center checks the seal.');
        $this->linehaul->confirmSeal($trip, $request->input('seal_number'), $request->input('note'));

        return back()->with('success', 'Seal checked. Receive the parcels below.');
    }

    /** The receiving company receives the parcels (unticked ones become Missing in Transit); they can then be sorted. */
    public function receive(Request $request, LinehaulTrip $trip)
    {
        $this->visible($trip);
        $onTrip = $trip->onBoard()->whereIn('status', ['pending', 'loaded'])->pluck('parcel_id')->map(fn ($id) => (int) $id)->all();
        $found = array_map('intval', (array) $request->input('found', []));
        $notFound = array_values(array_diff($onTrip, $found));
        $trip = $this->linehaul->receiveAll($this->me(), $trip, $notFound);
        \App\Models\ActivityLog::note("Linehaul trip {$trip->code} received at {$trip->destination->code}", $trip);

        return back()->with('success', count($onTrip) - count($notFound) . ' parcel(s) received at ' . $trip->destination->code . '. They are now in Parcel Sorting.'
            . ($notFound ? ' ' . count($notFound) . ' not found: marked Missing in Transit.' : ''));
    }
}
