<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('couriers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Personal info
            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_initial')->nullable();
            $table->enum('sex', ['Male', 'Female', 'Other']);
            $table->string('contact_no');
            $table->date('birthday');
            $table->unsignedTinyInteger('age');          // auto-calculated

            // Address (Philippine)
            $table->string('province');
            $table->string('municipality');
            $table->string('barangay');
            $table->string('street')->nullable();        // house no., street, etc.

            // Vehicle
            $table->string('vehicle_type');              // e.g. Motorcycle, Car
            $table->string('plate_number');

            // Documents
            $table->string('or_cr_path');                // OR/CR upload
            $table->string('id_license_path');           // ID/Driver's license upload

            // Status
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])
                  ->default('pending');
            $table->text('rejection_reason')->nullable();

            // Earnings
            $table->decimal('total_earnings', 10, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('couriers');
    }
};
