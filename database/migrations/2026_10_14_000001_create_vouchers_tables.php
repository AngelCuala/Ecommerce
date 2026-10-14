<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vouchers.
 *  - Platform vouchers (scope "platform") are made by ALVY admins and work on any seller's
 *    products, e.g. an 11.11 sale. ALVY pays for the discount; sellers earn the same.
 *  - Store vouchers (scope "seller") are made by a seller and only work on that seller's
 *    products, e.g. ₱150 off a ₱2,000 spend. The seller pays for the discount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->string('scope', 20);                                   // platform | seller
            $table->foreignId('seller_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('discount_type', 10);                           // fixed | percent
            $table->decimal('discount_value', 10, 2);
            $table->decimal('max_discount', 10, 2)->nullable();            // cap for percent vouchers
            $table->decimal('min_spend', 10, 2)->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('usage_limit')->nullable();            // total uses, null = unlimited
            $table->unsignedInteger('per_user_limit')->default(1);
            $table->unsignedInteger('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['scope', 'is_active', 'ends_at']);
            $table->index(['seller_id', 'is_active']);
        });

        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->decimal('discount_amount', 10, 2);
            $table->timestamps();

            $table->index(['voucher_id', 'user_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('platform_voucher_id')->nullable()->after('total_price')->constrained('vouchers')->nullOnDelete();
            $table->decimal('platform_discount', 10, 2)->default(0)->after('platform_voucher_id');
            $table->decimal('seller_discount', 10, 2)->default(0)->after('platform_discount');
        });

        Schema::table('order_items', function (Blueprint $table) {
            // This line's share of the order's vouchers.
            $table->decimal('seller_discount', 10, 2)->default(0)->after('price');
            $table->decimal('platform_discount', 10, 2)->default(0)->after('seller_discount');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn(['seller_discount', 'platform_discount']));
        Schema::table('orders', function (Blueprint $t) {
            $t->dropConstrainedForeignId('platform_voucher_id');
            $t->dropColumn(['platform_discount', 'seller_discount']);
        });
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('vouchers');
    }
};
