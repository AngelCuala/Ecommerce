<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store the full PSA PSGC chain (9-digit Correspondence Codes, the project's stored-code
 * convention) for SC riders and coverage areas. Additive and nullable: existing rows keep
 * working unchanged; see `php artisan sc:backfill-psgc-codes --dry-run`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->string('region_code', 9)->nullable()->after('area_id');
            $table->string('province_code', 9)->nullable()->after('region_code');
            $table->string('municipality_code', 9)->nullable()->after('province_code');
            $table->string('barangay_code', 9)->nullable()->after('municipality_code');
        });

        // delivery_areas already has municipality_code + barangay_code.
        Schema::table('delivery_areas', function (Blueprint $table) {
            $table->string('region_code', 9)->nullable()->after('municipality');
            $table->string('province_code', 9)->nullable()->after('region_code');
        });
    }

    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->dropColumn(['region_code', 'province_code', 'municipality_code', 'barangay_code']);
        });
        Schema::table('delivery_areas', function (Blueprint $table) {
            $table->dropColumn(['region_code', 'province_code']);
        });
    }
};
