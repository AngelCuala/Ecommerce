<?php

namespace App\Support;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\ParcelPickup;
use App\Models\Rider;
use App\Models\RiderJobOffer;
use App\Services\RiderDispatch;

/**
 * The rider and courier pages as the app shows them, worked out like the web's Blade views
 * (courier.rider.jobs / pickup / delivery / cod / profile, and the direct courier's dashboard / delivery page).
 */
class RiderJson
{
    // ── Rider of a logistics company ──────────────────────────────

    public static function rider(Rider $rider): array
    {
        return [
            'id'             => $rider->id,
            'full_name'      => $rider->full_name,
            'company'        => $rider->companyName(),
            'status'         => $rider->statusLabel(),
            'suspended'      => $rider->isSuspended(),
            'online'         => $rider->isOnlineNow(),
            'active_jobs'    => $rider->activeJobsCount(),
            'max_active'     => RiderDispatch::MAX_ACTIVE,
            'vehicle_type'   => $rider->vehicle_type,
            'plate_number'   => $rider->plate_number,
            'license_number' => $rider->license_number,
            'license_expiry' => $rider->license_expiry?->toDateString(),
            // The Account page's warning: expired, or within 30 days.
            'license_state'  => ! $rider->license_expiry ? null : ($rider->license_expiry->isPast() ? 'expired'
                : ($rider->license_expiry->lte(now()->addDays(30)) ? 'soon' : null)),
            'rider_since'    => $rider->approved_at?->toIso8601String(),
            'area'           => $rider->area?->name,
            'needs_reverification' => (bool) $rider->needs_reverification,
            'has_license_file' => (bool) $rider->license_path,
            'has_or_cr_file'   => (bool) $rider->or_cr_path,
        ];
    }

    private static function cod(?Order $order): bool
    {
        return $order && strtoupper((string) $order->payment_method) === 'COD' && $order->payment_status !== 'Paid';
    }

    public static function offer(RiderJobOffer $o, Rider $rider): array
    {
        $p = $o->parcel;
        $order = $p?->order;
        $cod = self::cod($order);

        return [
            'id'           => $o->id,
            'kind'         => $o->kind,
            'tracking'     => $p?->tracking_number,
            'pickup_name'  => $p?->seller->name ?? 'Seller',
            'pickup_address' => $p?->pickup_address,
            'dropoff'      => $o->kind === RiderJobOffer::PICKUP
                ? $rider->companyName() . ' hub'
                : trim(($p?->area->name ?? '') . ' ' . $p?->destination_municipality),
            'cod'          => $cod ? (float) $order->total_price : null,
            'pay'          => $o->kind === RiderJobOffer::PICKUP ? RiderDispatch::PAY_PICKUP : RiderDispatch::PAY_DELIVERY,
            'seconds_left' => $o->secondsLeft(),
            'expires_at'   => $o->expires_at?->toIso8601String(),
        ];
    }

    public static function pickupRow(ParcelPickup $pu): array
    {
        $p = $pu->parcel;
        return [
            'id' => $pu->id, 'tracking' => $p?->tracking_number, 'waybill' => $p?->waybill_number, 'status' => $pu->status, 'status_label' => $pu->statusLabel(),
            'seller' => $p?->seller->name ?? '', 'pickup_address' => $p?->pickup_address,
            // The website's Acknowledge and "On my way" (the seller is told the rider is coming).
            'acknowledged' => (bool) $pu->acknowledged_at, 'on_the_way' => (bool) $pu->en_route_at,
        ];
    }

    public static function pickupDetail(ParcelPickup $pu, Rider $rider): array
    {
        $p = $pu->parcel;
        $app = $p?->seller?->sellerApplication;

        return array_merge(self::pickupRow($pu), [
            'seller'         => $app->shop_name ?? $p?->seller->name ?? '—',
            'contact'        => $app->phone ?? $p?->seller->contact_no ?? null,
            'items'          => $p?->order ? (int) $p->order->items->sum('quantity') : null,
            'bring_to'       => $rider->companyName() . ' hub',
            'picked_up_at'   => $pu->picked_up_at?->toIso8601String(),
            'can_confirm'    => $pu->status === 'assigned',
            'pay'            => RiderDispatch::PAY_PICKUP,
        ]);
    }

    public static function deliveryRow(ParcelDelivery $d): array
    {
        $p = $d->parcel;
        return [
            'id' => $d->id, 'tracking' => $p?->tracking_number, 'waybill' => $p?->waybill_number, 'status' => $d->status, 'status_label' => $d->statusLabel(),
            'receiver' => $p?->receiver_name, 'address' => $p?->dropoff_address,
            'attempt' => (int) $d->attempt, 'max_attempts' => ParcelDelivery::MAX_ATTEMPTS,
            'acknowledged' => (bool) $d->acknowledged_at, 'updated_at' => $d->updated_at?->toIso8601String(),
        ];
    }

    public static function deliveryDetail(ParcelDelivery $d): array
    {
        $p = $d->parcel;
        $cod = (float) $d->cod_amount > 0;

        return self::deliveryRow($d) + [
            'phone'            => $p?->receiver_phone,
            'order_number'     => $p?->order?->number(),
            'cod_amount'       => $cod ? (float) $d->cod_amount : null,
            'assigned_at'      => $d->created_at?->toIso8601String(),
            'out_at'           => $d->picked_up_at?->toIso8601String(),
            'delivered_at'     => $d->delivered_at?->toIso8601String(),
            'failure_reason'   => $d->failure_reason,
            'otp_locked_until' => $d->otpLocked() ? $d->otp_locked_until->toIso8601String() : null,
            'pod_url'          => $d->status === 'delivered' ? ApiJson::file($d->pod_path) : null,
            'items'            => $p?->order ? $p->order->items->map(fn ($i) => ['title' => $i->product->title ?? 'Item',
                'variation' => $i->variation_name, 'quantity' => (int) $i->quantity])->values() : [],
            'actions' => [
                'start'   => $d->status === 'assigned',
                'deliver' => $d->status === 'out_for_delivery',
                'fail'    => in_array($d->status, ParcelDelivery::OPEN, true),
            ],
            'failure_reasons' => ParcelDelivery::FAILURE_REASONS,
            'pay'             => RiderDispatch::PAY_DELIVERY,
        ];
    }

    public static function returnRow(Parcel $p): array
    {
        return ['id' => $p->id, 'tracking' => $p->tracking_number, 'seller' => $p->seller->name ?? '', 'pickup_address' => $p->pickup_address];
    }

    /** A pay / history row from RiderWallet::history(). */
    public static function walletRow(array $r): array
    {
        return ['at' => $r['at']?->toIso8601String(), 'type' => $r['type'], 'tracking' => $r['tracking'], 'result' => $r['result'],
            'pay' => (float) $r['pay'], 'cod' => (float) $r['cod']];
    }

    // ── Direct courier ─────────────────────────────────────────────

    public static function courierDelivery(Delivery $d): array
    {
        $order = $d->order;
        $seller = $d->seller();

        return [
            'id'            => $d->id,
            'order_id'      => $d->order_id,
            'order_number'  => '#' . str_pad((string) $d->order_id, 6, '0', STR_PAD_LEFT),
            'status'        => $d->status,
            'status_label'  => $d->courierStatusLabel(),
            'fee'           => (float) $d->delivery_fee,
            'pickup_name'   => $d->pickupName(),
            'pickup_address' => trim(($seller->address ?? '') . ' ' . ($seller->city ?? '')) ?: null,
            'pickup_phone'  => $seller->phone ?? null,
            'pickup_scheduled_at' => $d->pickup_scheduled_at?->toIso8601String(),
            'buyer'         => $order?->full_name,
            'buyer_phone'   => $order?->phone,
            'address'       => $order?->fullAddress(),
            'items_count'   => $d->itemCount(),
            'notes'         => $d->notes,
            'accepted_at'   => $d->accepted_at?->toIso8601String(),
            'picked_up_at'  => $d->picked_up_at?->toIso8601String(),
            'delivered_at'  => $d->delivered_at?->toIso8601String(),
            'created_at'    => $d->created_at?->toIso8601String(),
            'items'         => $order ? $order->items->map(fn ($i) => ['title' => $i->product->title ?? '—', 'quantity' => (int) $i->quantity,
                'price' => (float) $i->price, 'image_url' => ApiJson::file($i->product?->image)])->values() : [],
            'actions' => [
                'accept'     => $d->status === 'available' && ! $d->courier_id,
                'pickup'     => $d->status === 'accepted',
                'in_transit' => $d->status === 'picked_up',
                'complete'   => $d->status === 'in_transit',
            ],
        ];
    }
}
