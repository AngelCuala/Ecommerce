<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Result of the automatic "is this really an ID?" check on uploaded IDs:
 * passed / unchecked (the check couldn't run — admin should look closely).
 * Rejected uploads never get saved, so they don't appear here.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'seller_applications'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('id_check_status', 20)->nullable();
                $t->string('id_check_note')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'seller_applications'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['id_check_status', 'id_check_note']));
        }
    }
};
