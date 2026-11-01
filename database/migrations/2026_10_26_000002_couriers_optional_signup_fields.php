<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A courier made by a logistics company (Add Courier) has no sex or home address on the form. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('couriers', function (Blueprint $t) {
            $t->enum('sex', ['Male', 'Female', 'Other'])->nullable()->change();
            $t->string('province')->nullable()->change();
            $t->string('municipality')->nullable()->change();
            $t->string('barangay')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Kept nullable: rows made by Add Courier have no values to restore.
    }
};
