<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared rules from the newer test workbooks (A12, A31, A34):
 *  - reviews and return requests take up to 5 photos
 *  - a suspended rider's not-yet-picked-up jobs go back to the Unassigned queue
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', fn (Blueprint $t) => $t->json('photo_paths')->nullable());
        Schema::table('return_requests', fn (Blueprint $t) => $t->json('evidence_paths')->nullable());
        Schema::table('parcels', fn (Blueprint $t) => $t->timestamp('unassigned_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('parcels', fn (Blueprint $t) => $t->dropColumn('unassigned_at'));
        Schema::table('return_requests', fn (Blueprint $t) => $t->dropColumn('evidence_paths'));
        Schema::table('reviews', fn (Blueprint $t) => $t->dropColumn('photo_paths'));
    }
};
