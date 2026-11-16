<?php

use App\Models\Rider;
use App\Services\TruckRiders;
use Illuminate\Database\Migrations\Migration;

/**
 * Only trucks are Couriers (hub-to-hub). Car and van riders pick up and deliver again (they take the bulky parcels),
 * so a truck record made for them earlier is turned off (trips not yet departed go back to Unassigned).
 */
return new class extends Migration
{
    public function up(): void
    {
        $sync = app(TruckRiders::class);
        Rider::whereIn('vehicle_type', Rider::BULKY_VEHICLES)->orderBy('id')->each(fn (Rider $r) => $sync->sync($r));
    }

    public function down(): void
    {
        // Nothing to undo: putting cars and vans back on trips would need the old vehicle list.
    }
};
