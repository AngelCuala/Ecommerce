<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            if (! Schema::hasColumn('books', 'product_code'))  $table->string('product_code')->nullable()->unique()->after('id');
            if (! Schema::hasColumn('books', 'brand'))         $table->string('brand')->nullable()->after('author');
            if (! Schema::hasColumn('books', 'subcategory'))   $table->string('subcategory')->nullable()->after('category_id');
            if (! Schema::hasColumn('books', 'sale_price'))    $table->decimal('sale_price', 10, 2)->nullable()->after('price');
            if (! Schema::hasColumn('books', 'status'))        $table->string('status')->default('active')->after('availability'); // draft|active|inactive|out_of_stock
            if (! Schema::hasColumn('books', 'specs'))         $table->json('specs')->nullable()->after('description');
            if (! Schema::hasColumn('books', 'video_path'))    $table->string('video_path')->nullable()->after('image');

            // Shipping
            if (! Schema::hasColumn('books', 'weight_kg'))     $table->decimal('weight_kg', 8, 2)->nullable();
            if (! Schema::hasColumn('books', 'length_cm'))     $table->decimal('length_cm', 8, 2)->nullable();
            if (! Schema::hasColumn('books', 'width_cm'))      $table->decimal('width_cm', 8, 2)->nullable();
            if (! Schema::hasColumn('books', 'height_cm'))     $table->decimal('height_cm', 8, 2)->nullable();
            if (! Schema::hasColumn('books', 'address_id')) {
                $table->foreignId('address_id')->nullable()->constrained('user_addresses')->nullOnDelete();
            }
        });

        // Make book-specific columns optional so general shop products don't require them.
        // (SQLite ignores change() on some columns; MySQL applies it. Guarded via try.)
        try {
            Schema::table('books', function (Blueprint $table) {
                $table->string('author')->nullable()->change();
                $table->string('isbn')->nullable()->change();
                $table->string('publisher')->nullable()->change();
                $table->year('publication_year')->nullable()->change();
            });
        } catch (\Throwable $e) {
            // doctrine/dbal may be unavailable; not fatal — validation is relaxed in controller.
        }
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            foreach (['product_code','brand','subcategory','sale_price','status','specs','video_path','weight_kg','length_cm','width_cm','height_cm'] as $col) {
                if (Schema::hasColumn('books', $col)) $table->dropColumn($col);
            }
            if (Schema::hasColumn('books', 'address_id')) {
                $table->dropConstrainedForeignId('address_id');
            }
        });
    }
};
