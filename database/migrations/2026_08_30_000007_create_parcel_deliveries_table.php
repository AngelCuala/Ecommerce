<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Named "parcel_deliveries" to avoid conflict with the existing "deliveries" table
        // which is used by the main ALVY order fulfillment system.
        Schema::create('parcel_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')->constrained('parcels')->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->foreignId('area_id')->nullable()->constrained('delivery_areas')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('status', ['assigned', 'out_for_delivery', 'delivered', 'failed', 'returned'])
                ->default('assigned');

            $table->text('remarks')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_deliveries');
    }
};
