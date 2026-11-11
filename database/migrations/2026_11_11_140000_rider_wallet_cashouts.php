<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GCash cash-out from a rider's e-wallet: a 'cashout' wallet line (negative amount) the rider requests; their hub
 * sends the money by GCash and marks it paid with the GCash reference, or rejects it (the money stays in the wallet).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_wallet_entries', function (Blueprint $table) {
            $table->string('status', 20)->nullable()->after('type');   // cashout: pending / paid / rejected
            $table->string('gcash_number', 20)->nullable()->after('note');
            $table->string('gcash_name', 120)->nullable()->after('gcash_number');
            $table->string('reference', 60)->nullable()->after('gcash_name');
            $table->string('reject_reason', 300)->nullable()->after('reference');
            $table->foreignId('processed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable()->after('processed_by');
            $table->index(['sorting_center_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('rider_wallet_entries', function (Blueprint $table) {
            $table->dropIndex(['sorting_center_id', 'type', 'status']);
            $table->dropConstrainedForeignId('processed_by');
            $table->dropColumn(['status', 'gcash_number', 'gcash_name', 'reference', 'reject_reason', 'processed_at']);
        });
    }
};
