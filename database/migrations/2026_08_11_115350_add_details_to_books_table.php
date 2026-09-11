<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            // Inventory
            $table->string('sku')->nullable()->unique()->after('stock');
            $table->enum('availability', ['in_stock', 'out_of_stock'])->default('in_stock')->after('sku');

            // Book details
            $table->string('edition')->nullable()->after('publication_year');   // e.g. "1st", "2nd"
            $table->string('language')->default('English')->after('edition');
            $table->unsignedInteger('pages')->nullable()->after('language');
            $table->enum('format', ['Paperback', 'Hardcover', 'eBook', 'Other'])
                  ->default('Paperback')->after('pages');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['sku', 'availability', 'edition', 'language', 'pages', 'format']);
        });
    }
};
