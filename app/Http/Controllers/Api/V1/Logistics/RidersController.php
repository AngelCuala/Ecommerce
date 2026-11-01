<?php

namespace App\Http\Controllers\Api\V1\Logistics;

use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SortingCenter\CourierApplicationController as WebCouriers;
use App\Http\Controllers\SortingCenter\LogisticsController as WebLogistics;
use App\Http\Controllers\SortingCenter\RiderWorkController as WebRiderWork;
use App\Http\Controllers\SortingCenterController as WebSc;
use App\Models\Courier;
use App\Models\Parcel;
use App\Models\Rider;
use App\Models\RiderRemittance;
use App\Services\RiderDispatch;
use App\Support\ApiJson;
use App\Support\LogisticsJson;
use Illuminate\Http\JsonResponse;

/** The logistics company's riders (the web's sc.riders, dispatch, remittances and courier applications). */
class RidersController extends Controller
{
    use CallsWeb;

    private function with(mixed $response, callable $page): JsonResponse
    {
        $json = $this->actionJson($response);
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + $page()->getData(true));
        }
        return $json;
    }

    // ── Dispatch board (offers, manual assignment, Unassigned queue, reassign) ──

    public function dispatch(): JsonResponse
    {
        $d = $this->pageData($this->web(WebRiderWork::class, 'dispatchBoard'));
        $job = fn (Parcel $p) => LogisticsJson::parcel($p) + [
            'job_kind' => $p->job_kind,
            'offer' => $p->job_offer ? ['rider' => $p->job_offer->rider?->full_name, 'seconds_left' => $p->job_offer->secondsLeft()] : null,
            'unassigned' => (bool) $p->unassigned,
        ];

        return response()->json([
            'jobs'        => $d['jobs']->map($job)->values(),
            'unassigned'  => $d['unassigned']->map($job)->values(),
            'riders'      => $d['riders']->map(fn ($r) => LogisticsJson::rider($r) + ['active_jobs' => (int) $r->active_jobs])->values(),
            'eligible_ids' => collect($d['eligible'])->pluck('id')->values(),
            // The website's "Choose rider" list: available riders first, the rest with why not (suspended, offline, full).
            'rider_choices' => collect($d['riderChoices'] ?? [])->map(fn ($c) => ['id' => $c->rider->id, 'name' => $c->rider->full_name,
                'available' => (bool) $c->available, 'why' => $c->available ? null : $c->why, 'active_jobs' => (int) $c->rider->active_jobs])->values(),
            'with_riders' => $d['withRiders']->map(fn ($x) => ['tracking' => $x->parcel?->tracking_number, 'rider' => $x->rider?->full_name,
                'status' => $x->status, 'status_label' => $x->statusLabel(), 'picked_up_at' => $x->picked_up_at?->toIso8601String()])->values(),
            'not_started' => $d['notStarted']->map(fn ($x) => ['kind' => $x->kind, 'parcel_id' => $x->parcel?->id, 'tracking' => $x->parcel?->tracking_number,
                'rider' => $x->rider?->full_name, 'rider_id' => $x->rider?->id, 'since' => $x->since?->toIso8601String()])->values(),
            'max_active'  => RiderDispatch::MAX_ACTIVE,
            'offer_seconds' => RiderDispatch::OFFER_SECONDS,
        ]);
    }

    public function autoAssign(int $parcel): JsonResponse
    {
        return $this->with($this->web(WebRiderWork::class, 'autoAssign', ['parcel' => Parcel::findOrFail($parcel)]), fn () => $this->dispatch());
    }

    /** rider_id. */
    public function manualAssign(int $parcel): JsonResponse
    {
        return $this->with($this->web(WebRiderWork::class, 'manualAssign', ['parcel' => Parcel::findOrFail($parcel)]), fn () => $this->dispatch());
    }

    /** rider_id: a job not started yet goes to another rider. */
    public function reassign(int $parcel): JsonResponse
    {
        return $this->with($this->web(WebLogistics::class, 'reassign', ['parcel' => Parcel::findOrFail($parcel)]), fn () => $this->dispatch());
    }

    /** rider_id, parcel_ids[]. */
    public function bulkAssign(): JsonResponse
    {
        return $this->with($this->web(WebLogistics::class, 'bulkAssign'), fn () => $this->dispatch());
    }

    // ── Riders ───────────────────────────────────────────────────

    /** tab: all, pending, active, inactive, rejected · page. */
    public function riders(): JsonResponse
    {
        $d = $this->pageData($this->web(WebSc::class, 'riders'));

        return response()->json(ApiJson::page($d['riders'], fn ($r) => LogisticsJson::rider($r)) + [
            'tabs' => collect(['all' => 'All', 'pending' => 'Pending', 'active' => 'Active', 'inactive' => 'Suspended', 'rejected' => 'Rejected'])
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => (int) ($d['counts'][$key] ?? 0)])->values(),
            'municipality' => $d['sc']->assigned_municipality,
            'has_municipality' => $d['sc']->hasAssignedMunicipality(),
            'barangays' => collect($d['barangays'])->map(fn ($b) => ['psgc' => $b['psgc'], 'code' => $b['code'] ?? null, 'name' => $b['name'],
                'covered' => in_array($b['code'] ?? null, $d['coveredCodes'], true)])->values(),
        ]);
    }

    /** full_name, phone, vehicle_type, barangay_psgc, barangay_code. */
    public function storeRider(): JsonResponse
    {
        // Just the message: the app reloads the list (it carries every barangay of the municipality, which is large).
        return $this->actionJson($this->web(WebSc::class, 'storeRider'));
    }

    public function rider(Rider $rider): JsonResponse
    {
        $d = $this->pageData($this->web(WebRiderWork::class, 'showRider', ['rider' => $rider]));
        return response()->json(['rider' => LogisticsJson::rider($d['rider'], true)]);
    }

    public function approve(Rider $rider): JsonResponse { return $this->with($this->web(WebSc::class, 'approveRider', ['rider' => $rider]), fn () => $this->rider($rider->fresh())); }
    /** reason (10–300 characters). */
    public function reject(Rider $rider): JsonResponse  { return $this->with($this->web(WebSc::class, 'rejectRider', ['rider' => $rider]), fn () => $this->rider($rider->fresh())); }
    /** Suspend (reason, 10–300 characters) or reactivate. */
    public function toggle(Rider $rider): JsonResponse  { return $this->with($this->web(WebSc::class, 'toggleRider', ['rider' => $rider]), fn () => $this->rider($rider->fresh())); }
    public function verify(Rider $rider): JsonResponse  { return $this->with($this->web(WebRiderWork::class, 'verifyRider', ['rider' => $rider]), fn () => $this->rider($rider->fresh())); }

    /** license / or-cr. */
    public function document(Rider $rider, string $document): mixed
    {
        return $this->web(WebRiderWork::class, 'riderDocument', ['rider' => $rider, 'document' => $document]);
    }

    // ── COD remittances ──────────────────────────────────────────

    /** date (Y-m-d) for the day's reconciliation · page. */
    public function remittances(): JsonResponse
    {
        $d = $this->pageData($this->web(WebRiderWork::class, 'remittances'));

        return response()->json(ApiJson::page($d['remittances'], fn ($r) => LogisticsJson::remittance($r)) + [
            'date' => $d['date']->toDateString(),
            'riders' => collect($d['riders'])->map(fn ($r) => ['id' => $r['rider']->id, 'name' => $r['rider']->full_name,
                'collected' => round((float) $r['collected'], 2), 'balance' => round((float) $r['balance'], 2), 'pending' => round((float) $r['pending'], 2)])->values(),
            'daily' => collect($d['daily'])->map(fn ($r) => ['rider' => $r['rider']->full_name, 'collected' => $r['collected'],
                'remitted' => $r['remitted'], 'difference' => $r['difference'],
                'orders' => $r['orders']->map(fn ($x) => ['tracking' => $x->parcel?->tracking_number, 'order_number' => $x->parcel?->order?->number(),
                    'cod' => (float) $x->cod_collected, 'delivered_at' => $x->delivered_at?->toIso8601String()])->values()])->values(),
        ]);
    }

    public function confirmRemittance(RiderRemittance $remittance): JsonResponse
    {
        return $this->with($this->web(WebRiderWork::class, 'confirmRemittance', ['remittance' => $remittance]), fn () => $this->remittances());
    }

    // ── Courier applications ─────────────────────────────────────

    /** status: approved, pending_activation (added by the company), pending (applications), rejected, suspended · page. */
    public function couriers(): JsonResponse
    {
        $d = $this->pageData($this->web(WebCouriers::class, 'index'));

        return response()->json(ApiJson::page($d['couriers'], fn ($c) => LogisticsJson::courier($c)) + [
            'status' => $d['status'],
            'tabs' => collect(['approved' => 'Active', 'pending_activation' => 'Pending Activation', 'pending' => 'Applications', 'rejected' => 'Rejected', 'suspended' => 'Suspended'])
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => (int) ($d['counts'][$key] ?? 0)])->values(),
        ]);
    }

    public function courier(Courier $courier): JsonResponse
    {
        $d = $this->pageData($this->web(WebCouriers::class, 'show', ['courier' => $courier]));
        return response()->json(['courier' => LogisticsJson::courier($d['courier'], true)]);
    }

    public function approveCourier(Courier $courier): JsonResponse
    {
        return $this->actionJson($this->web(WebCouriers::class, 'approve', ['courier' => $courier, 'review' => app(\App\Services\CourierReview::class)]));
    }

    /** rejection_reason. */
    public function rejectCourier(Courier $courier): JsonResponse
    {
        return $this->actionJson($this->web(WebCouriers::class, 'reject', ['courier' => $courier, 'review' => app(\App\Services\CourierReview::class)]));
    }

    /** license / or-cr. */
    public function courierDocument(Courier $courier, string $document): mixed
    {
        return $this->web(WebCouriers::class, 'document', ['courier' => $courier, 'document' => $document]);
    }
}
