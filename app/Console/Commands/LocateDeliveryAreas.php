<?php

namespace App\Console\Commands;

use App\Models\DeliveryArea;
use App\Services\NearestRiders;
use Illuminate\Console\Command;

/** Places barangays (delivery areas) on the map, once each, so the nearest rider can be found. */
class LocateDeliveryAreas extends Command
{
    protected $signature = 'areas:locate {--limit=40 : Barangays to look up in one run} {--again : Also retry ones not found before}';

    protected $description = 'Find the map location of delivery areas (barangays) from OpenStreetMap';

    public function handle(NearestRiders $nearest): int
    {
        $areas = DeliveryArea::query()
            ->when(! $this->option('again'), fn ($q) => $q->whereNull('located_at'), fn ($q) => $q->whereNull('latitude'))
            ->orderBy('id')->limit((int) $this->option('limit'))->get();

        $found = 0;
        foreach ($areas as $i => $area) {
            if ($i > 0) {
                usleep(1_100_000);   // Nominatim: at most one request a second
            }
            $found += $nearest->locate($area) ? 1 : 0;
        }
        $this->info("Located {$found} of {$areas->count()} barangay(s).");

        return self::SUCCESS;
    }
}
