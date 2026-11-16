<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rider wallet: the hub sends each rider their pay (their share of the shipping fee: ₱30 a pickup, ₱50 a delivery)
 * into an e-wallet. riders.wallet_since marks when this started for the rider: pay earned before it was settled the
 * old way (kept out of COD remittances); pay earned from then on goes to the wallet, and the rider remits all COD.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            $table->timestamp('wallet_since')->nullable();
        });
        DB::table('riders')->update(['wallet_since' => now()]);

        Schema::create('rider_wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sorting_center_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 20);                     // pay (sent by the hub); cashout later
            $table->decimal('amount', 12, 2);                // + into the wallet, − out of it
            $table->unsignedInteger('jobs')->default(0);
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['rider_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_wallet_entries');
        Schema::table('riders', function (Blueprint $table) {
            $table->dropColumn('wallet_since');
        });
    }
};
