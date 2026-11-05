<?php

namespace App\Http\Controllers\Api\V1\Rider;

use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Courier\RiderController as WebRider;
use App\Models\ParcelDelivery;
use App\Models\ParcelPickup;
use App\Models\Rider;
use App\Models\RiderRemittance;
use App\Services\RiderDispatch;
use App\Support\RiderJson;
use Illuminate\Http\JsonResponse;

/**
 * A logistics company's rider (the web's Hub Jobs, courier/rider/*): Online/Offline, job offers, pickups
 * (seller → hub), deliveries (hub → buyer) with a proof photo + COD, failed attempts, returns, profile, pay and COD.
 */
class RiderController extends Controller
{
    use CallsWeb;

    // ── Jobs ─────────────────────────────────────────────────────

    public function jobs(): JsonResponse
    {
        $d = $this->pageData($this->web(WebRider::class, 'index'));
        $rider = $d['rider'];

        return response()->json([
            'rider'      => RiderJson::rider($rider),
            'offers'     => $d['offers']->map(fn ($o) => RiderJson::offer($o, $rider))->values(),
            'pickups'    => $d['pickups']->map(fn ($p) => RiderJson::pickupRow($p))->values(),
            'deliveries' => $d['deliveries']->map(fn ($x) => RiderJson::deliveryRow($x))->values(),
            'returns'    => $d['returns']->map(fn ($p) => RiderJson::returnRow($p))->values(),
            // Hub-to-hub parcels this rider carries (riders the hub allowed to transport), and the last finished deliveries.
            'transfers'  => collect($d['transfers'] ?? [])->map(fn ($t) => [
                'id' => $t->id, 'reference' => $t->reference, 'tracking' => $t->parcel?->tracking_number, 'waybill' => $t->parcel?->waybill_number,
                'from' => $t->fromSortingCenter ? \App\Support\SortingCenters::displayName($t->fromSortingCenter) : null,
                'to' => $t->toSortingCenter ? \App\Support\SortingCenters::displayName($t->toSortingCenter) : null,
                'status' => $t->status, 'expected_by' => $t->expected_by?->toIso8601String(),
            ])->values(),
            'recent'     => collect($d['recent'] ?? [])->map(fn ($x) => RiderJson::deliveryRow($x))->values(),
            'decline_reasons' => WebRider::DECLINE_REASONS,
            'pay'        => ['pickup' => RiderDispatch::PAY_PICKUP, 'delivery' => RiderDispatch::PAY_DELIVERY],
        ]);
    }

    /** "I saw it": a job the hub assigned (kind: pickup / delivery). */
    public function acknowledge(string $kind, int $job): JsonResponse
    {
        return $this->withJobs($this->web(WebRider::class, 'acknowledge', ['kind' => $kind, 'job' => $job]));
    }

    /** reason (from decline_reasons): a job the hub assigned that the rider can't do; the hub reassigns it. */
    public function declineJob(string $kind, int $job): JsonResponse
    {
        return $this->withJobs($this->web(WebRider::class, 'declineJob', ['kind' => $kind, 'job' => $job]));
    }

    /** "On my way" to the seller (the parcel is not collected yet). */
    public function arriving(int $pickup): JsonResponse
    {
        return $this->withJobs($this->web(WebRider::class, 'arriving', ['pickup' => $pickup]));
    }

    /** online: true / false (omit to switch). */
    public function online(): JsonResponse { return $this->withJobs($this->web(WebRider::class, 'toggleOnline')); }
    public function accept(int $offer): JsonResponse  { return $this->withJobs($this->web(WebRider::class, 'accept', ['offer' => $offer])); }
    public function decline(int $offer): JsonResponse { return $this->withJobs($this->web(WebRider::class, 'decline', ['offer' => $offer])); }
    public function returned(int $parcel): JsonResponse { return $this->withJobs($this->web(WebRider::class, 'returnedToSeller', ['parcel' => $parcel])); }

    private function withJobs(mixed $response): JsonResponse
    {
        $json = $this->actionJson($response);
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + $this->jobs()->getData(true));
        }
        return $json;
    }

    // ── Pickup (seller → hub) ─────────────────────────────────────

    public function pickup(int $pickup): JsonResponse
    {
        $d = $this->pageData($this->web(WebRider::class, 'pickup', ['pickup' => $pickup]));
        return response()->json(['pickup' => RiderJson::pickupDetail($d['pickup'], $d['rider'])]);
    }

    /** tracking_number: scanned from the waybill barcode or typed. */
    public function confirmPickup(int $pickup): JsonResponse
    {
        $json = $this->actionJson($this->web(WebRider::class, 'confirmPickup', ['pickup' => $pickup]));
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + $this->pickup($pickup)->getData(true));
        }
        return $json;
    }

    // ── Delivery (hub → buyer) ────────────────────────────────────

    public function delivery(int $delivery): JsonResponse
    {
        $d = $this->pageData($this->web(WebRider::class, 'delivery', ['delivery' => $delivery]));
        return response()->json(['delivery' => RiderJson::deliveryDetail($d['delivery'])]);
    }

    public function start(int $delivery): JsonResponse { return $this->withDelivery($delivery, $this->web(WebRider::class, 'startDelivery', ['delivery' => $delivery])); }

    /** Multipart: pod_photo, cod_collected (COD only). An otp sent by older app builds is ignored. */
    public function deliver(int $delivery): JsonResponse { return $this->withDelivery($delivery, $this->web(WebRider::class, 'deliver', ['delivery' => $delivery])); }

    /** reason: one of failure_reasons. */
    public function fail(int $delivery): JsonResponse { return $this->withDelivery($delivery, $this->web(WebRider::class, 'fail', ['delivery' => $delivery])); }

    private function withDelivery(int $id, mixed $response): JsonResponse
    {
        $json = $this->actionJson($response);
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + $this->delivery($id)->getData(true));
        }
        return $json;
    }

    // ── Profile and documents ─────────────────────────────────────

    public function profile(): JsonResponse
    {
        $rider = \App\Models\Rider::where('user_id', auth()->id())->firstOrFail();
        return response()->json(['rider' => RiderJson::rider($rider), 'vehicles' => WebRider::VEHICLES]);
    }

    /** Multipart: vehicle_type, plate_number, license_number, license_expiry, license_file, or_cr_file. */
    public function updateProfile(): JsonResponse
    {
        $json = $this->actionJson($this->web(WebRider::class, 'updateProfile'));
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + $this->profile()->getData(true));
        }
        return $json;
    }

    /** The rider's own license or OR/CR file (license / or-cr). */
    public function document(string $document): mixed
    {
        $rider = Rider::where('user_id', auth()->id())->firstOrFail();
        return $this->web(WebRider::class, 'document', ['rider' => $rider->id, 'document' => $document]);
    }

    // ── History, pay and COD ──────────────────────────────────────

    /** from, to (Y-m-d). */
    public function history(): JsonResponse
    {
        $d = $this->pageData($this->web(WebRider::class, 'history'));
        return response()->json(['rows' => $d['rows']->map(fn ($r) => RiderJson::walletRow($r))->values()]);
    }

    /** period: today, week (default), last_week, month — the website's Rider Pay. */
    public function earnings(): JsonResponse
    {
        $d = $this->pageData($this->web(WebRider::class, 'earnings'));

        return response()->json([
            'period'     => $d['period'],
            'periods'    => WebRider::PAY_PERIODS,
            'from'       => $d['from']->toDateString(),
            'to'         => $d['to']->toDateString(),
            'cards'      => $d['cards'],        // today / week / month: pay and jobs
            'breakdown'  => $d['breakdown'],    // pickups, deliveries (count, pay), failed, cod, total
            'days'       => $d['days']->values(),
            'jobs'       => $d['jobs']->map(fn ($r) => RiderJson::walletRow($r))->values(),
            'pending'    => (float) $d['pending'],
            'today'      => $d['today']->map(fn ($r) => RiderJson::walletRow($r))->values(),
            'today_jobs' => $d['today']->count(),
            'today_pay'  => (float) $d['todayPay'],
            'week_pay'   => round((float) $d['weekPay'], 2),
            'pay'        => ['pickup' => RiderDispatch::PAY_PICKUP, 'delivery' => RiderDispatch::PAY_DELIVERY],
        ]);
    }

    public function cod(): JsonResponse
    {
        $d = $this->pageData($this->web(WebRider::class, 'cod'));

        return response()->json([
            'company'  => $d['rider']->companyName(),
            'balance'  => round((float) $d['balance'], 2),
            'pending'  => round((float) $d['pending'], 2),
            // The next remittance, worked out: COD on hand − unpaid pay kept = cash to hand over.
            'next'     => $d['next'],
            'orders'   => $d['orders']->map(fn (ParcelDelivery $x) => ['delivered_at' => $x->delivered_at?->toIso8601String(),
                'tracking' => $x->parcel?->tracking_number, 'order_number' => $x->parcel?->order?->number(), 'cod' => (float) $x->cod_collected])->values(),
            'remittances' => $d['remittances']->map(fn (RiderRemittance $r) => ['id' => $r->id, 'created_at' => $r->created_at?->toIso8601String(),
                'amount' => (float) $r->amount, 'pay_kept' => (float) $r->pay_kept, 'status' => $r->status, 'shortfall' => $r->shortfall()])->values(),
            'request_token' => $d['token'],
        ]);
    }

    /** amount, request_token (one remittance per token, like the website). */
    public function remit(): JsonResponse
    {
        $json = $this->actionJson($this->web(WebRider::class, 'remit'));
        if ($json->getStatusCode() === 200) {
            // The website opens the receipt next; the app gets its id.
            $sent = RiderRemittance::where('request_token', (string) request('request_token'))->value('id');
            $json->setData($json->getData(true) + ['remittance_id' => $sent] + $this->cod()->getData(true));
        }
        return $json;
    }

    /** The website's remittance receipt: what was sent, to whom, the deliveries it covers and the status. */
    public function remittanceReceipt(int $remittance): JsonResponse
    {
        $d = $this->pageData($this->web(WebRider::class, 'remittanceReceipt', ['remittance' => RiderRemittance::findOrFail($remittance)]));
        $r = $d['remittance'];

        return response()->json(['receipt' => [
            'id' => $r->id, 'reference' => 'REM-' . str_pad((string) $r->id, 6, '0', STR_PAD_LEFT),
            'rider' => $d['rider']->full_name, 'company' => $d['rider']->companyName(),
            'amount' => (float) $r->amount, 'pay_kept' => (float) $r->pay_kept, 'expected' => (float) $r->expected_amount, 'shortfall' => $r->shortfall(),
            'status' => $r->status, 'sent_at' => $r->created_at?->toIso8601String(),
            'confirmed_at' => $r->confirmed_at?->toIso8601String(), 'confirmed_by' => $r->confirmer?->name,
            'carried_over' => (float) $d['carriedOver'],
            'deliveries' => $d['deliveries']->map(fn ($x) => ['tracking' => $x->parcel?->tracking_number, 'order_number' => $x->parcel?->order?->number(),
                'delivered_at' => $x->delivered_at?->toIso8601String(), 'cod' => (float) $x->cod_collected])->values(),
        ]]);
    }
}
