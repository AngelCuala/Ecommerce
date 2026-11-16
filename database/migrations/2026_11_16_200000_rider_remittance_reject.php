<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The hub accepts a rider's COD remittance (after counting the cash) or rejects it with a reason (e.g. the cash was
 * never handed over): a rejected remittance doesn't count, the COD stays on the rider, and they remit again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_remittances', function (Blueprint $t) {
            $t->string('reject_reason', 300)->nullable()->after('status');
            $t->foreignId('rejected_by')->nullable()->after('settled_at')->constrained('users')->nullOnDelete();
            $t->timestamp('rejected_at')->nullable()->after('rejected_by');
        });
    }

    public function down(): void
    {
        Schema::table('rider_remittances', function (Blueprint $t) {
            $t->dropConstrainedForeignId('rejected_by');
            $t->dropColumn(['reject_reason', 'rejected_at']);
        });
    }
};
