<?php

namespace App\Http\Controllers\Api\V1\Linehaul;

use App\Http\Controllers\Controller;
use App\Models\Courier;
use App\Models\CourierChecklist;
use App\Models\LinehaulTrip;
use App\Models\LinehaulTripEvent;
use App\Models\UserNotification;
use App\Http\Controllers\Api\V1\Logistics\StaffController;
use App\Services\Linehaul;
use App\Support\LinehaulJson;
use App\Support\PersonRules;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * The courier app (a linehaul courier made by the company): duty and checklist, trips (accept, decline, load,
 * depart, delay, arrive), history, earnings and profile. Every call is about the signed-in courier only.
 */
class CourierTripsController extends Controller
{
    public function __construct(private readonly Linehaul $linehaul) {}

    /** The signed-in courier's linehaul record (403 for riders and self-registered couriers). */
    private function me(): Courier
    {
        $c = Courier::where('user_id', auth()->id())->whereNotNull('home_hub_id')->first();
        abort_unless($c && in_array($c->status, ['approved', 'suspended'], true), 403, 'This part of the app is for couriers of a logistics company.');

        return $c;
    }

    private function loc(Request $r): array
    {
        $lat = $r->input('lat');
        $lng = $r->input('lng');

        return [is_numeric($lat) ? (float) $lat : null, is_numeric($lng) ? (float) $lng : null];
    }

    private function tripJson(LinehaulTrip $t, string $message = ''): JsonResponse
    {
        return response()->json(array_filter(['message' => $message]) + ['trip' => LinehaulJson::trip($t->fresh(), true)]);
    }

    // ── Home, duty ───────────────────────────────────────────────

    public function home(): JsonResponse
    {
        $c = $this->me();
        $trip = $c->activeTrip();
        $today = (float) LinehaulTrip::where('paid_courier_id', $c->id)->whereDate('completed_at', today())->sum('pay');

        return response()->json([
            'courier' => LinehaulJson::courier($c) + ['company' => $c->hubName(), 'home_hub_label' => $c->homeHub?->label()],
            'trip' => $trip ? LinehaulJson::trip($trip) : null,
            'needs_note' => LinehaulTrip::where('paid_courier_id', $c->id)->where('needs_courier_note', true)->get()->map(fn ($t) => ['id' => $t->id, 'code' => $t->code])->values(),
            'checklist_items' => CourierChecklist::ITEMS,
            'decline_reasons' => Linehaul::DECLINE_REASONS,
            'delay_reasons' => Linehaul::DELAY_REASONS,
            'today_earnings' => round($today, 2),
            'unread_notifications' => UserNotification::where('user_id', auth()->id())->whereNull('read_at')->count(),
        ]);
    }

    /** on (true/false); going on: items {Tires: ok|not_ok, …}, note, lat, lng. */
    public function duty(Request $r): JsonResponse
    {
        $c = $this->me();
        [$lat, $lng] = $this->loc($r);
        $message = $r->boolean('on')
            ? $this->linehaul->goOnDuty($c, (array) $r->input('items', []), $r->input('note'), $lat, $lng)
            : $this->linehaul->goOffDuty($c);

        return response()->json(['message' => $message] + $this->home()->getData(true));
    }

    public function ping(Request $r): JsonResponse
    {
        $c = $this->me();
        [$lat, $lng] = $this->loc($r);
        if ($lat === null || $lng === null) {
            throw ValidationException::withMessages(['location' => 'No location was sent.']);
        }
        $this->linehaul->ping($c, $lat, $lng);

        return response()->json(['ok' => true, 'duty_label' => $c->fresh()->dutyLabel()]);
    }

    // ── Trips ────────────────────────────────────────────────────

    public function trip(int $id): JsonResponse
    {
        return $this->tripJson($this->linehaul->courierTrip($this->me(), $id));
    }

    public function accept(int $id): JsonResponse
    {
        $t = $this->linehaul->accept($this->me(), $id);
        return $this->tripJson($t, "You accepted {$t->code}. Tap Start Loading at {$t->origin->code}.");
    }

    public function decline(Request $r, int $id): JsonResponse
    {
        $t = $this->linehaul->decline($this->me(), $id, $r->input('reason'));
        return response()->json(['message' => "You declined {$t->code}. It pays you ₱0.00."]);
    }

    public function loading(int $id): JsonResponse
    {
        $t = $this->linehaul->startLoading($this->me(), $id);
        return $this->tripJson($t, 'Loading: scan every parcel.');
    }

    public function scan(Request $r, int $id): JsonResponse
    {
        $res = $this->linehaul->scan($this->me(), $id, (string) $r->input('tracking'));
        return $this->tripJson($this->linehaul->courierTrip($this->me(), $id), $res['message']);
    }

    public function depart(Request $r, int $id): JsonResponse
    {
        [$lat, $lng] = $this->loc($r);
        $t = $this->linehaul->depart($this->me(), $id, $r->input('seal_number'), $lat, $lng);
        return $this->tripJson($t, "{$t->code} departed: In Transit to {$t->destination->code}.");
    }

    /** reason, eta (date-time), photo (Vehicle breakdown). */
    public function delay(Request $r, int $id): JsonResponse
    {
        $r->validate(['photo' => 'nullable|file|mimes:jpg,jpeg,png|mimetypes:image/jpeg,image/png|max:5120'],
            ['photo.*' => 'The photo must be a JPG or PNG of up to 5 MB.']);
        $t = $this->linehaul->delay($this->me(), $id, $r->input('reason'), $r->input('eta'), $r->file('photo'));
        return $this->tripJson($t, $t->delay_reason === 'Vehicle breakdown'
            ? 'Breakdown reported. ' . $t->company?->name . ' may give the trip to another courier.'
            : 'Delay reported. The new ETA was sent to your logistics company.');
    }

    public function arrived(Request $r, int $id): JsonResponse
    {
        [$lat, $lng] = $this->loc($r);
        $t = $this->linehaul->arrived($this->me(), $id, $lat, $lng);
        return $this->tripJson($t, "Arrived at {$t->destination->code}. Hub staff will check the seal and scan the parcels in.");
    }

    public function note(Request $r, int $id): JsonResponse
    {
        $t = $this->linehaul->courierNote($this->me(), $id, $r->input('note'));
        return $this->tripJson($t, 'Your note was saved.');
    }

    // ── History, earnings ────────────────────────────────────────

    /** from, to (Y-m-d): both days included; the end can't be before the start. */
    private function range(Request $r): array
    {
        $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date' . ($r->filled('from') ? '|after_or_equal:from' : '')],
            ['to.after_or_equal' => 'The end date cannot be before the start date.']);

        return [$r->filled('from') ? Carbon::parse($r->input('from'))->startOfDay() : null, $r->filled('to') ? Carbon::parse($r->input('to'))->endOfDay() : null];
    }

    /** Every trip once: Completed / Completed with Discrepancy (paid), Declined and Reassigned (₱0.00). */
    public function history(Request $r): JsonResponse
    {
        $c = $this->me();
        [$from, $to] = $this->range($r);
        $in = fn ($q, string $col) => $q->when($from, fn ($q) => $q->where($col, '>=', $from))->when($to, fn ($q) => $q->where($col, '<=', $to));

        $rows = collect();
        foreach ($in(LinehaulTrip::where('paid_courier_id', $c->id)->whereIn('status', LinehaulTrip::DONE), 'completed_at')->get() as $t) {
            $rows->push(['trip_id' => $t->id, 'code' => $t->code, 'date' => $t->completed_at->toIso8601String(), 'route' => $t->route(),
                'parcels' => $t->onBoard()->count(), 'result' => $t->label(), 'pay' => (float) $t->pay]);
        }
        $events = $in(LinehaulTripEvent::where('courier_id', $c->id)->whereIn('type', ['declined', 'reassigned_away', 'unassigned']), 'created_at')->with('trip')->get();
        foreach ($events as $e) {
            $rows->push(['trip_id' => $e->linehaul_trip_id, 'code' => $e->trip?->code, 'date' => $e->created_at->toIso8601String(), 'route' => $e->trip?->route(),
                'parcels' => $e->trip?->items()->count(), 'result' => $e->type === 'declined' ? 'Declined' : 'Reassigned', 'reason' => $e->reason, 'pay' => 0.0]);
        }

        return response()->json(['data' => $rows->sortByDesc('date')->values(), 'from' => $from?->toDateString(), 'to' => $to?->toDateString()]);
    }

    public function earnings(Request $r): JsonResponse
    {
        $c = $this->me();
        [$from, $to] = $this->range($r);
        $paid = LinehaulTrip::where('paid_courier_id', $c->id)->whereIn('status', LinehaulTrip::DONE);
        $sum = fn ($start, $end) => round((float) (clone $paid)->whereBetween('completed_at', [$start, $end])->sum('pay'), 2);
        $trips = (clone $paid)->when($from, fn ($q) => $q->where('completed_at', '>=', $from))->when($to, fn ($q) => $q->where('completed_at', '<=', $to))
            ->latest('completed_at')->get();

        return response()->json([
            'today' => $sum(now()->startOfDay(), now()->endOfDay()),
            'week' => $sum(now()->startOfWeek(), now()->endOfWeek()),
            'range_total' => round((float) $trips->sum('pay'), 2),
            'pay_per_trip' => Linehaul::payFor($c), 'vehicle_type' => $c->vehicle_type,
            'trips' => $trips->map(fn ($t) => ['trip_id' => $t->id, 'code' => $t->code, 'route' => $t->route(), 'result' => $t->label(),
                'completed_at' => $t->completed_at->toIso8601String(), 'pay' => (float) $t->pay])->values(),
        ]);
    }

    // ── Profile ──────────────────────────────────────────────────

    public function profile(): JsonResponse
    {
        $c = $this->me();

        return response()->json(['profile' => [
            'name' => $c->fullName(), 'email' => $c->user?->email, 'phone' => $c->contact_no,
            'license_number' => $c->license_number, 'license_type' => $c->license_type, 'dl_codes' => $c->dl_codes,
            'license_expiry' => $c->license_expiry?->toDateString(), 'vehicle_type' => $c->vehicle_type, 'plate_number' => $c->plate_number,
            'load_capacity_kg' => $c->load_capacity_kg, 'home_hub' => $c->homeHub?->label(), 'company' => $c->hubName(),
            'status' => $c->isSuspended() ? 'Suspended' : 'Active', 'needs_reverification' => (bool) $c->needs_reverification,
            'documents' => ['license' => (bool) $c->id_license_path, 'or-cr' => (bool) $c->or_cr_path, 'nbi' => (bool) $c->nbi_path],
            'capacity_range' => StaffController::COURIER_VEHICLES[$c->vehicle_type] ?? null,
        ]]);
    }

    /** Multipart: load_capacity_kg, plate_number, license_expiry, license_file, or_cr_file, nbi_file (each optional). */
    public function updateProfile(Request $r): JsonResponse
    {
        $c = $this->me();
        $trip = $c->activeTrip();
        if ($trip && in_array($trip->status, ['loading', 'in_transit', 'arrived'], true)) {
            throw ValidationException::withMessages(['trip' => "Finish {$trip->code} before changing your vehicle or documents."]);
        }
        if ($r->has('plate_number')) {
            $r->merge(['plate_number' => strtoupper(preg_replace('/\s+/', '', (string) $r->input('plate_number')))]);
        }
        $v = StaffController::COURIER_VEHICLES[$c->vehicle_type] ?? ['min' => 1, 'max' => 99999];
        $doc = array_merge(['sometimes'], array_slice(PersonRules::document(), 1));
        $data = $r->validate([
            'load_capacity_kg' => ['sometimes', 'regex:/^\d+$/', 'integer', 'min:' . $v['min'], 'max:' . $v['max']],
            'plate_number'     => array_merge(['sometimes'], array_slice(PersonRules::plate(null, $c->id), 1)),
            'license_expiry'   => 'sometimes|date|after_or_equal:today',
            'license_file'     => $doc, 'or_cr_file' => $doc, 'nbi_file' => $doc,
        ], PersonRules::staffMessages(['license_file' => 'The driver\'s license', 'or_cr_file' => 'The OR/CR', 'nbi_file' => 'The NBI clearance']) + [
            'load_capacity_kg.*' => 'A ' . $c->vehicle_type . ' carries ' . number_format($v['min']) . ' to ' . number_format($v['max']) . ' kg (whole kg).',
        ]);

        $updates = array_intersect_key($data, array_flip(['load_capacity_kg', 'plate_number', 'license_expiry']));
        foreach (['license_file' => 'id_license_path', 'or_cr_file' => 'or_cr_path', 'nbi_file' => 'nbi_path'] as $field => $column) {
            if ($r->hasFile($field)) {
                $updates[$column] = $r->file($field)->store('courier-documents', \App\Support\ProtectedFile::DISK);
            }
        }
        if (! $updates) {
            throw ValidationException::withMessages(['profile' => 'Nothing to save.']);
        }
        $c->update($updates + ['needs_reverification' => true]);
        UserNotification::create(['user_id' => $c->sorting_center_id, 'title' => 'Courier details changed', 'type' => 'courier',
            'body' => $c->fullName() . ' changed ' . implode(', ', array_map(fn ($k) => str_replace(['_path', '_kg', '_'], ['', '', ' '], $k), array_keys($updates))) . '. Check and verify them.']);

        return response()->json(['message' => 'Saved. Your logistics company will check the changes.'] + $this->profile()->getData(true));
    }

    public function document(string $document): mixed
    {
        $c = $this->me();
        $path = ['license' => $c->id_license_path, 'or-cr' => $c->or_cr_path, 'nbi' => $c->nbi_path][$document] ?? null;
        abort_unless($path, 404);

        return \App\Support\ProtectedFile::response($path);   // kept on the private disk
    }
}
