<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAYMENTS_SPEC §11: pickups are in bulk. A pickup stop is one visit to one seller on one day, in a time window the
 * logistics company offers; every parcel the seller makes ready that day goes on it and one rider collects them all.
 * Each parcel keeps its own tracking number and is still scanned at pickup and again at the hub.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pickup_stops', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sorting_center_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('rider_id')->nullable()->constrained()->nullOnDelete();
            $t->date('pickup_date');
            $t->string('window_start', 5);    // 13:00
            $t->string('window_end', 5);      // 17:00
            $t->timestamps();
            $t->index(['sorting_center_id', 'pickup_date']);
            $t->index(['seller_id', 'pickup_date']);
        });

        Schema::table('parcels', function (Blueprint $t) {
            $t->foreignId('pickup_stop_id')->nullable()->after('current_hub_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $t) {
            $t->dropConstrainedForeignId('pickup_stop_id');
        });
        Schema::dropIfExists('pickup_stops');
    }
};
