<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('commission_rate', 5, 2)->default(10.00)->after('price');   // e.g. 10.00 = 10%
            $table->decimal('commission_amount', 8, 2)->default(0)->after('commission_rate');
            $table->decimal('seller_earning', 8, 2)->default(0)->after('commission_amount');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['commission_rate', 'commission_amount', 'seller_earning']);
        });
    }
};
