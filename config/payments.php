<?php

/*
|--------------------------------------------------------------------------
| Online payments (e-wallet and card)
|--------------------------------------------------------------------------
| PAYMENT_GATEWAY decides whether buyers can pay online at checkout:
|   (empty)   online payment is off — Cash on Delivery only (the default)
|   paymongo  GCash, Maya and cards through PayMongo (needs PAYMONGO_SECRET_KEY)
|   sandbox   a TEST gateway built into ALVY: no real money moves. Use it only to try
|             the flow (and for automated tests) — never on a live shop.
|
| Unpaid online orders are cancelled after `unpaid_minutes` (stock and vouchers come back).
*/

return [
    'gateway' => env('PAYMENT_GATEWAY'),

    'unpaid_minutes' => (int) env('PAYMENT_UNPAID_MINUTES', 60),

    'methods' => [
        'gcash' => 'GCash',
        'maya'  => 'Maya',
        'card'  => 'Credit / Debit card',
    ],

    /*
    | GCash sent by the buyer to the shop's GCash account, outside ALVY: the buyer uploads the receipt
    | screenshot at checkout and the seller checks it before accepting the order. Shown at checkout
    | only when GCASH_NUMBER is set.
    */
    'gcash_receipt' => [
        'number' => env('GCASH_NUMBER'),
        'name'   => env('GCASH_ACCOUNT_NAME'),
    ],

    'paymongo' => [
        'secret_key' => env('PAYMONGO_SECRET_KEY'),
        'base_url'   => env('PAYMONGO_BASE_URL', 'https://api.paymongo.com/v1'),
    ],
];
