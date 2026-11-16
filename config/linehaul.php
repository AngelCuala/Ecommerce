<?php

/*
|--------------------------------------------------------------------------
| Linehaul (hub-to-hub trips)
|--------------------------------------------------------------------------
| handover_seconds  How often the driver's Handover QR and 6-digit code change (30–60 s). A code also works during
|                   the next period, so one shown just before it changes still scans; after that it has expired.
*/

return [
    'handover_seconds' => max(30, min(60, (int) env('LINEHAUL_HANDOVER_SECONDS', 45))),
];
