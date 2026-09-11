<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('courier_id')->nullable()->constrained('couriers')->nullOnDelete();

            $table->decimal('delivery_fee', 8, 2)->default(50.00);  // fixed ₱50

            $table->enum('status', [
                'available',    // waiting for courier to accept
                'accepted',     // courier accepted, not yet picked up
                'picked_up',    // courier has the parcel
                'in_transit',   // on the way
                'delivered',    // delivered to buyer
                'failed',       // delivery failed
            ])->default('available');

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
