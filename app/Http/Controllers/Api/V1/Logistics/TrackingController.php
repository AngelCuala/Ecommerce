<?php

namespace App\Http\Controllers\Api\V1\Logistics;

use App\Http\Controllers\Api\V1\Concerns\CallsWeb;
use App\Http\Controllers\Controller;
use App\Http\Controllers\SortingCenter\CommissionController as WebCommission;
use App\Http\Controllers\SortingCenter\ShipmentController as WebShipment;
use App\Http\Controllers\SortingCenterController as WebSc;
use App\Models\Parcel;
use App\Models\ParcelTransfer;
use App\Models\Rider;
use App\Models\ShipmentException;
use App\Services\SortingCenterCommission;
use App\Support\ApiJson;
use App\Support\LogisticsJson;
use App\Support\SortingCenters;
use Illuminate\Http\JsonResponse;

/**
 * The website's Shipment Tracking board (sc.tracking), Commissions (sc.commissions) and printable
 * waybills (sc.waybill / sc.waybills) for the app.
 */
class TrackingController extends Controller
{
    use CallsWeb;

    /** tab (incoming, awaiting_sorting, …), q (tracking, waybill, town, seller or #order), status · page. */
    public function index(): JsonResponse
    {
        $d = $this->pageData($this->web(WebShipment::class, 'index'));
        $me = (int) auth()->id();
        // The board's one button per row, as on the website.
        $action = function (Parcel $p) use ($me) {
            $t = $p->activeTransfer;
            return match (true) {
                $p->status === 'in_transit_to_hub'
                    || ($p->status === 'in_transfer' && $t?->isInTransit() && (int) $t->to_sorting_center_id === $me) => 'receive',
                $p->status === 'picked_up' && ! $p->transfer_status                                     => 'start_sorting',
                $t?->isPending() && (int) $t->from_sorting_center_id === $me                            => 'dispatch',
                in_array($p->status, ['sorting', 'picked_up'], true)                                    => 'ready',
                $p->status === 'sorted' && ! $p->transfer_status                                        => 'final_delivery',
                default                                                                                  => 'details',
            };
        };

        return response()->json(ApiJson::page($d['parcels'], fn (Parcel $p) => LogisticsJson::parcel($p) + [
            'row_action' => $action($p),
            'next'       => $d['next'][$p->id]['label'] ?? null,
            'transfer'   => $p->activeTransfer ? self::transfer($p->activeTransfer) : null,
            'rider'      => $p->parcelDelivery?->rider?->full_name ?? $p->pickup?->rider?->full_name,
            'last_event' => ($e = $p->events->first()) ? ['label' => $e->label(), 'at' => $e->occurred_at?->toIso8601String()] : null,
        ]) + [
            'tab'  => $d['tab'],
            'tabs' => collect(WebShipment::TABS)->except('all')->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => (int) ($d['counts'][$key] ?? 0)])->values(),
            'statuses' => Parcel::STATUS_LABELS,
        ]);
    }

    public function show(int $parcel): JsonResponse
    {
        $d = $this->pageData($this->web(WebShipment::class, 'show', ['parcel' => Parcel::findOrFail($parcel)]));
        $p = $d['parcel'];
        $me = (int) auth()->id();
        $t = $d['transfer'];
        $next = $d['next'];
        $atHere = $p->isAtHub() && (int) $p->current_sorting_center_id === $me && ! $t;
        $local = ($next['type'] ?? null) === 'local';

        return response()->json(['parcel' => LogisticsJson::parcel($p) + [
            'next'            => $next ? ['type' => $next['type'], 'label' => $next['label'], 'center_id' => $next['center']?->id] : null,
            'next_label'      => $next['label'] ?? ($t ? 'Transfer to ' . SortingCenters::nameOf($t->toSortingCenter) : null),
            'held_by'         => $p->currentSortingCenter ? SortingCenters::nameOf($p->currentSortingCenter) : null,
            'received_by'     => $p->receivedBy?->name,
            'sort_code'       => $p->sortCode(),
            'transfer'        => $t ? self::transfer($t) + [
                'can_dispatch' => $t->isPending() && (int) $t->from_sorting_center_id === $me,
                'can_cancel'   => $t->isPending() && (int) $t->from_sorting_center_id === $me,
                // A parcel in transit to another hub that turned up here.
                'stray'        => $t->isInTransit() && (int) $t->to_sorting_center_id !== $me,
            ] : null,
            'events' => $d['events']->map(fn ($e) => [
                'label' => $e->label(), 'note' => $e->note, 'at' => $e->occurred_at?->toIso8601String(), 'by' => $e->actor?->name,
                'from_status' => $e->from_status, 'to_status' => $e->to_status, 'corrects' => $e->corrects?->label(),
            ])->values(),
            'exceptions' => $p->exceptions->sortByDesc('id')->map(fn (ShipmentException $x) => [
                'id' => $x->id, 'type' => $x->type, 'type_label' => $x->typeLabel(), 'details' => $x->details, 'status' => $x->status,
                'resolution' => $x->resolution, 'reporter' => $x->reporter?->name, 'created_at' => $x->created_at?->toIso8601String(),
            ])->values(),
            'history' => [
                'transfers'  => $p->transfers->sortBy('id')->map(fn ($x) => self::transfer($x))->values(),
                'pickups'    => $p->pickups->sortBy('id')->map(fn ($x) => ['rider' => $x->rider?->full_name, 'status_label' => $x->statusLabel(),
                    'at' => $x->created_at?->toIso8601String()])->values(),
                'deliveries' => $p->deliveries->sortBy('id')->map(fn ($x) => ['rider' => $x->rider?->full_name, 'status_label' => $x->statusLabel(),
                    'attempt' => (int) $x->attempt, 'at' => $x->created_at?->toIso8601String()])->values(),
            ],
            'items' => $p->order ? $p->order->items->map(fn ($i) => ['title' => $i->product->title ?? 'Item', 'variation' => $i->variation_name,
                'quantity' => (int) $i->quantity])->values() : [],
            'actions' => [
                'receive'       => (bool) $d['canReceive'],
                'start_sorting' => $atHere && ! $local && $p->status === 'picked_up',
                'sort'          => $atHere && $local && in_array($p->status, ['picked_up', 'sorting'], true),
                'transfer'      => $atHere && in_array($p->status, ['picked_up', 'sorting', 'sorted'], true),
                'waybill'       => (int) $p->current_sorting_center_id === $me,
            ],
        ],
            'areas' => $d['areas']->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values(),
            'suggested_area_id' => $d['suggestedArea']?->id,
            'hubs' => $d['hubs']->map(fn ($h) => ['id' => $h->id, 'name' => SortingCenters::displayName($h), 'municipality' => $h->assigned_municipality])->values(),
            'transporters' => $d['transporters']->map(fn ($r) => ['id' => $r->id, 'name' => $r->full_name])->values(),
            'exception_types' => ShipmentException::TYPES,
        ]);
    }

    /** condition (good / damaged), notes (required if damaged). Tapping Confirm in the app is the confirmation. */
    public function receive(int $parcel): JsonResponse
    {
        request()->merge(['confirm_received' => true]);
        return $this->then($parcel, $this->web(WebShipment::class, 'receive', ['parcel' => Parcel::findOrFail($parcel)]));
    }

    public function startSorting(int $parcel): JsonResponse
    {
        return $this->then($parcel, $this->web(WebShipment::class, 'startSorting', ['parcel' => Parcel::findOrFail($parcel)]));
    }

    /** type (damaged_on_arrival, missing, other), details. */
    public function exception(int $parcel): JsonResponse
    {
        return $this->then($parcel, $this->web(WebShipment::class, 'reportException', ['parcel' => Parcel::findOrFail($parcel)]));
    }

    /** Allow / stop one of this center's riders carrying hub transfers. */
    public function toggleTransport(Rider $rider): JsonResponse
    {
        $json = $this->actionJson($this->web(WebShipment::class, 'toggleTransport', ['rider' => $rider]));
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + ['can_transport_transfers' => (bool) $rider->fresh()->can_transport_transfers]);
        }
        return $json;
    }

    private function then(int $parcel, mixed $response): JsonResponse
    {
        $json = $this->actionJson($response);
        if ($json->getStatusCode() === 200) {
            $json->setData($json->getData(true) + $this->show($parcel)->getData(true));
        }
        return $json;
    }

    public static function transfer(ParcelTransfer $t): array
    {
        return [
            'id' => $t->id, 'reference' => $t->reference, 'status' => $t->status, 'status_label' => $t->statusLabel(),
            'from' => $t->fromSortingCenter ? SortingCenters::nameOf($t->fromSortingCenter) : null,
            'to' => $t->toSortingCenter ? SortingCenters::nameOf($t->toSortingCenter) : null,
            'transporter' => $t->transporter?->full_name, 'reason' => $t->reason, 'exception_reason' => $t->exception_reason,
            'delayed' => $t->isDelayed(), 'created_at' => $t->created_at?->toIso8601String(),
            'dispatched_at' => $t->dispatched_at?->toIso8601String(), 'expected_by' => $t->expected_by?->toIso8601String(),
            'received_at' => $t->received_at?->toIso8601String(),
        ];
    }

    // ── Commissions ──────────────────────────────────────────────

    /** period (today, week, month, custom) · from, to · status · page. */
    public function commissions(): JsonResponse
    {
        request()->query->remove('export');
        $d = $this->pageData($this->web(WebCommission::class, 'index'));

        return response()->json(ApiJson::page($d['items'], fn ($l) => [
            'parcel_id' => $l['parcel']->id, 'tracking' => $l['parcel']->tracking_number, 'waybill' => $l['parcel']->waybill_number,
            'order_number' => $l['parcel']->order?->number(), 'created_at' => $l['parcel']->created_at?->toIso8601String(),
            'role' => $l['role'], 'fee' => (float) $l['fee'], 'hubs' => (int) $l['hubs'], 'share' => (float) $l['share'],
            'commission' => (float) $l['commission'], 'rider_pay' => (float) $l['rider_pay'], 'net' => (float) $l['net'],
            'status' => $l['status'], 'status_label' => SortingCenterCommission::STATUSES[$l['status']] ?? $l['status'],
        ]) + [
            'period' => $d['range']->period, 'range_label' => $d['range']->label(),
            'from' => $d['range']->from->toDateString(), 'to' => $d['range']->to->toDateString(),
            'status' => $d['status'], 'statuses' => SortingCenterCommission::STATUSES,
            'summary' => $d['summary'], 'rate' => (float) $d['rate'],
        ]);
    }

    /** format (csv / xlsx) plus the same filters. */
    public function exportCommissions(): mixed
    {
        request()->validate(['format' => 'required|in:csv,xlsx'], ['format.in' => 'Choose CSV or Excel (XLSX).']);
        request()->query->set('export', request()->input('format'));
        $response = $this->web(WebCommission::class, 'index');

        return $response instanceof \Symfony\Component\HttpFoundation\Response && ! $response instanceof \Illuminate\Http\RedirectResponse
            ? $response
            : $this->actionJson($response);
    }

    // ── Waybills ─────────────────────────────────────────────────

    /** ids[] (up to 100 of this center's parcels): what each printed 4×6 label shows. */
    public function waybills(): JsonResponse
    {
        $d = $this->pageData($this->web(WebSc::class, 'waybills'));
        $hub = $d['hub'];

        return response()->json(['waybills' => $d['parcels']->map(function (Parcel $p) use ($hub) {
            $order = $p->order;
            $isCod = $order && $order->payment_method === 'COD' && $order->payment_status !== 'Paid';
            $items = $order ? $order->items()->with('product')->get() : collect();
            $sc = $p->currentSortingCenter?->assigned_municipality ?: $hub->assigned_municipality;

            return [
                'id' => $p->id, 'waybill' => $p->waybill_number, 'tracking' => $p->tracking_number, 'sort_code' => $p->sortCode(),
                'hub' => $sc ? $sc . ' SC' : 'ALVY SC', 'area' => $p->area->name ?? 'Not sorted yet',
                'receiver' => $p->receiver_name, 'receiver_phone' => self::mask($p->receiver_phone), 'dropoff_address' => $p->dropoff_address,
                'sender' => $p->seller?->storeName() ?? 'ALVY seller',
                'sender_phone' => self::mask($p->seller?->sellerApplication->phone ?? $p->seller?->contact_no), 'pickup_address' => $p->pickup_address,
                'cod' => $isCod ? (float) $order->total_price : null, 'order_number' => $order?->number(),
                'weight_kg' => $p->weight_kg !== null ? (float) $p->weight_kg : null, 'item_count' => (int) $items->sum('quantity'),
                'ship_date' => ($p->received_at ?? $p->created_at)?->toDateString(),
                'items' => $items->map(fn ($i) => ['title' => $i->product->title ?? 'Item', 'variation' => $i->variation_name, 'quantity' => (int) $i->quantity])->values(),
                'notes' => $p->notes,
            ];
        })->values()]);
    }

    /** As the printed label: the first and last 4 characters of a phone number. */
    private static function mask(?string $phone): string
    {
        return $phone ? (strlen($phone) > 7 ? substr($phone, 0, 4) . str_repeat('*', max(0, strlen($phone) - 8)) . substr($phone, -4) : $phone) : '—';
    }
}
