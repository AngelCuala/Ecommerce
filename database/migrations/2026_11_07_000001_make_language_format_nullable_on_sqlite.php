<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 2026_09_23_032202 made these optional with MySQL-only SQL, so on SQLite every product saved from the
 * seller's form failed (the form no longer sends them).
 */
return new class extends Migration
{
    /** SQLite rebuilds the table here; outside a transaction it can turn foreign keys off, so rows that point at products survive. */
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('language')->nullable()->change();
            $table->string('format')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Left optional: rows saved since may have no language or format.
    }
};
