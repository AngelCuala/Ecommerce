<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * orders.status and orders.payment_status were ENUMs that only allowed the original values.
 * The buyer modules add statuses (Awaiting Payment, Completed, Return Requested, Refunded) and
 * payment states (Unpaid, Refund pending, Refunded), so they become plain strings.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->string('status', 30)->default('Pending')->change();
            $t->string('payment_status', 30)->default('Pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->enum('status', ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'])->default('Pending')->change();
            $t->enum('payment_status', ['Pending', 'Paid', 'Failed'])->default('Pending')->change();
        });
    }
};
