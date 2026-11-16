<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Marketplace Commission Rate
    |--------------------------------------------------------------------------
    | The percentage the platform takes from each sale.
    | e.g. 10 = 10% commission. Seller receives (100 - commission)%.
    | Each order item stores the rate in force when it was bought, so changing
    | this only affects new orders.
    |
    */
    'commission_rate' => env('MARKETPLACE_COMMISSION', 10),

    /*
    |--------------------------------------------------------------------------
    | Sorting Center Commission Rate
    |--------------------------------------------------------------------------
    | The percentage ALVY keeps from a sorting center's share of each parcel's
    | shipping fee (App\Services\SortingCenterCommission). 0: ALVY's commission is
    | on the items only; the whole shipping fee belongs to the hubs and their riders.
    | Earnings are worked out live, so a new rate applies to every parcel shown.
    |
    */
    'sorting_center_commission_rate' => env('SORTING_CENTER_COMMISSION', 0),

    /*
    |--------------------------------------------------------------------------
    | Shipping fee 30/30/40
    |--------------------------------------------------------------------------
    | From this time each parcel's shipping fee goes 30% to the pickup rider, 30% to the delivery rider, and the hubs the
    | parcel passed split the other 40% (App\Services\RiderDispatch, SortingCenterCommission). Jobs and parcels
    | from before keep the old rule (₱30 a pickup, ₱50 a delivery).
    |
    */
    'rider_share_since' => env('RIDER_SHARE_SINCE', '2026-11-16 00:00:00'),

    /*
    |--------------------------------------------------------------------------
    | Returns and refunds
    |--------------------------------------------------------------------------
    | On: buyers can ask for a return within Order::RETURN_DAYS of delivery, and the
    | Return & Refund Policy shows everywhere. RETURNS_ENABLED=false turns it off
    | again (requests already made can still be finished).
    |
    */
    'returns_enabled' => (bool) env('RETURNS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Low-stock alert level
    |--------------------------------------------------------------------------
    | A seller is alerted when a product's stock drops to this many units or fewer.
    | Each product can set its own level (products.low_stock_threshold); empty = this.
    |
    */
    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 10),
];
