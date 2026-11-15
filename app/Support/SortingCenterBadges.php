<?php

namespace App\Support;

use App\Models\Parcel;
use App\Models\RiderRemittance;
use App\Services\RiderDispatch;

/**
 * The numbers beside the sorting center's sidebar items: how much work is waiting on each page, so staff
 * see at a glance where to go next. Each number counts exactly what that page lists as waiting.
 *
 *   pickups      Pickup Requests: sellers' requests this company hasn't confirmed yet
 *   sorting      Parcel Sorting: parcels received at the hub, not sorted yet
 *   dispatch     Deliveries: pickups and deliveries still waiting for a rider (Rider Dispatch)
 *   remittances  COD Remittances: cash riders handed in, waiting to be confirmed
 */
class SortingCenterBadges
{
    public static function for(int $scId): array
    {
        return [
            'pickups'     => Parcel::where('current_sorting_center_id', $scId)->where('status', 'pending_pickup')->count(),
            'sorting'     => Parcel::where('current_sorting_center_id', $scId)->whereIn('status', ['picked_up', 'sorting'])
                ->whereNull('transfer_status')->count(),
            'dispatch'    => app(RiderDispatch::class)->waiting($scId)->count(),
            'remittances' => RiderRemittance::where('sorting_center_id', $scId)->where('status', 'pending')->count(),
        ];
    }
}
