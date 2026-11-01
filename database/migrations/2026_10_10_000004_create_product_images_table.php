<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product photo gallery. The old book_images table was dropped during the
 * books → products rename, but the shop, product page and seller product
 * form still use the gallery — this brings the table back for products.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_images')) {
            return;
        }

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('path');
            $table->string('label')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
