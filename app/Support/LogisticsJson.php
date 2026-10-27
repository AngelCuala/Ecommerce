<?php

namespace App\Support;

use App\Models\Courier;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\Rider;
use App\Models\RiderRemittance;
use App\Services\ParcelService;

/**
 * The logistics company's (sorting center) pages as the app shows them, worked out like the web's sc.* Blade views.
 */
class LogisticsJson
{
    public const BUCKET_LABELS = ['pending' => 'Pending pickup', 'in_transit' => 'In transit', 'delivered' => 'Delivered',
        'failed' => 'Failed / exceptions', 'returned' => 'Returned / cancelled'];

    public static function parcel(?Parcel $p): ?array
    {
        if (! $p) {
            return null;
        }
        $order = $p->relationLoaded('order') ? $p->order : null;
        $cod = $order && strtoupper((string) $order->payment_method) === 'COD' && $order->payment_status !== 'Paid';

        return [
            'id'              => $p->id,
            'tracking'        => $p->tracking_number,
            'status'          => $p->status,
            'status_label'    => $p->statusLabel(),
            'order_id'        => $p->order_id,
            'order_number'    => $order?->number(),
            'seller'          => $p->relationLoaded('seller') ? ($p->seller?->sellerApplication?->shop_name ?? $p->seller?->name) : null,
            'pickup_address'  => $p->pickup_address,
            'receiver'        => $p->receiver_name,
            'receiver_phone'  => $p->receiver_phone,
            'dropoff_address' => $p->dropoff_address,
            'destination'     => trim(($p->destination_municipality ?? '') . ($p->destination_province ? ', ' . $p->destination_province : ''), ', '),
            'area'            => $p->relationLoaded('area') && $p->area ? ['id' => $p->area->id, 'name' => $p->area->name] : null,
            'weight_kg'       => $p->weight_kg !== null ? (float) $p->weight_kg : null,
            'cod'             => $cod ? (float) $order->total_price : null,
            'payment'         => $order ? $order->payment_method . ' · ' . $order->payment_status : null,
            'transfer_status' => $p->transfer_status,
            'failure_reason'  => $p->failure_reason,
            'exception_photo_url' => ApiJson::file($p->exception_photo_path),
            'created_at'      => $p->created_at?->toIso8601String(),
            'received_at'     => $p->received_at?->toIso8601String(),
            'sorted_at'       => $p->sorted_at?->toIso8601String(),
            'verified_at'     => $p->verified_at?->toIso8601String(),
            'returned_at'     => $p->returned_at?->toIso8601String(),
            'can_mark_exception' => ! in_array($p->status, Parcel::CLOSED_STATUSES, true),
        ];
    }

    public static function rider(Rider $r, bool $wallet = false): array
    {
        $row = [
            'id'                => $r->id,
            'full_name'         => $r->full_name,
            'phone'             => $r->phone,
            'email'             => $r->relationLoaded('user') ? $r->user?->email : null,
            'vehicle_type'      => $r->vehicle_type,
            'plate_number'      => $r->plate_number,
            'area'              => $r->relationLoaded('area') ? $r->area?->name : null,
            'area_id'           => $r->area_id,
            'application_status' => $r->application_status,
            'status_label'      => $r->statusLabel(),
            'is_active'         => (bool) $r->is_active,
            'suspended'         => $r->isSuspended(),
            'online'            => $r->isOnlineNow(),
            'rejection_reason'  => $r->rejection_reason,
            'needs_reverification' => (bool) $r->needs_reverification,
            'license_number'    => $r->license_number,
            'license_expiry'    => $r->license_expiry?->toDateString(),
            'has_license_file'  => (bool) $r->license_path,
            'has_or_cr_file'    => (bool) $r->or_cr_path,
            'pending_activation' => $r->isPendingActivation(),
            'created_at'        => $r->created_at?->toIso8601String(),
        ];
        if ($wallet) {
            $w = app(\App\Services\RiderWallet::class);
            $row += ['active_jobs' => $r->activeJobsCount(), 'cod_balance' => $w->codBalance($r), 'cod_pending' => $w->pendingRemittance($r)];
        }
        return $row;
    }

    public static function delivery(ParcelDelivery $d, ?int $attempts = null): array
    {
        $p = $d->parcel;
        $parcelStatus = $p?->status;
        return [
            'id'             => $d->id,
            'status'         => $d->status,
            'status_label'   => $d->statusLabel(),
            'attempt'        => (int) $d->attempt,
            'attempts'       => $attempts ?? (int) $d->attempt,
            'max_attempts'   => ParcelDelivery::MAX_ATTEMPTS,
            'rider'          => $d->rider?->full_name,
            'area'           => $d->area?->name,
            'cod_amount'     => (float) $d->cod_amount > 0 ? (float) $d->cod_amount : null,
            'cod_collected'  => (float) $d->cod_collected,
            'out_at'         => $d->picked_up_at?->toIso8601String(),
            'delivered_at'   => $d->delivered_at?->toIso8601String(),
            'failure_reason' => $d->failure_reason,
            'remarks'        => $d->remarks,
            'pod_url'        => ApiJson::file($d->pod_path),
            'created_at'     => $d->created_at?->toIso8601String(),
            'parcel'         => self::parcel($p),
            'actions' => [
                // A logistics override (the rider's app was offline): the allowed next statuses.
                'override'   => in_array($parcelStatus, ['assigned', 'in_transit'], true) ? (ParcelService::DELIVERY_FLOW[$d->status] ?? []) : [],
                'reschedule' => $parcelStatus === 'failed' && $p->deliveries()->count() < ParcelDelivery::MAX_ATTEMPTS,
                'return'     => $parcelStatus === 'failed',
            ],
        ];
    }

    public static function remittance(RiderRemittance $r): array
    {
        return [
            'id' => $r->id, 'rider' => $r->rider?->full_name, 'amount' => (float) $r->amount, 'expected' => (float) $r->expected_amount,
            'shortfall' => $r->shortfall(), 'status' => $r->status, 'created_at' => $r->created_at?->toIso8601String(),
            'confirmed_at' => $r->confirmed_at?->toIso8601String(),
        ];
    }

    public static function courier(Courier $c, bool $detail = false): array
    {
        $row = [
            'id' => $c->id, 'name' => $c->fullName(), 'email' => $c->user?->email, 'contact_no' => $c->contact_no,
            'vehicle_type' => $c->vehicle_type, 'plate_number' => $c->plate_number, 'status' => $c->status,
            'status_label' => $c->isPendingActivation() ? 'Pending Activation' : ucfirst($c->status),
            'submitted_at' => ($c->submitted_at ?? $c->created_at)?->toIso8601String(),
            // Made by the company (Add Courier): a linehaul driver with a home hub.
            'linehaul' => $c->isLinehaul(), 'home_hub' => $c->homeHub?->label(), 'load_capacity_kg' => $c->load_capacity_kg,
            'duty_label' => $c->isLinehaul() && $c->isApproved() ? $c->dutyLabel() : null, 'needs_reverification' => (bool) $c->needs_reverification,
            'checklist_at' => $c->checklist_at?->toIso8601String(), 'last_seen_at' => $c->last_seen_at?->toIso8601String(),
            'location' => $c->last_lat !== null ? ['lat' => (float) $c->last_lat, 'lng' => (float) $c->last_lng] : null,
        ];
        if ($detail) {
            $row += [
                'sex' => $c->sex, 'age' => $c->age, 'birthday' => $c->birthday?->toDateString(), 'address' => $c->fullAddress(),
                'business_name' => $c->business_name, 'rejection_reason' => $c->rejection_reason,
                'reviewed_at' => $c->reviewed_at?->toIso8601String(), 'reviewed_by' => $c->reviewedBy?->name,
                'has_license' => (bool) $c->id_license_path, 'has_or_cr' => (bool) $c->or_cr_path, 'has_nbi' => (bool) $c->nbi_path,
                'license_number' => $c->license_number, 'license_type' => $c->license_type, 'dl_codes' => $c->dl_codes,
                'license_expiry' => $c->license_expiry?->toDateString(),
            ];
        }
        return $row;
    }
}
