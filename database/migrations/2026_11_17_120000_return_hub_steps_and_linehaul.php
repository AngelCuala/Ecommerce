<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A returned item at a sorting center goes through the same steps as a parcel (App\Services\ReturnShipments):
 *   1 Sort (the next stop: the seller from this hub, or the hub of the seller's town)  2 Check (condition, photo)
 *   3 Scan for the record (waybill)  4 Send (a rider to the seller, or a hub-to-hub trip to the next hub).
 * The steps are kept per hub (they start again at the next hub) and every one is a line in return_scan_events.
 * A return can ride a linehaul trip: linehaul_trip_parcels.return_shipment_id (the delivered parcel's own status
 * doesn't change).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_shipments', function (Blueprint $t) {
            $t->foreignId('current_hub_id')->nullable()->after('sorting_center_id')->constrained('logistics_hubs')->nullOnDelete();
            $t->string('next_stop', 10)->nullable()->after('current_hub_id');          // seller | hub
            $t->foreignId('next_hub_id')->nullable()->after('next_stop')->constrained('logistics_hubs')->nullOnDelete();
            $t->dateTime('hub_sorted_at')->nullable();
            $t->dateTime('hub_checked_at')->nullable();
            $t->string('hub_condition', 20)->nullable();                                // good | damaged | opened | wrong_item
            $t->string('hub_check_note', 300)->nullable();
            $t->string('hub_check_photo')->nullable();
            $t->dateTime('hub_scanned_at')->nullable();
            $t->foreignId('linehaul_trip_id')->nullable()->constrained('linehaul_trips')->nullOnDelete();
        });

        Schema::create('return_scan_events', function (Blueprint $t) {
            $t->id();
            $t->foreignId('return_shipment_id')->constrained('return_shipments')->cascadeOnDelete();
            $t->foreignId('sorting_center_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('hub_id')->nullable()->constrained('logistics_hubs')->nullOnDelete();
            $t->string('type', 20);         // arrived, sorted, checked, scanned, loaded, departed, received, to_seller, missing
            $t->string('note', 300)->nullable();
            $t->string('condition', 20)->nullable();
            $t->string('photo_path')->nullable();
            $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('created_at');
            $t->index(['return_shipment_id', 'created_at']);
        });

        Schema::table('linehaul_trip_parcels', function (Blueprint $t) {
            $t->foreignId('return_shipment_id')->nullable()->after('parcel_id')->constrained('return_shipments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('linehaul_trip_parcels', fn (Blueprint $t) => $t->dropConstrainedForeignId('return_shipment_id'));
        Schema::dropIfExists('return_scan_events');
        Schema::table('return_shipments', function (Blueprint $t) {
            foreach (['current_hub_id', 'next_hub_id', 'linehaul_trip_id'] as $fk) {
                $t->dropConstrainedForeignId($fk);
            }
            $t->dropColumn(['next_stop', 'hub_sorted_at', 'hub_checked_at', 'hub_condition', 'hub_check_note', 'hub_check_photo', 'hub_scanned_at']);
        });
    }
};
