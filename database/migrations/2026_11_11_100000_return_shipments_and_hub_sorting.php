<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - parcels.next_center_id: a parcel the hub sorted for another sorting center (outside its jurisdiction):
 *   it waits for a hub-to-hub (linehaul) trip there instead of a rider.
 * - return_shipments: a buyer's return, carried like a pickup — a rider collects it from the buyer (photo + waybill scan),
 *   the hub receives it, then a rider brings it back to the seller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->foreignId('next_center_id')->nullable()->after('area_id')->constrained('users')->nullOnDelete();
        });

        Schema::create('return_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('return_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parcel_id')->nullable()->constrained()->nullOnDelete();      // the delivered parcel (its waybill is scanned)
            $table->foreignId('sorting_center_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('requested')->index();
            $table->foreignId('pickup_rider_id')->nullable()->constrained('riders')->nullOnDelete();
            $table->timestamp('pickup_assigned_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->string('pickup_proof_path')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('seller_rider_id')->nullable()->constrained('riders')->nullOnDelete();
            $table->timestamp('seller_assigned_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('returned_proof_path')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('return_shipments');
        Schema::table('parcels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('next_center_id');
        });
    }
};
