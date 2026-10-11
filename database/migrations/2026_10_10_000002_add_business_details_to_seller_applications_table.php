<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Become a Seller" now asks for seller type, TIN, region, postal code and two
 * extra business documents (DTI certificate, BIR Form 2303).
 * The store description is no longer asked for, so it becomes optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_applications', function (Blueprint $table) {
            $table->string('seller_type', 50)->nullable()->after('line_of_business');
            $table->string('tin', 20)->nullable()->after('seller_type');
            $table->string('region', 120)->nullable()->after('address');
            $table->string('zip_code', 10)->nullable()->after('house_number');
            $table->string('dti_certificate_path')->nullable()->after('business_permit_path');
            $table->string('bir_2303_path')->nullable()->after('dti_certificate_path');
            $table->text('description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('seller_applications', function (Blueprint $table) {
            $table->dropColumn(['seller_type', 'tin', 'region', 'zip_code', 'dti_certificate_path', 'bir_2303_path']);
        });
    }
};
