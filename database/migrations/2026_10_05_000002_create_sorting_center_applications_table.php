<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logistics / Sorting Center registrations (ERP): the applicant's details, business,
 * PSA PSGC address (its municipality becomes the center's coverage on approval) and
 * uploaded documents, reviewed by an administrator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sorting_center_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('last_name');
            $table->string('first_name');
            $table->string('middle_initial', 5)->nullable();
            $table->string('sex', 10);
            $table->date('birthday');
            $table->unsignedTinyInteger('age');
            $table->string('contact_no', 30);

            $table->string('business_name');

            // Address (official PSA names + 9-digit Correspondence Codes)
            $table->string('region')->nullable();
            $table->string('region_code', 9)->nullable();
            $table->string('province')->nullable();
            $table->string('province_code', 9)->nullable();
            $table->string('municipality');
            $table->string('municipality_code', 9);
            $table->string('barangay');
            $table->string('barangay_code', 9)->nullable();
            $table->string('street');
            $table->string('house_number', 50);
            $table->string('zip_code', 20)->nullable();
            $table->text('address');

            // Documents (private disk)
            $table->string('government_id_path');
            $table->string('business_permit_path');

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sorting_center_applications');
    }
};
