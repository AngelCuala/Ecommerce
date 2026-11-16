<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where each barangay is on the map (found once from OpenStreetMap by `php artisan areas:locate`), so a parcel
 * whose barangay has no rider can go to the nearest rider of the company instead (App\Services\NearestRiders).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_areas', function (Blueprint $t) {
            $t->decimal('latitude', 10, 7)->nullable()->after('barangay_code');
            $t->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $t->timestamp('located_at')->nullable()->after('longitude');   // tried (found or not), so it isn't asked again every run
        });
    }

    public function down(): void
    {
        Schema::table('delivery_areas', function (Blueprint $t) {
            $t->dropColumn(['latitude', 'longitude', 'located_at']);
        });
    }
};
