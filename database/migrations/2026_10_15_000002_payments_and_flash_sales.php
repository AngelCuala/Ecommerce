<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Online payments (e-wallet / card through a gateway) and flash sales.
 *  - payments: one per payment attempt for a checkout (all its store orders); "Pay again" makes a new one.
 *  - flash_sales: a seller's product at a flash price for a time slot, for a limited quantity.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->uuid('checkout_group')->index();
            $t->string('method', 20);                       // gcash | maya | card
            $t->string('provider', 20);                     // sandbox | paymongo
            $t->decimal('amount', 12, 2);
            $t->string('status', 20)->default('pending');   // pending | paid | failed | cancelled | expired
            $t->string('provider_ref')->nullable()->index(); // gateway checkout / session id
            $t->string('provider_payment_id')->nullable();  // gateway payment id (for refunds)
            $t->text('checkout_url')->nullable();
            $t->string('failure_reason')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
        });

        Schema::table('orders', function (Blueprint $t) {
            $t->timestamp('pay_by')->nullable()->after('payment_status');     // unpaid online orders cancel after this
            $t->decimal('refunded_amount', 12, 2)->default(0)->after('pay_by');
        });

        Schema::create('flash_sales', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $t->decimal('flash_price', 12, 2);
            $t->unsignedInteger('quantity');
            $t->unsignedInteger('sold')->default(0);
            $t->timestamp('starts_at')->index();
            $t->timestamp('ends_at');
            $t->timestamp('reminders_sent_at')->nullable();
            $t->timestamps();
        });

        Schema::create('flash_sale_reminders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('flash_sale_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['flash_sale_id', 'user_id']);
        });

        Schema::table('order_items', function (Blueprint $t) {
            $t->foreignId('flash_sale_id')->nullable()->after('variation_name')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $t) => $t->dropConstrainedForeignId('flash_sale_id'));
        Schema::dropIfExists('flash_sale_reminders');
        Schema::dropIfExists('flash_sales');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['pay_by', 'refunded_amount']));
        Schema::dropIfExists('payments');
    }
};
