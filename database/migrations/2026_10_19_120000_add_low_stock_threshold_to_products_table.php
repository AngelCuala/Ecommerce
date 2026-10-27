<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-product low-stock alert level. Null = the store-wide default (config marketplace.low_stock_threshold).
 * Additive only: existing products keep the default, no data changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'low_stock_threshold')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedInteger('low_stock_threshold')->nullable()->after('stock');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('products', 'low_stock_threshold')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('low_stock_threshold');
            });
        }
    }
};
