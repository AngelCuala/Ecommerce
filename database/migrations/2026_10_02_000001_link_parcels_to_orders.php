<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Connects the Sorting Center parcel pipeline to real marketplace orders.
 *
 *  - parcels.order_id            the order this parcel ships (one parcel per order + seller)
 *  - destination_*               buyer's municipality / province, used for routing + area detection
 *  - received_at / sorted_at     sorting-center timestamps
 *  - failure_reason              recorded when a pickup is rejected or a delivery fails
 *  - status enum gains 'returned' (parcel returned to seller)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('tracking_number')
                ->constrained('orders')->nullOnDelete();
            $table->string('destination_municipality')->nullable()->after('dropoff_address');
            $table->string('destination_province')->nullable()->after('destination_municipality');
            $table->timestamp('pickup_scheduled_at')->nullable()->after('notes');
            $table->timestamp('received_at')->nullable()->after('verified_at');
            $table->timestamp('sorted_at')->nullable()->after('received_at');
            $table->text('failure_reason')->nullable()->after('sorted_at');

            $table->unique(['order_id', 'seller_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE parcels MODIFY status ENUM(
                'pending_pickup','pickup_approved','pickup_rejected','picked_up',
                'sorted','assigned','in_transit','delivered','failed','returned'
            ) NOT NULL DEFAULT 'pending_pickup'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::table('parcels')->where('status', 'returned')->update(['status' => 'failed']);
            DB::statement("ALTER TABLE parcels MODIFY status ENUM(
                'pending_pickup','pickup_approved','pickup_rejected','picked_up',
                'sorted','assigned','in_transit','delivered','failed'
            ) NOT NULL DEFAULT 'pending_pickup'");
        }

        Schema::table('parcels', function (Blueprint $table) {
            $table->dropUnique(['order_id', 'seller_id']);
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn([
                'destination_municipality', 'destination_province',
                'pickup_scheduled_at', 'received_at', 'sorted_at', 'failure_reason',
            ]);
        });
    }
};
