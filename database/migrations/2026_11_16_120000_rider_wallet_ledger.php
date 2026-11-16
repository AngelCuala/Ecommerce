<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAYMENTS_SPEC: the rider wallet is a ledger. Every job is its own line (pickup_credit, delivery_credit), a COD
 * delivery's line is added when the hub confirms the remittance that covers it (held while a shortage is open),
 * a shortage taken from earnings is a shortage_adjustment line, and a withdrawal goes to a saved GCash, Maya or
 * bank account. The hub records the cash it counted, who confirmed it, and any shortage it deducted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_wallet_entries', function (Blueprint $t) {
            $t->string('source_type', 20)->nullable()->after('type');        // pickup, delivery, delivery_cod, remittance
            $t->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $t->foreignId('parcel_id')->nullable()->after('source_id')->constrained()->nullOnDelete();
            $t->timestamp('earned_at')->nullable()->after('parcel_id');
            $t->string('payout_method', 10)->nullable()->after('gcash_name');  // gcash, maya, bank
            $t->string('bank_name', 80)->nullable()->after('payout_method');
            $t->unique(['rider_id', 'type', 'source_type', 'source_id'], 'rider_wallet_entries_source_unique');
        });

        Schema::table('rider_remittances', function (Blueprint $t) {
            $t->decimal('counted_amount', 12, 2)->nullable()->after('expected_amount');
            $t->decimal('deducted', 12, 2)->default(0)->after('counted_amount');   // shortage taken from the rider's earnings
            $t->foreignId('settled_by')->nullable()->after('confirmed_at')->constrained('users')->nullOnDelete();
            $t->timestamp('settled_at')->nullable()->after('settled_by');
        });

        // Saved places to send a withdrawal (riders now; sellers use the same table).
        Schema::create('payout_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('method', 10);                 // gcash, maya, bank
            $t->string('bank_name', 80)->nullable();
            $t->string('account_name', 120);
            $t->string('account_number', 30);
            $t->timestamps();
            $t->unique(['user_id', 'method', 'account_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_accounts');
        Schema::table('rider_remittances', function (Blueprint $t) {
            $t->dropConstrainedForeignId('settled_by');
            $t->dropColumn(['counted_amount', 'deducted', 'settled_at']);
        });
        Schema::table('rider_wallet_entries', function (Blueprint $t) {
            $t->dropUnique('rider_wallet_entries_source_unique');
            $t->dropConstrainedForeignId('parcel_id');
            $t->dropColumn(['source_type', 'source_id', 'earned_at', 'payout_method', 'bank_name']);
        });
    }
};
