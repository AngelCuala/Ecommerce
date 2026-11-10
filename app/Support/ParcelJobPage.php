<?php

namespace App\Support;

use App\Models\Parcel;

/**
 * Where a parcel's current job is done in the logistics panel: the Shipment Board's status links there
 * (a pickup request → Pickup Requests, sorted → Deliveries, failed → Failed Deliveries, …).
 */
final class ParcelJobPage
{
    /** [page name, url] for this parcel's status, from this sorting center's point of view. */
    public static function for(Parcel $parcel, ?int $me = null): array
    {
        $me ??= (int) auth()->id();
        $t = $parcel->relationLoaded('activeTransfer') ? $parcel->activeTransfer : null;

        if ($parcel->relationLoaded('exceptions') ? $parcel->exceptions->contains(fn ($x) => $x->isOpen() && ! in_array($x->type, Parcel::HOLD_EXCEPTIONS_EXCEPT, true)) : $parcel->openHold()) {
            return ['Flagged Parcels', route('sc.flagged')];
        }

        if (\App\Models\ReturnShipment::where('parcel_id', $parcel->id)->whereIn('status', \App\Models\ReturnShipment::OPEN)->exists()) {
            return ['Return Items', route('sc.returns')];
        }

        return match (true) {
            $parcel->status === 'pending_pickup'                              => ['Pickup Requests', route('sc.pickup-requests', ['tab' => 'pending'])],
            in_array($parcel->status, ['pickup_approved', 'in_transit_to_hub'], true) => ['Pickup Requests', route('sc.pickup-requests', ['tab' => 'confirmed'])],
            $parcel->status === 'pickup_rejected'                             => ['Pickup Requests', route('sc.pickup-requests', ['tab' => 'rejected'])],
            in_array($parcel->status, ['picked_up', 'sorting'], true)          => ['Parcel Sorting', route('sc.parcel-sorting')],
            $parcel->status === 'sorted' && $parcel->next_center_id           => ['Parcel Sorting', route('sc.parcel-sorting') . '#hub-to-hub'],
            $parcel->status === 'sorted' && $parcel->transfer_status           => ['Linehaul Trips', route('sc.trips')],
            $parcel->status === 'sorted'                                      => ['Deliveries', route('sc.delivery-assignment', array_filter(['area_id' => $parcel->area_id]))],
            in_array($parcel->status, ['assigned', 'in_transit'], true)        => ['Deliveries', route('sc.delivery-monitoring')],
            $parcel->status === 'failed'                                      => ['Failed Deliveries', route('sc.failed-deliveries')],
            in_array($parcel->status, ['in_transfer', 'linehaul'], true)       => ['Linehaul Trips', route('sc.trips')],
            in_array($parcel->status, ['return_to_sender', 'returned'], true)  => ['Return to Seller', route('sc.rts')],
            default                                                           => ['Details', route('sc.tracking.show', $parcel)],
        };
    }
}
