<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Sorting-center accounts are assigned to ONE municipality ──
        Schema::table('users', function (Blueprint $table) {
            $table->string('assigned_municipality')->nullable()->after('barangay');
            $table->string('assigned_municipality_code')->nullable()->after('assigned_municipality');
            $table->string('assigned_province')->nullable()->after('assigned_municipality_code');
            $table->string('assigned_province_code')->nullable()->after('assigned_province');
        });

        // ── Delivery areas (barangays/zones) belong to a municipality + SC ──
        Schema::table('delivery_areas', function (Blueprint $table) {
            $table->foreignId('sorting_center_id')->nullable()->after('id')
                ->constrained('users')->nullOnDelete();
            $table->string('municipality')->nullable()->after('description');
            $table->string('municipality_code')->nullable()->after('municipality');
            $table->string('barangay_code')->nullable()->after('municipality_code');
        });

        // ── Each rider belongs to the SC that manages them ──
        Schema::table('riders', function (Blueprint $table) {
            $table->foreignId('sorting_center_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sorting_center_id');
        });

        Schema::table('delivery_areas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sorting_center_id');
            $table->dropColumn(['municipality', 'municipality_code', 'barangay_code']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'assigned_municipality',
                'assigned_municipality_code',
                'assigned_province',
                'assigned_province_code',
            ]);
        });
    }
};
