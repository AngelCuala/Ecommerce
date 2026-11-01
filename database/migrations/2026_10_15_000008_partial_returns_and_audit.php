<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A8: a return can cover only some items of an order (refund = item price minus its voucher share).
 * A35: the audit log keeps the old value, new value and reason of manual overrides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', fn (Blueprint $t) => $t->json('item_ids')->nullable());
        Schema::table('order_items', function (Blueprint $t) {
            $t->timestamp('refunded_at')->nullable();
            $t->decimal('refund_amount', 12, 2)->nullable();
        });
        Schema::table('activity_logs', function (Blueprint $t) {
            $t->string('old_value')->nullable();
            $t->string('new_value')->nullable();
            $t->text('reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', fn (Blueprint $t) => $t->dropColumn(['old_value', 'new_value', 'reason']));
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn(['refunded_at', 'refund_amount']));
        Schema::table('return_requests', fn (Blueprint $t) => $t->dropColumn('item_ids'));
    }
};
