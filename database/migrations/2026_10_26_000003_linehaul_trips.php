<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hub-to-hub (linehaul) trips driven by a company's couriers: the courier goes On Duty after a pre-trip
 * checklist, accepts a trip, scans every parcel while loading, seals and departs; the destination hub
 * checks the seal and scans each parcel in. Hubs get a location (500 m geofence for depart and arrival).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_hubs', function (Blueprint $t) {
            $t->decimal('latitude', 10, 7)->nullable()->after('address');
            $t->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::table('couriers', function (Blueprint $t) {
            $t->boolean('on_duty')->default(false)->after('status');
            $t->timestamp('duty_since')->nullable()->after('on_duty');
            $t->timestamp('checklist_at')->nullable()->after('duty_since');
            $t->decimal('last_lat', 10, 7)->nullable()->after('checklist_at');
            $t->decimal('last_lng', 10, 7)->nullable()->after('last_lat');
            $t->timestamp('last_seen_at')->nullable()->after('last_lng');
            $t->boolean('needs_reverification')->default(false)->after('last_seen_at');
        });

        Schema::table('parcels', function (Blueprint $t) {
            $t->foreignId('current_hub_id')->nullable()->after('current_sorting_center_id')->constrained('logistics_hubs')->nullOnDelete();
        });

        Schema::create('courier_checklists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('courier_id')->constrained()->cascadeOnDelete();
            $t->json('items');                   // {"Tires":"ok","Brakes":"not_ok",…}
            $t->boolean('passed');
            $t->string('note', 300)->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->timestamps();
        });

        Schema::create('linehaul_trips', function (Blueprint $t) {
            $t->id();
            $t->string('code', 20)->unique();     // TRIP-000001
            $t->foreignId('sorting_center_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('origin_hub_id')->constrained('logistics_hubs');
            $t->foreignId('destination_hub_id')->constrained('logistics_hubs');
            $t->foreignId('courier_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 30)->default('scheduled')->index();
            $t->timestamp('scheduled_departure_at')->nullable();
            $t->timestamp('assigned_at')->nullable();
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('loading_at')->nullable();
            $t->timestamp('departed_at')->nullable();
            $t->timestamp('arrived_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->string('seal_number', 20)->nullable();
            $t->string('received_seal', 20)->nullable();
            $t->boolean('seal_discrepancy')->default(false);
            $t->string('seal_note', 300)->nullable();
            $t->timestamp('eta_at')->nullable();
            $t->boolean('delayed')->default(false);
            $t->string('delay_reason', 40)->nullable();
            $t->boolean('needs_rescan')->default(false);   // reassigned in transit: the new courier scans every parcel again
            $t->string('courier_note', 300)->nullable();
            $t->boolean('needs_courier_note')->default(false);
            $t->decimal('pay', 10, 2)->default(0);
            $t->foreignId('paid_courier_id')->nullable()->constrained('couriers')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('linehaul_trip_parcels', function (Blueprint $t) {
            $t->id();
            $t->foreignId('linehaul_trip_id')->constrained()->cascadeOnDelete();
            $t->foreignId('parcel_id')->constrained()->cascadeOnDelete();
            $t->string('status', 20)->default('pending');   // pending, loaded, received, missing, damaged, removed
            $t->timestamp('loaded_at')->nullable();
            $t->timestamp('received_at')->nullable();
            $t->string('damage_photo_path')->nullable();
            $t->timestamps();
            $t->unique(['linehaul_trip_id', 'parcel_id']);
        });

        // History and audit log of every trip (A35): assignments, declines, reassignments, seal and parcel discrepancies…
        Schema::create('linehaul_trip_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('linehaul_trip_id')->constrained()->cascadeOnDelete();
            $t->foreignId('courier_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();   // who did it
            $t->string('type', 40);
            $t->string('old_value')->nullable();
            $t->string('new_value')->nullable();
            $t->string('reason', 300)->nullable();
            $t->string('photo_path')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('linehaul_trip_locations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('linehaul_trip_id')->constrained()->cascadeOnDelete();
            $t->decimal('lat', 10, 7);
            $t->decimal('lng', 10, 7);
            $t->timestamp('recorded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linehaul_trip_locations');
        Schema::dropIfExists('linehaul_trip_events');
        Schema::dropIfExists('linehaul_trip_parcels');
        Schema::dropIfExists('linehaul_trips');
        Schema::dropIfExists('courier_checklists');
        Schema::table('parcels', fn (Blueprint $t) => $t->dropConstrainedForeignId('current_hub_id'));
        Schema::table('couriers', fn (Blueprint $t) => $t->dropColumn(['on_duty', 'duty_since', 'checklist_at', 'last_lat', 'last_lng', 'last_seen_at', 'needs_reverification']));
        Schema::table('logistics_hubs', fn (Blueprint $t) => $t->dropColumn(['latitude', 'longitude']));
    }
};
