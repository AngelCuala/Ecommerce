<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\ParcelTransfer;
use App\Services\ShipmentTracker;

/**
 * What a buyer or seller sees about a shipment: built only from recorded events and records.
 * Steps that haven't happened are listed as "expected" only when they are on this shipment's
 * actual route (e.g. the next hub only while a transfer to it is under way). No rider personal
 * details are included. There is no GPS: the location is the last place staff confirmed it.
 */
class ShipmentProgress
{
    public static function for(Order $order): array
    {
        $parcel = Parcel::where('order_id', $order->id)->latest('id')->with(['currentSortingCenter', 'transfers.fromSortingCenter', 'transfers.toSortingCenter'])->first();
        $events = app(ShipmentTracker::class)->timeline($order);
        $delivery = $parcel ? ParcelDelivery::where('parcel_id', $parcel->id)->latest('id')->first() : null;
        $attempts = $parcel ? ParcelDelivery::where('parcel_id', $parcel->id)->where('status', 'failed')->orderBy('id')->get() : collect();
        $transfer = $parcel?->transfers->first(fn ($t) => in_array($t->status, ParcelTransfer::ACTIVE, true));
        $name = fn ($u) => $u ? SortingCenters::displayName($u) : null;

        $status = $parcel ? $parcel->statusLabel() : match (true) {
            $order->status === Order::CANCELLED => 'Cancelled',
            (bool) $order->packed_at            => 'Preparing Package',
            (bool) $order->accepted_at          => 'Seller Processing',
            default                             => 'Order Placed',
        };
        $location = match (true) {
            ! $parcel                                     => 'With the seller',
            in_array($parcel->status, ['pending_pickup', 'pickup_approved', 'pickup_rejected'], true) => 'With the seller',
            $parcel->status === 'in_transit_to_hub'       => 'With the pickup rider, on the way to ' . ($name($parcel->currentSortingCenter) ?? 'the sorting center'),
            $parcel->status === 'in_transfer'             => 'In transit to ' . ($name($transfer?->toSortingCenter) ?? 'the next sorting center'),
            $parcel->status === 'in_transit'              => 'With the delivery rider',
            $parcel->status === 'delivered'               => 'Delivered',
            in_array($parcel->status, ['returned'], true) => 'Back with the seller',
            default                                       => $name($parcel->currentSortingCenter) ?? '—',
        };

        // Hubs the parcel was confirmed at, in order.
        $hubs = $events->filter(fn ($e) => in_array($e->event_type, ['arrived_at_sorting_center', 'arrived_at_destination_center'], true))
            ->map(fn ($e) => ['name' => $name($e->location), 'at' => $e->occurred_at])->values();

        return [
            'parcel'         => $parcel,
            'tracking'       => $parcel?->tracking_number ?? $order->tracking_number,
            'status'         => $status,
            'location'       => $location,
            'hubs'           => $hubs,
            'transfers'      => $parcel ? $parcel->transfers->whereNotIn('status', ['cancelled', 'rejected'])->sortBy('id')->values() : collect(),
            'rider_assigned' => $parcel && in_array($parcel->status, ['assigned', 'in_transit'], true),
            'events'         => $events,
            'expected'       => self::expected($order, $parcel, $transfer),
            'delivered_at'   => $parcel?->status === 'delivered' ? ($delivery?->delivered_at ?? $events->firstWhere('event_type', 'delivered')?->occurred_at) : null,
            'pod'            => $parcel?->status === 'delivered' && $delivery?->pod_path ? $delivery->pod_path : null,
            'attempts'       => $attempts,
            // A finished shipment is no longer "being reviewed", even if an old exception was never closed.
            'under_review'   => $parcel && ! in_array($parcel->status, ['delivered', 'returned'], true)
                && $parcel->exceptions()->where('status', 'open')->exists(),
        ];
    }

    /** Steps still to come on this shipment's own route (never shown as done). */
    private static function expected(Order $order, ?Parcel $parcel, ?ParcelTransfer $transfer): array
    {
        if ($order->status === Order::CANCELLED || in_array($parcel?->status, ['delivered', 'returned', 'cancelled', 'lost', 'damaged', 'return_to_sender'], true)) {
            return [];
        }
        $steps = [];
        $at = $parcel?->status;
        if (! $parcel) {
            if (! $order->accepted_at) $steps[] = 'Seller Processing';
            if (! $order->packed_at) $steps[] = 'Preparing Package';
            $steps[] = 'Ready for Pickup';
        }
        if (! $parcel || in_array($at, ['pending_pickup', 'pickup_approved', 'pickup_rejected'], true)) {
            $steps[] = 'Picked Up from Seller';
        }
        if (! $parcel || in_array($at, ['pending_pickup', 'pickup_approved', 'pickup_rejected', 'in_transit_to_hub'], true)) {
            $steps[] = 'Arrived at Sorting Center';
        }
        if ($at === 'in_transfer' && $transfer) {
            $steps[] = 'Arrived at ' . SortingCenters::displayName($transfer->toSortingCenter);
        }
        if (! in_array($at, ['in_transit'], true)) {
            $steps[] = 'Out for Delivery';
        }
        $steps[] = 'Delivered';

        return $steps;
    }
}
