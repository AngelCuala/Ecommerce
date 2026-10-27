<?php

/*
 * Where ALVY operates. Buyers' delivery addresses, sellers' business / pickup addresses,
 * riders and sorting centers must all be inside these provinces (enforced on the server).
 *
 * SERVICE_AREA_PROVINCES: comma-separated 9-digit PSA Correspondence Codes of provinces.
 * Default 043400000 = Laguna. Empty = no restriction (nationwide).
 * Admin accounts are never restricted. Existing records outside the area are kept; they
 * must be moved into the area the next time they are edited (see `php artisan service-area:report`).
 */
return [
    'provinces' => array_values(array_filter(array_map('trim', explode(',', (string) env('SERVICE_AREA_PROVINCES', '043400000'))))),
];
