<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The rider's proof photo of the parcel at the seller, taken when the pickup is confirmed. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcel_pickups', function (Blueprint $t) {
            $t->string('proof_path')->nullable()->after('picked_up_at');
        });
    }

    public function down(): void
    {
        Schema::table('parcel_pickups', function (Blueprint $t) {
            $t->dropColumn('proof_path');
        });
    }
};
