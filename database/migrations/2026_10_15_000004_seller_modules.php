<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Seller modules (S1–S12 test set):
 *  - store profile: logo, banner, vacation mode
 *  - store categories (two levels) separate from the platform categories
 *  - product condition, scheduled sales (start time), variant images
 *  - vouchers limited to chosen products; bundle and buy-X-get-Y promotions
 *  - order steps (accepted, packed), dispute on a rejected return, payouts
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_applications', function (Blueprint $t) {
            $t->string('logo_path')->nullable();
            $t->string('banner_path')->nullable();
            $t->boolean('is_on_vacation')->default(false);
        });

        Schema::create('store_categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('store_categories')->cascadeOnDelete();
            $t->string('name', 50);
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_hidden')->default(false);
            $t->timestamps();
            $t->index(['seller_id', 'sort_order']);
        });

        Schema::create('product_store_category', function (Blueprint $t) {
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('store_category_id')->constrained()->cascadeOnDelete();
            $t->primary(['product_id', 'store_category_id']);
        });

        Schema::table('products', function (Blueprint $t) {
            $t->string('condition', 20)->default('New')->after('brand');
            $t->timestamp('sale_starts_at')->nullable()->after('discount_percent');
        });

        Schema::table('product_variations', function (Blueprint $t) {
            $t->string('image_path')->nullable()->after('sku');
        });

        Schema::table('vouchers', function (Blueprint $t) {
            $t->json('product_ids')->nullable()->after('seller_id');   // store voucher for these products only
        });

        Schema::create('promotions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $t->string('name', 120);
            $t->string('type', 20);                                    // bundle | bxgy
            $t->json('product_ids');                                   // bundle: qualifying products; bxgy: the product to buy
            $t->unsignedInteger('min_qty');                            // bundle: items needed; bxgy: units to buy
            $t->decimal('discount_percent', 5, 2)->nullable();         // bundle
            $t->foreignId('free_product_id')->nullable()->constrained('products')->nullOnDelete();   // bxgy
            $t->unsignedInteger('free_qty')->default(1);
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::table('orders', function (Blueprint $t) {
            $t->timestamp('accepted_at')->nullable();
            $t->timestamp('packed_at')->nullable();
            $t->decimal('promo_discount', 10, 2)->default(0)->after('seller_discount');
        });
        Schema::table('order_items', function (Blueprint $t) {
            $t->decimal('promo_discount', 10, 2)->default(0)->after('seller_discount');
        });

        Schema::table('return_requests', function (Blueprint $t) {
            $t->timestamp('escalated_at')->nullable();
            $t->text('dispute_note')->nullable();
        });

        Schema::create('seller_payouts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->string('status', 20)->default('pending');              // pending | paid | rejected
            $t->uuid('request_token')->unique();                       // one request per click
            $t->string('reference')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_payouts');
        Schema::table('return_requests', fn (Blueprint $t) => $t->dropColumn(['escalated_at', 'dispute_note']));
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('promo_discount'));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['accepted_at', 'packed_at', 'promo_discount']));
        Schema::dropIfExists('promotions');
        Schema::table('vouchers', fn (Blueprint $t) => $t->dropColumn('product_ids'));
        Schema::table('product_variations', fn (Blueprint $t) => $t->dropColumn('image_path'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['condition', 'sale_starts_at']));
        Schema::dropIfExists('product_store_category');
        Schema::dropIfExists('store_categories');
        Schema::table('seller_applications', fn (Blueprint $t) => $t->dropColumn(['logo_path', 'banner_path', 'is_on_vacation']));
    }
};
