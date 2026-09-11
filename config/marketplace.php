<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Marketplace Commission Rate
    |--------------------------------------------------------------------------
    | The percentage the platform takes from each sale.
    | e.g. 10 = 10% commission. Seller receives (100 - commission)%.
    |
    */
    'commission_rate' => env('MARKETPLACE_COMMISSION', 10),
];
