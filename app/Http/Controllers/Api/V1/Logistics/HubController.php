<?php

namespace App\Http\Controllers\Api\V1\Logistics;

use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SortingCenter\LogisticsController as WebLogistics;
use App\Http\Controllers\SortingCenterController as WebSc;
use App\Models\DeliveryArea;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\ParcelTransfer;
use App\Support\ApiJson;
use App\Support\LogisticsJson;
use Illuminate\Http\JsonResponse;

/**
 * The parcel's way through the hub (the web's sorting-center pages): dashboard, shipments, pickup requests,
 * receiving, sorting, delivery assignment and monitoring, Return to Sender, Lost / Damaged and transfers.
 */
class HubController extends Controller
{
    use CallsWeb;

    /** An action's message plus a page as it is now (when it succeeded). */
    private function with(mixed $response, callable $page): JsonResponse
    {
        $json = $this->actionJson($response);
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + $page()->getData(true));
        }
        return $json;
    }

    // ── Dashboard and shipments (L1, L5) ─────────────────────────

    /** from, to (Y-m-d). */
    public function dashboard(): JsonResponse
    {
        $d = $this->pageData($this->web(WebSc::class, 'dashboard'));

        return response()->json([
            'stats' => $d['stats'],
            'shipment_counts' => collect(LogisticsJson::BUCKET_LABELS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label,
                'count' => (int) ($d['shipmentCounts'][$key] ?? 0)])->values(),
            'recent' => $d['recentParcels']->map(fn ($x) => LogisticsJson::delivery($x))->values(),
            'area_rates' => $d['areaRates']->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'total' => (int) $a->total,
                'delivered' => (int) $a->delivered, 'rate' => $a->total > 0 ? (int) round($a->delivered / $a->total * 100) : 0])->values(),
        ]);
    }

    /** status (bucket), q (tracking, order no. or receiver), from, to, page. */
    public function shipments(): JsonResponse
    {
        $d = $this->pageData($this->web(WebLogistics::class, 'shipments'));

        return response()->json(ApiJson::page($d['parcels'], fn ($p) => LogisticsJson::parcel($p)) + [
            'status' => $d['status'], 'q' => $d['q'],
            'tabs' => collect(LogisticsJson::BUCKET_LABELS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label,
                'count' => (int) ($d['counts'][$key] ?? 0)])->values(),
        ]);
    }

    public function parcel(int $parcel): JsonResponse
    {
        // This company's parcels, and pickup requests not routed to any company yet (the web's pickup-request list shows those too).
        $p = Parcel::where(fn ($q) => $q->where('current_sorting_center_id', auth()->id())
                ->orWhere(fn ($w) => $w->whereNull('current_sorting_center_id')->where('status', 'pending_pickup')))
            ->with(['order', 'seller.sellerApplication', 'area', 'rtsRider'])->findOrFail($parcel);
        $deliveries = ParcelDelivery::where('parcel_id', $p->id)->with('rider', 'area', 'parcel')->orderBy('attempt')->get();

        return response()->json(['parcel' => LogisticsJson::parcel($p) + [
            'rts_rider' => $p->rtsRider?->full_name,
            'deliveries' => $deliveries->map(fn ($x) => LogisticsJson::delivery($x))->values(),
            'pickups' => \App\Models\ParcelPickup::where('parcel_id', $p->id)->with('rider')->latest()->get()->map(fn ($x) => [
                'status' => $x->status, 'status_label' => $x->statusLabel(), 'rider' => $x->rider?->full_name,
                'picked_up_at' => $x->picked_up_at?->toIso8601String(), 'received_at' => $x->received_at?->toIso8601String()])->values(),
        ]]);
    }

    /** Multipart: type (lost / damaged), reason, photo (required for damaged). */
    public function exception(int $parcel): JsonResponse
    {
        $p = Parcel::findOrFail($parcel);
        return $this->with($this->web(WebLogistics::class, 'markException', ['parcel' => $p]), fn () => $this->parcel($parcel));
    }

    // ── Pickup requests ──────────────────────────────────────────

    /** tab: pending, confirmed, received, rejected · page. */
    public function pickupRequests(): JsonResponse
    {
        $d = $this->pageData($this->web(WebSc::class, 'pickupRequests'));

        return response()->json(ApiJson::page($d['requests'], fn ($p) => LogisticsJson::parcel($p)) + [
            'tab' => $d['tab'],
            'tabs' => collect(['pending' => 'Pending', 'confirmed' => 'Confirmed', 'received' => 'Received', 'rejected' => 'Rejected'])
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => (int) ($d['counts'][$key] ?? 0)])->values(),
        ]);
    }

    public function approvePickup(int $parcel): JsonResponse
    {
        return $this->actionJson($this->web(WebSc::class, 'approvePickup', ['parcel' => Parcel::findOrFail($parcel)]));
    }

    /** reason. */
    public function rejectPickup(int $parcel): JsonResponse
    {
        return $this->actionJson($this->web(WebSc::class, 'rejectPickup', ['parcel' => Parcel::findOrFail($parcel)]));
    }

    // ── Incoming (receive / scan) ────────────────────────────────

    /** tab: all or a status · search · page. */
    public function incoming(): JsonResponse
    {
        $d = $this->pageData($this->web(WebSc::class, 'incomingParcels'));
        $labels = ['found' => 'Found', 'duplicate' => 'Already received', 'not_found' => 'Not found'];

        return response()->json(ApiJson::page($d['parcels'], fn ($p) => LogisticsJson::parcel($p)) + [
            'stats' => $d['miniStats'],
            // This center's own recent scans (the website's "Recent scans").
            'recent_scans' => $d['recentScans']->map(fn ($s) => [
                'code' => $s->old_value, 'result' => $s->status, 'result_label' => $labels[$s->status] ?? 'Invalid code',
                'parcel_status' => $s->new_value, 'parcel_id' => $s->subject_type === 'Parcel' ? $s->subject_id : null,
                'at' => $s->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /** hub_id (optional): the company's hub receiving it; the buyer's timeline then says "At Hub (HUB-QC)". condition, notes (optional). */
    public function receive(int $parcel): JsonResponse
    {
        $p = Parcel::findOrFail($parcel);
        $this->atHub($p);
        request()->merge(['confirm_received' => true]);   // tapping Receive in the app is the confirmation

        return $this->actionJson($this->web(WebSc::class, 'advanceParcel', ['parcel' => $p]));
    }

    /** tracking_number: scanned from the waybill or typed · hub_id (optional). */
    public function scan(): JsonResponse
    {
        $p = Parcel::where('tracking_number', strtoupper(trim((string) request('tracking_number'))))->first();
        if ($p) {
            $this->atHub($p);
        }

        // Scanning only finds the parcel (the website then opens its tracking page to confirm receipt).
        // A JSON request gets the website's "Scan QR" pop-up answer ({ok, url, message}); a miss is a 422 for the app.
        $json = $this->actionJson($this->web(WebSc::class, 'scanParcel'));
        $data = $json->getData(true);
        if (($data['ok'] ?? null) === false) {
            return response()->json(['message' => $data['message'], 'errors' => ['tracking_number' => [$data['message']]]], 422);
        }
        if ($json->getStatusCode() === 200 && preg_match('#/sc/tracking/(\d+)#', (string) ($data['url'] ?? $data['redirect'] ?? ''), $m)) {
            $json->setData($data + ['parcel_id' => (int) $m[1]]);
        }
        return $json;
    }

    /** Which of the company's hubs is receiving the parcel (only for this company's parcels). */
    private function atHub(Parcel $p): void
    {
        $hub = \App\Models\LogisticsHub::where('sorting_center_id', auth()->id())->find((int) request('hub_id'));
        if ($hub && (int) $p->current_sorting_center_id === (int) auth()->id() && in_array($p->status, ['pickup_approved', 'in_transit_to_hub'], true)) {
            $p->forceFill(['current_hub_id' => $hub->id])->saveQuietly();
        }
    }

    // ── Sorting ──────────────────────────────────────────────────

    public function sorting(): JsonResponse
    {
        $d = $this->pageData($this->web(WebSc::class, 'parcelSorting'));

        return response()->json([
            'areas' => $d['areas']->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'sorted' => (int) $a->sorted_count])->values(),
            'pending' => $d['pending']->map(fn ($p) => LogisticsJson::parcel($p) + [
                'suggested_area_id' => $d['suggested'][$p->id] ?? null, 'covered' => (bool) ($d['covered'][$p->id] ?? false)])->values(),
            'sorted_today' => (int) $d['sortedToday'],
        ]);
    }

    /** area_id. */
    public function sort(int $parcel): JsonResponse
    {
        return $this->with($this->web(WebSc::class, 'sortParcel', ['parcel' => Parcel::findOrFail($parcel)]), fn () => $this->sorting());
    }

    // ── Delivery assignment and monitoring ───────────────────────

    /** area_id · page. */
    public function assignment(): JsonResponse
    {
        $d = $this->pageData($this->web(WebSc::class, 'deliveryAssignment'));

        return response()->json(ApiJson::page($d['parcels'], fn ($p) => LogisticsJson::parcel($p)) + [
            'riders' => $d['riders']->map(fn ($r) => LogisticsJson::rider($r))->values(),
            'areas'  => $d['areas']->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values(),
        ]);
    }

    /** rider_id (a rider of the parcel's barangay). */
    public function assign(int $parcel): JsonResponse
    {
        return $this->actionJson($this->web(WebSc::class, 'assignParcel', ['parcel' => Parcel::findOrFail($parcel)]));
    }

    /** status · page. */
    public function monitoring(): JsonResponse
    {
        $d = $this->pageData($this->web(WebSc::class, 'deliveryMonitoring'));

        return response()->json(ApiJson::page($d['deliveries'], fn ($x) => LogisticsJson::delivery($x, (int) ($d['attempts'][$x->parcel_id] ?? 1))) + [
            'stats' => $d['monitorStats'],
            'statuses' => ParcelDelivery::STATUS_LABELS,
        ]);
    }

    /** status (an allowed next status), remarks (the reason, 10–300 characters). */
    public function overrideStatus(int $delivery): JsonResponse
    {
        return $this->actionJson($this->web(WebSc::class, 'updateDeliveryStatus', ['delivery' => ParcelDelivery::findOrFail($delivery)]));
    }

    public function reschedule(int $delivery): JsonResponse
    {
        return $this->actionJson($this->web(WebSc::class, 'rescheduleDelivery', ['delivery' => ParcelDelivery::findOrFail($delivery)]));
    }

    public function returnToSender(int $delivery): JsonResponse
    {
        return $this->actionJson($this->web(WebSc::class, 'returnParcel', ['delivery' => ParcelDelivery::findOrFail($delivery)]));
    }

    // ── Return to Sender ─────────────────────────────────────────

    public function rts(): JsonResponse
    {
        $d = $this->pageData($this->web(WebLogistics::class, 'rtsQueue'));

        return response()->json([
            'parcels'  => $d['parcels']->map(fn ($p) => LogisticsJson::parcel($p) + ['rts_rider' => $p->rtsRider?->full_name,
                'rts_rider_id' => $p->rts_rider_id, 'attempts' => $p->deliveries->map(fn ($x) => ['attempt' => (int) $x->attempt,
                    'status' => $x->status, 'reason' => $x->failure_reason])->values()])->values(),
            'awaiting' => $d['awaiting']->map(fn ($p) => LogisticsJson::parcel($p))->values(),
            'riders'   => collect($d['riders'])->map(fn ($r) => LogisticsJson::rider($r))->values(),
        ]);
    }

    /** rider_id. */
    public function assignRts(int $parcel): JsonResponse
    {
        return $this->with($this->web(WebLogistics::class, 'assignRts', ['parcel' => Parcel::findOrFail($parcel)]), fn () => $this->rts());
    }

    // ── Transfers between hubs ───────────────────────────────────

    /** search (tracking number). */
    public function transfers(): JsonResponse
    {
        $d = $this->pageData($this->web(WebSc::class, 'transfers'));
        $t = fn (ParcelTransfer $x) => TrackingController::transfer($x) + ['tracking' => $x->parcel?->tracking_number,
            'waybill' => $x->parcel?->waybill_number, 'parcel_id' => $x->parcel_id,
            'direction' => (int) $x->from_sorting_center_id === (int) auth()->id() ? 'out' : 'in'];

        return response()->json([
            // Parcels that can be sent on, each with the hub the website suggests.
            'parcels'  => $d['myParcels']->map(fn ($p) => LogisticsJson::parcel($p) + [
                'next' => ($n = $d['suggested'][$p->id] ?? null) ? ['type' => $n['type'], 'label' => $n['label'], 'center_id' => $n['center']?->id] : null,
            ])->values(),
            'centers'  => $d['otherCenters']->map(fn ($u) => ['id' => $u->id, 'name' => \App\Support\SortingCenters::displayName($u),
                'municipality' => $u->assigned_municipality, 'can_receive' => $u->hasAssignedMunicipality()])->values(),
            'transporters' => $d['transporters']->map(fn ($r) => ['id' => $r->id, 'name' => $r->full_name])->values(),
            'outgoing' => $d['outgoing']->map($t)->values(),
            'incoming' => $d['incoming']->map($t)->values(),
            'history'  => $d['history']->map($t)->values(),
        ]);
    }

    /** parcel_id, to_sorting_center_id, reason. */
    public function initiateTransfer(): JsonResponse
    {
        return $this->with($this->web(WebSc::class, 'initiateTransfer'), fn () => $this->transfers());
    }

    /** The destination receives it: tapping Accept in the app confirms the parcel is physically there. */
    public function acceptTransfer(ParcelTransfer $transfer): JsonResponse
    {
        request()->merge(['confirm_received' => true]);

        return $this->with($this->web(WebSc::class, 'acceptTransfer', ['transfer' => $transfer]), fn () => $this->transfers());
    }

    /** The parcel leaves this hub: In Transit to the destination, which then receives it. */
    public function dispatchTransfer(ParcelTransfer $transfer): JsonResponse
    {
        return $this->with($this->web(WebSc::class, 'dispatchTransfer', ['transfer' => $transfer]), fn () => $this->transfers());
    }

    public function rejectTransfer(ParcelTransfer $transfer): JsonResponse
    {
        return $this->with($this->web(WebSc::class, 'rejectTransfer', ['transfer' => $transfer]), fn () => $this->transfers());
    }

    public function cancelTransfer(ParcelTransfer $transfer): JsonResponse
    {
        return $this->with($this->web(WebSc::class, 'cancelTransfer', ['transfer' => $transfer]), fn () => $this->transfers());
    }
}
