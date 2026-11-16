<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A seller can appeal a contact strike ("this was a mistake"); an administrator accepts it (the strike no longer
 * counts) or rejects it, with a note. Strikes are deleted after a year (contact-strikes:prune).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_strikes', function (Blueprint $table) {
            $table->string('appeal_status', 20)->nullable()->after('kinds');   // pending / accepted / rejected
            $table->string('appeal_reason', 500)->nullable()->after('appeal_status');
            $table->timestamp('appealed_at')->nullable()->after('appeal_reason');
            $table->string('appeal_note', 500)->nullable()->after('appealed_at');
            $table->foreignId('appeal_decided_by')->nullable()->after('appeal_note')->constrained('users')->nullOnDelete();
            $table->timestamp('appeal_decided_at')->nullable()->after('appeal_decided_by');
        });
    }

    public function down(): void
    {
        Schema::table('contact_strikes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('appeal_decided_by');
            $table->dropColumn(['appeal_status', 'appeal_reason', 'appealed_at', 'appeal_note', 'appeal_decided_at']);
        });
    }
};
