<?php

/*
 * Where ALVY operates. Buyers' delivery addresses, sellers' business / pickup addresses,
 * riders and sorting centers must all be inside these provinces (enforced on the server).
 *
 * SERVICE_AREA_PROVINCES: comma-separated 9-digit PSA Correspondence Codes of provinces.
 * Default: empty = the whole Philippines (no restriction). e.g. 043400000 = Laguna only.
 * Admin accounts are never restricted. Existing records outside the area are kept; they
 * must be moved into the area the next time they are edited (see `php artisan service-area:report`).
 */
return [
    'provinces' => array_values(array_filter(array_map('trim', explode(',', (string) env('SERVICE_AREA_PROVINCES', ''))))),
];
