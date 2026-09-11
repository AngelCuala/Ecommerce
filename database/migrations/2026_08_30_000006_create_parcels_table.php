<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcels', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('pickup_address');
            $table->string('dropoff_address');
            $table->string('receiver_name');
            $table->string('receiver_phone')->nullable();
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->string('size')->nullable();   // small / medium / large or dimensions
            $table->text('notes')->nullable();
            $table->foreignId('area_id')->nullable()->constrained('delivery_areas')->nullOnDelete();

            // Full lifecycle:
            // pending_pickup → pickup_approved → picked_up → sorted → assigned → in_transit → delivered / failed
            $table->enum('status', [
                'pending_pickup',
                'pickup_approved',
                'pickup_rejected',
                'picked_up',
                'sorted',
                'assigned',
                'in_transit',
                'delivered',
                'failed',
            ])->default('pending_pickup');

            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};
