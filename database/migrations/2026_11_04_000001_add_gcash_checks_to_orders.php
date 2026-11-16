<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GCash paid to ALVY:
 *  - payment_expected: the exact amount the buyer was asked to send for the whole checkout (the orders' total minus
 *    a few centavos ALVY covers, different for each waiting payment, so the admin finds it in GCash at a glance)
 *  - payment_check: what the receipt reader found (amount, Ref No., is it a GCash receipt) and reminders sent
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->decimal('payment_expected', 10, 2)->nullable()->after('payment_reference');
            $t->json('payment_check')->nullable()->after('payment_expected');
            $t->index('payment_reference');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex(['payment_reference']);
            $t->dropColumn(['payment_expected', 'payment_check']);
        });
    }
};
