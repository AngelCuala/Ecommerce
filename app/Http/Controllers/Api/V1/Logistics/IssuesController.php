<?php

namespace App\Http\Controllers\Api\V1\Logistics;

use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SortingCenter\IssuesController as WebIssues;
use App\Http\Controllers\SortingCenter\ReturnsController as WebReturns;
use App\Http\Controllers\SortingCenterController as WebSc;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\ReturnShipment;
use App\Models\ShipmentException;
use App\Support\ApiJson;
use App\Support\LogisticsJson;
use Illuminate\Http\JsonResponse;

/** The website's Failed Deliveries, Flagged Parcels, Return Items and hub-to-hub sorting, for the app. */
class IssuesController extends Controller
{
    use CallsWeb;

    /** reason (one of failure_reasons) · page. */
    public function failed(): JsonResponse
    {
        $d = $this->pageData($this->web(WebIssues::class, 'failed'));
        $outcome = fn (?Parcel $p) => match (true) {
            ! $p                                                          => null,
            $p->status === 'failed'                                       => 'waiting',
            $p->status === 'cancelled'                                    => 'cancelled',
            in_array($p->status, ['return_to_sender', 'returned'], true)  => 'returning',
            $p->status === 'delivered'                                    => 'delivered',
            default                                                       => 'rescheduled',
        };

        return response()->json(ApiJson::page($d['failed'], fn (ParcelDelivery $x) => [
            'id' => $x->id, 'reason' => $x->failure_reason, 'remarks' => $x->remarks, 'attempt' => (int) $x->attempt,
            'max_attempts' => ParcelDelivery::MAX_ATTEMPTS, 'rider' => $x->rider?->full_name, 'at' => $x->updated_at?->toIso8601String(),
            'outcome' => $outcome($x->parcel), 'parcel' => $x->parcel ? LogisticsJson::parcel($x->parcel) : null,
            'can_decide' => $x->parcel?->status === 'failed' && (int) $x->parcel->current_sorting_center_id === (int) auth()->id(),
        ]) + [
            'counts' => $d['counts'], 'waiting' => (int) $d['waiting'], 'reasons' => ParcelDelivery::FAILURE_REASONS, 'reason' => $d['reason'],
        ]);
    }

    // ── Return Items ───────────────────────────────────────────

    public static function returnRow(ReturnShipment $r): array
    {
        return [
            'id' => $r->id, 'status' => $r->status, 'status_label' => $r->statusLabel(), 'order_number' => $r->order?->number(),
            'waybill' => $r->code(), 'reason' => $r->returnRequest?->reason, 'refund_status' => $r->returnRequest?->statusLabel(),
            'buyer' => ['name' => $r->order?->full_name, 'address' => $r->buyerAddress()],
            'seller' => ['name' => $r->parcel?->seller?->storeName() ?? $r->parcel?->seller?->name, 'address' => $r->sellerAddress()],
            'pickup_rider' => $r->pickupRider?->full_name, 'pickup_rider_id' => $r->pickup_rider_id, 'accepted' => (bool) $r->acknowledged_at,
            'seller_rider' => $r->sellerRider?->full_name, 'seller_rider_id' => $r->seller_rider_id,
            'bulky' => (bool) $r->parcel?->isBulky(), 'cancel_reason' => $r->cancel_reason,
            'proof_url' => $r->pickup_proof_path ? \App\Support\ProtectedFile::url($r->pickup_proof_path) : null,
            'updated_at' => $r->updated_at?->toIso8601String(), 'returned_at' => $r->returned_at?->toIso8601String(),
            'action' => match ($r->status) {
                'requested', 'pickup_assigned' => 'assign_pickup',
                'picked_up'                    => 'receive',
                'at_hub', 'to_seller'          => 'assign_seller',
                default                        => null,
            },
        ];
    }

    /** tab: pickup | hub | seller | done · page. */
    public function returns(): JsonResponse
    {
        $d = $this->pageData($this->web(WebReturns::class, 'index'));

        return response()->json(ApiJson::page($d['items'], fn (ReturnShipment $r) => self::returnRow($r)) + [
            'tab' => $d['tab'],
            'tabs' => collect(WebReturns::TABS)->map(fn ($t, $key) => ['key' => $key, 'label' => $t[0], 'count' => (int) ($d['counts'][$key] ?? 0)])->values(),
            'riders' => $d['riderChoices']->map(fn ($c) => LogisticsJson::rider($c->rider) + ['available' => $c->available, 'why' => $c->why,
                'bulky_ok' => $c->rider->canCarryBulky(), 'active_jobs' => (int) ($c->rider->active_jobs ?? 0)])->values(),
        ]);
    }

    /** rider_id. */
    public function returnAssign(int $return): JsonResponse
    {
        return $this->actionJson($this->web(WebReturns::class, 'assignPickup', ['return' => ReturnShipment::findOrFail($return)]));
    }

    public function returnReceive(int $return): JsonResponse
    {
        return $this->actionJson($this->web(WebReturns::class, 'receive', ['return' => ReturnShipment::findOrFail($return)]));
    }

    /** rider_id. */
    public function returnToSeller(int $return): JsonResponse
    {
        return $this->actionJson($this->web(WebReturns::class, 'assignSeller', ['return' => ReturnShipment::findOrFail($return)]));
    }

    // ── Hub-to-hub sorting ─────────────────────────────────────

    /** center_id: the sorting center serving the buyer's address. */
    public function sortTransfer(int $parcel): JsonResponse
    {
        return $this->actionJson($this->web(WebSc::class, 'sortForTransfer', ['parcel' => Parcel::findOrFail($parcel)]));
    }
}
