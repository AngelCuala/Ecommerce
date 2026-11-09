<?php

use App\Models\Courier;
use App\Models\Rider;
use App\Services\TruckRiders;
use Illuminate\Database\Migrations\Migration;

/**
 * Riders and couriers are one account: a rider whose vehicle is a 4-wheeler (car, van, truck) does hub-to-hub trips.
 * Every logistics company's courier (truck record with a home hub) gets a rider, and every 4-wheel rider gets
 * a truck record. No columns change; the courier rows stay as the riders' truck records.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sync = app(TruckRiders::class);

        Courier::whereNotNull('home_hub_id')->whereNotNull('sorting_center_id')->orderBy('id')->each(fn (Courier $c) => $sync->adopt($c));

        Rider::whereIn('vehicle_type', Rider::HUB_TO_HUB_VEHICLES)->orderBy('id')->each(fn (Rider $r) => $sync->sync($r));
    }

    public function down(): void
    {
        // Nothing to undo: the riders made here are ordinary riders.
    }
};
