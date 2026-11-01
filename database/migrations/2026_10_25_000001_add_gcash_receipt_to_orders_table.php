<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GCash paid by the buyer outside ALVY: the receipt screenshot they upload at checkout (private disk)
 * and the GCash reference number they type. Additive only; existing orders keep nulls.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_proof_path')->nullable()->after('payment_status');
            $table->string('payment_reference', 40)->nullable()->after('payment_proof_path');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_proof_path', 'payment_reference']);
        });
    }
};
