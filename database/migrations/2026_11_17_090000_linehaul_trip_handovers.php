<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trip handover QR (App\Services\TripHandover): the hub scans the driver's rotating QR (or types the 6-digit code)
 * at loading and at arrival. Each scan is one row here (the audit of who, where, when, and the counts); a token
 * can be used once (token_hash is unique). The origin hub's confirmation seals the truck (linehaul_trips.sealed_at):
 * only then can the driver depart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linehaul_handovers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('linehaul_trip_id')->constrained('linehaul_trips')->cascadeOnDelete();
            $t->string('stage', 10);                                   // load (origin) | receive (destination)
            $t->foreignId('hub_id')->constrained('logistics_hubs');
            $t->foreignId('courier_id')->nullable()->constrained('couriers')->nullOnDelete();
            $t->string('plate_number', 20)->nullable();
            $t->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('method', 10);                                  // qr | code
            $t->string('token_hash', 64)->unique();                    // a token / code works once
            $t->unsignedInteger('expected')->default(0);               // parcels on the trip when scanned
            $t->unsignedInteger('scanned')->nullable();                // loaded / received when confirmed
            $t->decimal('weight_kg', 10, 2)->nullable();
            $t->text('note')->nullable();                              // required when the counts don't match
            $t->dateTime('verified_at');
            $t->dateTime('confirmed_at')->nullable();
            $t->timestamps();
            $t->index(['linehaul_trip_id', 'stage']);
        });

        Schema::table('linehaul_trips', function (Blueprint $t) {
            $t->dateTime('sealed_at')->nullable()->after('loading_at');   // the origin hub confirmed the handover
        });
    }

    public function down(): void
    {
        Schema::table('linehaul_trips', fn (Blueprint $t) => $t->dropColumn('sealed_at'));
        Schema::dropIfExists('linehaul_handovers');
    }
};
