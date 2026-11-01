<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller sign-up: a new account starts "unverified" until its email OTP is
 * entered, then becomes "pending" for admin review. Corporations upload an
 * SEC certificate instead of a DTI certificate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('approval_status', ['unverified', 'pending', 'approved', 'rejected'])
                ->default('pending')->change();
        });

        Schema::table('seller_applications', function (Blueprint $table) {
            $table->enum('status', ['unverified', 'pending', 'approved', 'rejected'])
                ->default('pending')->change();
            $table->string('sec_certificate_path')->nullable()->after('dti_certificate_path');
        });
    }

    public function down(): void
    {
        Schema::table('seller_applications', function (Blueprint $table) {
            $table->dropColumn('sec_certificate_path');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->default('pending')->change();
        });
    }
};
