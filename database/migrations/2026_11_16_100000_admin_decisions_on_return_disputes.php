<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The admin's decision on a disputed return (who, when, why), kept apart from the seller's note. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_requests', function (Blueprint $t) {
            if (! Schema::hasColumn('return_requests', 'admin_decision')) {
                $t->string('admin_decision', 20)->nullable();        // refund | close
                $t->text('admin_note')->nullable();
                $t->foreignId('admin_decided_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('admin_decided_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', function (Blueprint $t) {
            $t->dropConstrainedForeignId('admin_decided_by');
            $t->dropColumn(['admin_decision', 'admin_note', 'admin_decided_at']);
        });
    }
};
