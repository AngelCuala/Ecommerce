<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAYMENTS_SPEC §5, §7: the seller wallet is a ledger. One line per delivered order (+ the seller's 90%): on hold
 * during the return window (with its expected release date), available once the order is completed (the buyer
 * confirmed, or the window ended), reversed when it is refunded. Withdrawals (seller_payouts) go to a saved GCash,
 * Maya or bank account; ALVY sends them from its bank and marks them completed (with the bank reference) or failed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_wallet_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $t->string('type', 20);                       // order_credit, return_reversal
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->decimal('amount', 12, 2);
            $t->string('status', 12);                     // on_hold, available, reversed
            $t->timestamp('release_at')->nullable();      // expected release (end of the return window)
            $t->timestamp('earned_at')->nullable();       // delivered
            $t->string('note')->nullable();
            $t->timestamps();
            $t->unique(['seller_id', 'type', 'order_id']);
            $t->index(['seller_id', 'status']);
        });

        Schema::table('seller_payouts', function (Blueprint $t) {
            $t->string('method', 10)->nullable()->after('status');        // gcash, maya, bank
            $t->string('bank_name', 80)->nullable()->after('method');
            $t->string('account_name', 120)->nullable()->after('bank_name');
            $t->string('account_number', 30)->nullable()->after('account_name');
            $t->string('reject_reason', 300)->nullable()->after('reference');
            $t->foreignId('processed_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
            $t->timestamp('processed_at')->nullable()->after('processed_by');
        });
    }

    public function down(): void
    {
        Schema::table('seller_payouts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('processed_by');
            $t->dropColumn(['method', 'bank_name', 'account_name', 'account_number', 'reject_reason', 'processed_at']);
        });
        Schema::dropIfExists('seller_wallet_entries');
    }
};
