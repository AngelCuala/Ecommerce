<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller modules, assumption A17: prices go up to ₱1,000,000.00, which decimal(8,2) can't hold.
 * A draft can be saved with only its name, so its platform category may still be empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->decimal('price', 10, 2)->change();
            $t->unsignedBigInteger('category_id')->nullable()->change();
        });
        Schema::table('order_items', function (Blueprint $t) {
            $t->decimal('price', 10, 2)->change();
            $t->decimal('commission_amount', 12, 2)->default(0)->change();
            $t->decimal('seller_earning', 12, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        // Widened columns are left as they are: narrowing them could cut off saved amounts.
    }
};
