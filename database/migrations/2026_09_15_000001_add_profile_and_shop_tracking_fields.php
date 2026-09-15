<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── users: profile photo + missing profile extras ──────
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'profile_photo_path')) {
                $table->string('profile_photo_path')->nullable()->after('password');
            }
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 30)->nullable()->after('profile_photo_path');
            }
            if (! Schema::hasColumn('users', 'address')) {
                $table->string('address')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('users', 'city')) {
                $table->string('city', 120)->nullable()->after('address');
            }
            if (! Schema::hasColumn('users', 'zip')) {
                $table->string('zip', 20)->nullable()->after('city');
            }
            if (! Schema::hasColumn('users', 'country')) {
                $table->string('country', 120)->nullable()->after('zip');
            }
        });

        // ── seller_applications: shop name change tracking ─────
        Schema::table('seller_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('seller_applications', 'shop_name_changes_this_month')) {
                $table->unsignedTinyInteger('shop_name_changes_this_month')->default(0)->after('shop_name');
            }
            if (! Schema::hasColumn('seller_applications', 'shop_name_last_changed_at')) {
                $table->timestamp('shop_name_last_changed_at')->nullable()->after('shop_name_changes_this_month');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('users', 'profile_photo_path') ? 'profile_photo_path' : null,
                Schema::hasColumn('users', 'phone')              ? 'phone'              : null,
                Schema::hasColumn('users', 'address')            ? 'address'            : null,
                Schema::hasColumn('users', 'city')               ? 'city'               : null,
                Schema::hasColumn('users', 'zip')                ? 'zip'                : null,
                Schema::hasColumn('users', 'country')            ? 'country'            : null,
            ]));
        });

        Schema::table('seller_applications', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('seller_applications', 'shop_name_changes_this_month') ? 'shop_name_changes_this_month' : null,
                Schema::hasColumn('seller_applications', 'shop_name_last_changed_at')    ? 'shop_name_last_changed_at'    : null,
            ]));
        });
    }
};
