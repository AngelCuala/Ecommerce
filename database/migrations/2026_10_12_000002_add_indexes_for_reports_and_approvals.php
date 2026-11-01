<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the queries the newer features run on every page or report:
 * sales reports (orders by date/status), the shop's "published" filter, account role
 * lookups (sorting centers, sellers) and the sorting center's courier applications.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['created_at', 'status'], 'orders_created_at_status_index');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->index(['approval_status', 'status', 'is_archived'], 'products_publish_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index(['role', 'approval_status'], 'users_role_approval_index');
        });

        Schema::table('couriers', function (Blueprint $table) {
            $table->index(['sorting_center_id', 'status'], 'couriers_hub_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropIndex('orders_created_at_status_index'));
        Schema::table('products', fn (Blueprint $t) => $t->dropIndex('products_publish_index'));
        Schema::table('users', fn (Blueprint $t) => $t->dropIndex('users_role_approval_index'));
        Schema::table('couriers', fn (Blueprint $t) => $t->dropIndex('couriers_hub_status_index'));
    }
};
