<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The previous books→products rename migration checked hasColumn('…', 'product_id')
 * instead of hasColumn('…', 'book_id'), so the column renames never ran.
 * This migration finishes the job: rename book_id → product_id wherever it still exists.
 */
return new class extends Migration
{
    /** Tables and their old column name → new column name. */
    private array $targets = [
        'cart_items'        => ['book_id' => 'product_id'],
        'order_items'       => ['book_id' => 'product_id'],
        'product_variations'=> ['book_id' => 'product_id'],
        'product_images'    => ['book_id' => 'product_id'],
        'seller_violations' => ['book_id' => 'product_id'],
        'reviews'           => ['book_id' => 'product_id'],
    ];

    public function up(): void
    {
        foreach ($this->targets as $table => $renames) {
            if (! Schema::hasTable($table)) continue;

            foreach ($renames as $old => $new) {
                if (! Schema::hasColumn($table, $old)) continue;
                if (Schema::hasColumn($table, $new)) continue; // already renamed

                // Drop any FK on the old column before renaming (MariaDB requires this).
                $this->dropForeignIfExists($table, $old);

                Schema::table($table, function (Blueprint $bp) use ($old, $new) {
                    $bp->renameColumn($old, $new);
                });

                // Re-add the FK pointing at products.
                Schema::table($table, function (Blueprint $bp) use ($new) {
                    // nullOnDelete for optional references (reviews, violations), cascadeOnDelete for owned items.
                    $bp->foreign($new)->references('id')->on('products')->cascadeOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $table => $renames) {
            if (! Schema::hasTable($table)) continue;

            foreach ($renames as $old => $new) {
                if (! Schema::hasColumn($table, $new)) continue;
                if (Schema::hasColumn($table, $old)) continue;

                $this->dropForeignIfExists($table, $new);

                Schema::table($table, function (Blueprint $bp) use ($old, $new) {
                    $bp->renameColumn($new, $old);
                });

                Schema::table($table, function (Blueprint $bp) use ($old) {
                    $bp->foreign($old)->references('id')->on('products')->cascadeOnDelete();
                });
            }
        }
    }

    /** Drop a foreign key constraint by column name if it exists (MariaDB-compatible). */
    private function dropForeignIfExists(string $table, string $column): void
    {
        try {
            // Standard Laravel convention: tablename_columnname_foreign
            $constraintName = "{$table}_{$column}_foreign";
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY IF EXISTS `{$constraintName}`");
        } catch (\Throwable) {
            // If the constraint doesn't exist or has a different name, ignore.
        }
    }
};
