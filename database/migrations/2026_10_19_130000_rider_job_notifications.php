<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rider job notifications.
 *  - user_notifications.dedupe_key: one notification per job event (assigned / cancelled / reassigned),
 *    so a retried request never notifies twice. Null for every other notification (existing rows untouched).
 *  - parcel_pickups / parcel_deliveries.acknowledged_at: when the rider confirmed they saw the job.
 * Additive only; no existing data changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('user_notifications', 'dedupe_key')) {
            Schema::table('user_notifications', function (Blueprint $table) {
                $table->string('dedupe_key', 120)->nullable()->after('link');
                $table->unique(['user_id', 'dedupe_key']);
            });
        }
        foreach (['parcel_pickups', 'parcel_deliveries'] as $t) {
            if (Schema::hasTable($t) && ! Schema::hasColumn($t, 'acknowledged_at')) {
                Schema::table($t, fn (Blueprint $table) => $table->timestamp('acknowledged_at')->nullable());
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('user_notifications', 'dedupe_key')) {
            Schema::table('user_notifications', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'dedupe_key']);
                $table->dropColumn('dedupe_key');
            });
        }
        foreach (['parcel_pickups', 'parcel_deliveries'] as $t) {
            if (Schema::hasColumn($t, 'acknowledged_at')) {
                Schema::table($t, fn (Blueprint $table) => $table->dropColumn('acknowledged_at'));
            }
        }
    }
};
