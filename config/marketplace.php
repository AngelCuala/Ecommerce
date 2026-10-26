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
    | shipping fee (App\Services\SortingCenterCommission). e.g. 10 = 10%.
    | Earnings are worked out live, so a new rate applies to every parcel shown.
    |
    */
    'sorting_center_commission_rate' => env('SORTING_CENTER_COMMISSION', 10),

    /*
    |--------------------------------------------------------------------------
    | Low-stock alert level
    |--------------------------------------------------------------------------
    | A seller is alerted when a product's stock drops to this many units or fewer.
    | Each product can set its own level (products.low_stock_threshold); empty = this.
    |
    */
    'low_stock_threshold' => (int) env('LOW_STOCK_THRESHOLD', 5),
];
