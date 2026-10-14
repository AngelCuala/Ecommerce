<?php

/*
|--------------------------------------------------------------------------
| Shipping rates
|--------------------------------------------------------------------------
| Shipping is charged per store. Chargeable weight = the total weight of that store's
| items in the order, rounded UP to the next whole kg (minimum 1 kg).
|   fee = first_kg + (chargeable kg − 1) × each_additional_kg
|
| Zones come from the first two digits of the PSGC code (the region):
|   NCR = 13 · Luzon = 01 02 03 04 05 14 17 · Visayas = 06 07 08 18 · Mindanao = 09 10 11 12 15 16 19
| Rates are the same in both directions (NCR → Visayas = Visayas → NCR).
*/

return [

    'rates' => [
        'NCR-NCR'           => [50, 15],
        'NCR-Luzon'         => [70, 25],
        'Luzon-Luzon'       => [60, 20],
        'NCR-Visayas'       => [120, 40],
        'Luzon-Visayas'     => [120, 40],
        'Visayas-Visayas'   => [60, 20],
        'NCR-Mindanao'      => [150, 50],
        'Luzon-Mindanao'    => [150, 50],
        'Visayas-Mindanao'  => [120, 40],
        'Mindanao-Mindanao' => [60, 20],
    ],

    // Products without a weight are charged as this many kg.
    'default_weight_kg' => 0.5,

    // Origin for items sold by ALVY itself, or a seller whose pickup address has no PSGC code.
    'default_origin_zone' => env('SHIPPING_DEFAULT_ORIGIN_ZONE', 'NCR'),

    // Provinces no logistics partner covers yet (PSGC province code, 9-digit form "RRPP00000").
    // Orders to these addresses can't be placed.
    'unserviceable_provinces' => [
        '020900000' => 'Batanes',
    ],
];
