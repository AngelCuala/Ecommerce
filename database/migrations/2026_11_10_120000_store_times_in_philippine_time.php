<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The site now runs on Philippine time (config app.timezone = Asia/Manila) instead of UTC, so every page shows the
 * time people actually did things. Stored times were UTC: move each date-time column forward 8 hours (PH has no DST).
 * All columns of a table change in one UPDATE, so nothing is touched twice. Backup taken before running.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only the MySQL databases hold old UTC times (a fresh test database has nothing to move).
        if (DB::getDriverName() !== 'mysql') {
            return;
        }
        $columns = DB::select("SELECT table_name AS t, column_name AS c FROM information_schema.columns
            WHERE table_schema = DATABASE() AND data_type IN ('datetime', 'timestamp') AND table_name <> 'migrations'");

        foreach (collect($columns)->groupBy('t') as $table => $cols) {
            $set = $cols->map(fn ($x) => "`{$x->c}` = `{$x->c}` + INTERVAL 8 HOUR")->implode(', ');
            DB::statement("UPDATE `{$table}` SET {$set}");
        }
    }

    public function down(): void
    {
        // Restore storage/app/private/db-backups/*-before-manila-time.sql instead.
    }
};
