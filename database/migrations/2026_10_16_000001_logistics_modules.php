<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Logistics workbook (L1–L8):
 *  - shipping rates the logistics team can change (override config/shipping.php)
 *  - delivery zones added for provinces no zone covered yet (e.g. Batanes)
 *  - Return-to-Sender trip back to the seller, and the seller's receipt of it
 *  - Lost / Damaged exceptions with a photo
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipping_rates')) {
            Schema::create('shipping_rates', function (Blueprint $t) {
                $t->id();
                $t->string('route', 60)->unique();          // "NCR-NCR", "NCR-Visayas", …
                $t->decimal('first_kg', 10, 2);
                $t->decimal('additional_kg', 10, 2);
                $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('shipping_zones')) {
            Schema::create('shipping_zones', function (Blueprint $t) {
                $t->id();
                $t->string('name', 60);
                $t->string('province_code', 10)->unique();  // 9-digit PSGC province code
                $t->string('province_name', 120);
                $t->decimal('first_kg', 10, 2);
                $t->decimal('additional_kg', 10, 2);
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
            });
        }

        Schema::table('parcels', function (Blueprint $t) {
            if (! Schema::hasColumn('parcels', 'rts_rider_id')) {
                $t->unsignedBigInteger('rts_rider_id')->nullable()->after('failure_reason');
                $t->timestamp('rts_assigned_at')->nullable()->after('rts_rider_id');
                $t->timestamp('returned_at')->nullable()->after('rts_assigned_at');
                $t->timestamp('returned_received_at')->nullable()->after('returned_at');
            }
            if (! Schema::hasColumn('parcels', 'exception_photo_path')) {
                $t->string('exception_photo_path')->nullable()->after('returned_received_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $t) {
            $t->dropColumn(['rts_rider_id', 'rts_assigned_at', 'returned_at', 'returned_received_at', 'exception_photo_path']);
        });
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('shipping_rates');
    }
};
