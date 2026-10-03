<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make the book-specific columns `language` and `format` nullable, so
     * general shop products (which have no book language/format) can be saved.
     * These were missed when author/isbn/publisher/publication_year were made
     * nullable earlier.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return; // Raw ALTER below is MySQL-specific; other drivers not used here.
        }

        if (Schema::hasColumn('books', 'language')) {
            DB::statement("ALTER TABLE `books` MODIFY `language` VARCHAR(255) NULL DEFAULT 'English'");
        }
        if (Schema::hasColumn('books', 'format')) {
            // Was an ENUM('Paperback','Hardcover','eBook','Other'); widen to a
            // nullable string so non-book products aren't forced into a value.
            DB::statement("ALTER TABLE `books` MODIFY `format` VARCHAR(255) NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        if (Schema::hasColumn('books', 'language')) {
            DB::statement("ALTER TABLE `books` MODIFY `language` VARCHAR(255) NOT NULL DEFAULT 'English'");
        }
        if (Schema::hasColumn('books', 'format')) {
            DB::statement("ALTER TABLE `books` MODIFY `format` VARCHAR(255) NOT NULL DEFAULT 'Paperback'");
        }
    }
};
