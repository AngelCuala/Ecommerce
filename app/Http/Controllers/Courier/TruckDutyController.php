<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Models\CourierChecklist;
use App\Models\Courier;
use App\Models\LinehaulTrip;
use App\Services\Linehaul;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Hub Trips on the website, for a logistics company's truck courier (4-wheelers, hub to hub):
 * go On Duty (pre-trip checklist and location) so the company can assign trips, stay Online while the
 * site is open (a ping every minute), go Off Duty, and accept or decline an assigned trip.
 * Same rules as the courier app (App\Services\Linehaul); loading, sealing and departing stay in the app.
 */
class TruckDutyController extends Controller
{
    public function __construct(private readonly Linehaul $linehaul) {}

    /** The signed-in truck courier (riders and self-registered couriers don't have Hub Trips). */
    public static function truckCourier(): ?Courier
    {
        $c = Courier::where('user_id', auth()->id())->whereNotNull('home_hub_id')->with('homeHub')->first();

        return $c && in_array($c->status, ['approved', 'suspended'], true) ? $c : null;
    }

    private function me(): Courier
    {
        $c = self::truckCourier();
        abort_unless($c, 403, 'Hub Trips are for truck couriers of a logistics company.');

        return $c;
    }

    private function loc(Request $r): array
    {
        return [is_numeric($r->input('lat')) ? (float) $r->input('lat') : null, is_numeric($r->input('lng')) ? (float) $r->input('lng') : null];
    }

    public function index()
    {
        $c = $this->me();
        // Opening the page counts as being seen: an On Duty courier is Online again right away.
        if ($c->on_duty) {
            $c->forceFill(['last_seen_at' => now()])->save();
        }
        $trip = $c->activeTrip()?->load('origin', 'destination', 'destinationCompany');
        $parcels = $trip ? $trip->onBoard()->with('parcel')->orderBy('id')->get() : collect();
        $needsNote = LinehaulTrip::where('paid_courier_id', $c->id)->where('needs_courier_note', true)->with('origin', 'destination')->get();
        $recent = LinehaulTrip::where('paid_courier_id', $c->id)->whereNotNull('completed_at')->with('origin', 'destination')
            ->latest('completed_at')->limit(5)->get();
        $today = (float) LinehaulTrip::where('paid_courier_id', $c->id)->whereDate('completed_at', today())->sum('pay');
        $lastCheck = CourierChecklist::where('courier_id', $c->id)->latest('id')->first();

        return view('courier.trips', [
            'courier' => $c, 'trip' => $trip, 'recent' => $recent, 'today' => $today, 'lastCheck' => $lastCheck,
            'items' => CourierChecklist::ITEMS, 'declineReasons' => Linehaul::DECLINE_REASONS, 'delayReasons' => Linehaul::DELAY_REASONS,
            'parcels' => $parcels, 'needsNote' => $needsNote,
        ]);
    }

    /** on=1 with the checklist answers (and a note for any Not OK) and the location; on=0 to go Off Duty. */
    public function duty(Request $request): RedirectResponse
    {
        $c = $this->me();
        [$lat, $lng] = $this->loc($request);
        try {
            $message = $request->boolean('on')
                ? $this->linehaul->goOnDuty($c, (array) $request->input('items', []), $request->input('note'), $lat, $lng)
                : $this->linehaul->goOffDuty($c);
        } catch (ValidationException $e) {
            return back()->withInput()->with('error', collect($e->errors())->flatten()->first());
        }

        // Online / Offline, the same words a rider sees (Online = On Duty for the company's trip list).
        $c->refresh();
        if ($request->boolean('on')) {
            return $c->on_duty
                ? back()->with('success', "You're Online. {$c->hubName()} can assign you hub-to-hub trips.")
                : back()->with('error', str_replace(['You stay Off Duty', 'Off Duty'], ['You stay Offline', 'Offline'], $message));
        }

        return back()->with('success', "You're Offline. You won't get trips until you go Online again.");
    }

    /** Every minute while a page is open: keeps an On Duty courier Online (and the trip's location fresh). */
    public function ping(Request $request): JsonResponse
    {
        $c = $this->me();
        [$lat, $lng] = $this->loc($request);
        if ($lat !== null && $lng !== null) {
            $this->linehaul->ping($c, $lat, $lng);
        } else {
            $c->forceFill(['last_seen_at' => now()])->save();
        }
        $c->refresh();

        return response()->json(['on_duty' => (bool) $c->on_duty, 'duty_label' => $c->isSuspended() ? 'Suspended' : $c->dutyLabel()]);
    }

    public function accept(int $trip): RedirectResponse
    {
        try {
            $t = $this->linehaul->accept($this->me(), $trip);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', "You accepted {$t->code}. Open the ALVY app at {$t->origin?->code} to load and scan the parcels.");
    }

    /** Runs a step of the trip; a rule it breaks comes back as the page's error message. */
    private function step(callable $do, string $success): RedirectResponse
    {
        try {
            $t = $do($this->me());
        } catch (ValidationException $e) {
            return back()->withInput()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', is_string($t) ? $t : str_replace(':code', $t->code, $success));
    }

    /** At the origin hub: start loading (the receiving sorting center must have accepted the trip). */
    public function startLoading(int $trip): RedirectResponse
    {
        return $this->step(fn (Courier $c) => $this->linehaul->startLoading($c, $trip),
            'Loading :code. Scan the QR code on every parcel as it goes on the truck.');
    }

    /** One waybill scanned (camera, photo or typed): JSON for the scanner pop-up, a redirect for the typed form. */
    public function scan(Request $request, int $trip): JsonResponse|RedirectResponse
    {
        $tracking = (string) $request->input('tracking_number', '');
        try {
            $r = $this->linehaul->scan($this->me(), $trip, $tracking);
        } catch (ValidationException $e) {
            $msg = collect($e->errors())->flatten()->first();

            return $request->expectsJson() ? response()->json(['ok' => false, 'message' => $msg], 422) : back()->with('error', $msg);
        }
        $msg = $r['message'] . ($r['loaded'] === $r['total'] ? ' Every parcel is on board: seal the truck and depart.' : '');

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'message' => $msg, 'url' => route('courier.trips', ['loaded' => $r['loaded']]) . '#trip'] + $r)
            : back()->with('success', $msg);
    }

    /** Every parcel loaded, the seal number, at the origin hub. */
    public function depart(Request $request, int $trip): RedirectResponse
    {
        [$lat, $lng] = $this->loc($request);

        return $this->step(fn (Courier $c) => $this->linehaul->depart($c, $trip, $request->input('seal_number'), $lat, $lng),
            ':code departed. Drive safely; report a delay here if anything holds you up.');
    }

    /** In Transit: a reason and a new ETA. */
    public function delay(Request $request, int $trip): RedirectResponse
    {
        return $this->step(fn (Courier $c) => $this->linehaul->delay($c, $trip, $request->input('reason'), $request->input('eta')),
            'Delay on :code reported. Both sorting centers were told.');
    }

    /** At the destination hub. */
    public function arrived(Request $request, int $trip): RedirectResponse
    {
        [$lat, $lng] = $this->loc($request);

        return $this->step(fn (Courier $c) => $this->linehaul->arrived($c, $trip, $lat, $lng),
            ':code arrived. The receiving hub checks the seal and scans the parcels in.');
    }

    /** The courier's note about a missing or damaged parcel, when the receiving hub asks for one. */
    public function note(Request $request, int $trip): RedirectResponse
    {
        return $this->step(fn (Courier $c) => $this->linehaul->courierNote($c, $trip, $request->input('note')),
            'Your note on :code was sent.');
    }

    /**
     * Every trip once, like a rider's Job History: Completed (paid) and Declined / Reassigned (₱0).
     * Each row: at, code, route, parcels, result, reason, pay.
     */
    public static function tripHistory(Courier $c, ?\Carbon\Carbon $from = null, ?\Carbon\Carbon $to = null): \Illuminate\Support\Collection
    {
        $in = fn ($q, string $col) => $q->when($from, fn ($q) => $q->where($col, '>=', $from))->when($to, fn ($q) => $q->where($col, '<=', $to));
        $rows = collect();
        foreach ($in(LinehaulTrip::where('paid_courier_id', $c->id)->whereIn('status', LinehaulTrip::DONE), 'completed_at')->with('origin', 'destination')->get() as $t) {
            $rows->push(['at' => $t->completed_at, 'code' => $t->code, 'route' => $t->route(), 'parcels' => $t->onBoard()->count(),
                'result' => $t->label(), 'reason' => null, 'pay' => (float) $t->pay]);
        }
        $events = $in(\App\Models\LinehaulTripEvent::where('courier_id', $c->id)->whereIn('type', ['declined', 'reassigned_away', 'unassigned']), 'created_at')
            ->with('trip.origin', 'trip.destination')->get();
        foreach ($events as $e) {
            $rows->push(['at' => $e->created_at, 'code' => $e->trip?->code, 'route' => $e->trip?->route(), 'parcels' => $e->trip?->items()->count(),
                'result' => $e->type === 'declined' ? 'Declined' : 'Reassigned', 'reason' => $e->reason, 'pay' => 0.0]);
        }

        return $rows->sortByDesc('at')->values();
    }

    public function history(Request $request)
    {
        $c = $this->me();
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from'], ['to.after_or_equal' => 'The end date must be on or after the start date.']);
        $from = $request->filled('from') ? \Carbon\Carbon::parse($request->from)->startOfDay() : null;
        $to = $request->filled('to') ? \Carbon\Carbon::parse($request->to)->endOfDay() : null;

        return view('courier.trip-history', ['courier' => $c, 'rows' => self::tripHistory($c, $from, $to)]);
    }

    /** Trip Pay, like a rider's pay page: today / this week / this month, a period's trips, day by day. */
    public function pay(Request $request)
    {
        $c = $this->me();
        $periods = \App\Http\Controllers\Courier\RiderController::PAY_PERIODS;
        $request->validate(['period' => 'nullable|in:' . implode(',', array_keys($periods))]);
        $period = $request->input('period', 'week');
        $tz = config('app.business_timezone', 'Asia/Manila');
        $now = now($tz);
        $ranges = [
            'today'     => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'week'      => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week' => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'month'     => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
        ];
        $all = self::tripHistory($c);
        $in = fn (array $r) => $all->filter(fn ($j) => $j['at']->between($r[0], $r[1]))->values();
        $sum = fn ($jobs) => round((float) $jobs->sum('pay'), 2);
        $cards = collect(['today', 'week', 'month'])->mapWithKeys(fn ($k) => [$k => ['pay' => $sum($in($ranges[$k])), 'trips' => $in($ranges[$k])->where('pay', '>', 0)->count()]]);

        [$from, $to] = $ranges[$period];
        $trips = $in($ranges[$period]);
        $days = collect();
        if ($period !== 'today') {
            for ($d = $from->copy()->startOfDay(); $d->lte($to) && $d->lte($now); $d->addDay()) {
                $day = $trips->filter(fn ($j) => $j['at']->copy()->tz($tz)->isSameDay($d));
                $days->push(['label' => $period === 'month' ? $d->format('M j') : $d->format('D, M j'), 'pay' => $sum($day), 'trips' => $day->count(), 'today' => $d->isSameDay($now)]);
            }
            $days = $period === 'month' ? $days->filter(fn ($d) => $d['trips'] > 0)->values() : $days;
        }
        $active = $c->activeTrip();

        return view('courier.trip-pay', [
            'courier' => $c, 'tz' => $tz, 'period' => $period, 'periods' => $periods, 'from' => $from, 'to' => $to,
            'cards' => $cards, 'trips' => $trips, 'days' => $days, 'peak' => max(1, (float) $days->max('pay')),
            'perTrip' => Linehaul::payFor($c), 'pending' => $active ? Linehaul::payFor($c) : 0.0, 'active' => $active,
            'total' => $sum($trips), 'paidCount' => $trips->where('pay', '>', 0)->count(), 'zeroCount' => $trips->where('pay', 0)->count(),
        ]);
    }

    public function decline(Request $request, int $trip): RedirectResponse
    {
        try {
            $t = $this->linehaul->decline($this->me(), $trip, $request->input('reason'));
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first());
        }

        return back()->with('success', "You declined {$t->code}. The company was told.");
    }
}
