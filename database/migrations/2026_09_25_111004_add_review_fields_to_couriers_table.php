<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('couriers', function (Blueprint $table) {
            if (! Schema::hasColumn('couriers', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('rejection_reason');
            }
            if (! Schema::hasColumn('couriers', 'reviewed_at')) {
                $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            }
            if (! Schema::hasColumn('couriers', 'reviewed_by')) {
                $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')
                      ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('couriers', function (Blueprint $table) {
            if (Schema::hasColumn('couriers', 'reviewed_by')) {
                $table->dropConstrainedForeignId('reviewed_by');
            }
            foreach (['submitted_at', 'reviewed_at'] as $col) {
                if (Schema::hasColumn('couriers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
