<?php

namespace App\Http\Controllers\SortingCenter;

use App\Http\Controllers\Controller;
use App\Models\LinehaulHandover;
use App\Models\LinehaulTrip;
use App\Models\LogisticsHub;
use App\Services\TripHandover;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * "Scan driver QR" for hub staff: scan the linehaul driver's Handover QR (or type the 6-digit code), check the driver,
 * truck and trip, scan the parcels on (loading) or off (arrival) with live counts, and confirm. See App\Services\TripHandover.
 */
class HandoverController extends Controller
{
    public function __construct(private readonly TripHandover $handover) {}

    private function hubs()
    {
        return LogisticsHub::where('sorting_center_id', auth()->id())->orderBy('code')->get();
    }

    private function mine(LinehaulHandover $handover): LinehaulHandover
    {
        abort_unless((int) $handover->hub?->sorting_center_id === (int) auth()->id(), 404);

        return $handover;
    }

    private static function error(ValidationException $e): string
    {
        return collect($e->errors())->flatten()->first();
    }

    /** The scan page, with the trips waiting for a handover at the company's hubs. */
    public function index(Request $request)
    {
        $hubs = $this->hubs();
        $ids = $hubs->pluck('id');
        $waiting = LinehaulTrip::with('courier', 'origin', 'destination')->whereNotNull('courier_id')
            ->where(fn ($q) => $q->where(fn ($o) => $o->whereIn('origin_hub_id', $ids)->whereIn('status', ['accepted', 'loading'])->whereNull('sealed_at'))
                ->orWhere(fn ($d) => $d->whereIn('destination_hub_id', $ids)->where('status', 'arrived')))
            ->orderBy('id')->get()->filter(fn ($t) => TripHandover::stageOf($t))->values();
        $hubId = (int) ($request->old('hub_id') ?: $request->query('hub') ?: $request->session()->get('handover_hub'));
        $hubId = $hubs->firstWhere('id', $hubId)?->id ?? $hubs->first()?->id;
        // The camera pop-up sends only the code: it is checked at this hub.
        $request->session()->put('handover_hub', $hubId);

        return view('sc.handover', compact('hubs', 'waiting', 'hubId'));
    }

    /** hub_id + code (the QR's text, or the 6 digits). */
    public function verify(Request $request): RedirectResponse|JsonResponse
    {
        // The camera pop-up sends only the code: the hub is the one picked on the page (kept in the session).
        if ((int) $request->input('hub_id') > 0) {
            $request->session()->put('handover_hub', (int) $request->input('hub_id'));
        }
        $hubId = (int) ($request->input('hub_id') ?: $request->session()->get('handover_hub'));
        try {
            $h = $this->handover->verify(auth()->user(), $hubId, (string) $request->input('code', $request->input('tracking_number', '')));
        } catch (ValidationException $e) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => self::error($e)], 422)
                : back()->withInput()->with('error', self::error($e));
        }
        $url = route('sc.handover.show', $h);

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => 'Driver verified.', 'url' => $url])
            : redirect($url)->with('success', 'Driver verified. Check the details, then scan the parcels.');
    }

    public function show(LinehaulHandover $handover)
    {
        $h = $this->mine($handover)->load('hub', 'scanner', 'courier.user');
        $trip = $h->trip()->with('courier.user', 'origin', 'destination', 'company', 'destinationCompany')->firstOrFail();
        $items = $trip->onBoard()->with('parcel')->orderBy('id')->get();
        $open = ! $h->confirmed_at && TripHandover::stageOf($trip) === $h->stage;

        return view('sc.handover-show', ['h' => $h, 'trip' => $trip, 'items' => $items, 'counts' => $trip->counts(), 'open' => $open]);
    }

    /** One parcel on or off the truck: JSON for the scanner pop-up (and the live list), a redirect for the typed form. */
    public function scan(Request $request, LinehaulHandover $handover): RedirectResponse|JsonResponse
    {
        $h = $this->mine($handover);
        try {
            $r = $this->handover->scan($h, auth()->user(), (string) $request->input('tracking_number', ''));
        } catch (ValidationException $e) {
            return $request->expectsJson() ? response()->json(['ok' => false, 'message' => self::error($e)], 422) : back()->with('error', self::error($e));
        }

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'stay' => true, 'message' => $r['message'], 'counts' => $r['counts'], 'url' => route('sc.handover.show', $h)])
            : back()->with('success', $r['message']);
    }

    public function confirm(Request $request, LinehaulHandover $handover): RedirectResponse
    {
        $h = $this->mine($handover);
        try {
            $trip = $this->handover->confirm($h, auth()->user(), $request->input('note'));
        } catch (ValidationException $e) {
            return back()->withInput()->with('error', self::error($e));
        }

        return redirect()->route('sc.handover.show', $h)->with('success', $h->isLoad()
            ? "{$trip->code} is sealed. The driver can depart for {$trip->destination?->code}."
            : "{$trip->code} received: {$trip->label()}. The parcels wait in Parcel Sorting.");
    }

    /** Live counts for the open handover page (polled every few seconds). */
    public function live(LinehaulHandover $handover): JsonResponse
    {
        $h = $this->mine($handover);
        $trip = $h->trip;

        return response()->json([
            'counts' => $trip->counts(), 'status' => $trip->status, 'status_label' => $trip->label(),
            'items' => $trip->onBoard()->with('parcel:id,tracking_number')->get()->map(fn ($i) => ['tracking' => $i->parcel?->tracking_number, 'status' => $i->status])->values(),
            'confirmed' => (bool) $h->fresh()->confirmed_at,
        ]);
    }
}
