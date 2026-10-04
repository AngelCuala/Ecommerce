<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Delivery routing (same municipality → direct courier, different → sorting center)
 * compares official PSGC municipality codes instead of free-text names.
 *
 *  - orders.municipality_code / province_code               buyer's delivery address (from checkout dropdowns)
 *  - seller_applications.municipality_code / province_code  seller's official pickup/origin address
 *  - parcels.status gains 'cancelled'                         closes a sorting-center request that must not
 *                                                             run (e.g. a duplicate of a courier delivery)
 *                                                             without deleting its history
 *
 * All new columns are nullable: existing records keep working without codes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('province_code', 20)->nullable()->after('province');
            $table->string('municipality_code', 20)->nullable()->after('city');
        });

        Schema::table('seller_applications', function (Blueprint $table) {
            $table->string('province_code', 20)->nullable()->after('province');
            $table->string('municipality_code', 20)->nullable()->after('municipality');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE parcels MODIFY status ENUM(
                'pending_pickup','pickup_approved','pickup_rejected','picked_up',
                'sorted','assigned','in_transit','delivered','failed','returned','cancelled'
            ) NOT NULL DEFAULT 'pending_pickup'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('parcels')->where('status', 'cancelled')->update(['status' => 'pickup_rejected']);
            DB::statement("ALTER TABLE parcels MODIFY status ENUM(
                'pending_pickup','pickup_approved','pickup_rejected','picked_up',
                'sorted','assigned','in_transit','delivered','failed','returned'
            ) NOT NULL DEFAULT 'pending_pickup'");
        }

        Schema::table('seller_applications', function (Blueprint $table) {
            $table->dropColumn(['province_code', 'municipality_code']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['province_code', 'municipality_code']);
        });
    }
};
