<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, drop the book_images table since it references books
        Schema::dropIfExists('book_images');
        
        // Rename books table to products
        Schema::rename('books', 'products');
        
        // Update foreign key references in other tables
        $this->updateForeignKeyReferences();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rename products back to books
        Schema::rename('products', 'books');
        
        // Recreate book_images table
        Schema::create('book_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('books')->cascadeOnDelete();
            $table->string('path');
            $table->string('label')->nullable();
            $table->tinyInteger('sort_order')->unsigned()->default(0);
            $table->timestamps();
        });
        
        // Revert foreign key references
        $this->revertForeignKeyReferences();
    }

    /**
     * Update foreign key column names from product_id to product_id
     */
    private function updateForeignKeyReferences(): void
    {
        // Update cart_items table
        if (Schema::hasColumn('cart_items', 'product_id')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->dropForeign(['product_id']);
                $table->renameColumn('product_id', 'product_id');
            });
            
            Schema::table('cart_items', function (Blueprint $table) {
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            });
        }

        // Update order_items table
        if (Schema::hasColumn('order_items', 'product_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['product_id']);
                $table->renameColumn('product_id', 'product_id');
            });
            
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            });
        }

        // Update product_variations table
        if (Schema::hasColumn('product_variations', 'product_id')) {
            Schema::table('product_variations', function (Blueprint $table) {
                $table->dropForeign(['product_id']);
                $table->renameColumn('product_id', 'product_id');
            });
            
            Schema::table('product_variations', function (Blueprint $table) {
                $table->foreign('product_id')->references('id')->on('products')->cascadeOnDelete();
            });
        }
    }

    /**
     * Revert foreign key column names from product_id back to product_id
     */
    private function revertForeignKeyReferences(): void
    {
        // Revert cart_items table
        if (Schema::hasColumn('cart_items', 'product_id')) {
            Schema::table('cart_items', function (Blueprint $table) {
                $table->dropForeign(['product_id']);
                $table->renameColumn('product_id', 'product_id');
            });
            
            Schema::table('cart_items', function (Blueprint $table) {
                $table->foreign('product_id')->references('id')->on('books')->cascadeOnDelete();
            });
        }

        // Revert order_items table
        if (Schema::hasColumn('order_items', 'product_id')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropForeign(['product_id']);
                $table->renameColumn('product_id', 'product_id');
            });
            
            Schema::table('order_items', function (Blueprint $table) {
                $table->foreign('product_id')->references('id')->on('books')->cascadeOnDelete();
            });
        }

        // Revert product_variations table
        if (Schema::hasColumn('product_variations', 'product_id')) {
            Schema::table('product_variations', function (Blueprint $table) {
                $table->dropForeign(['product_id']);
                $table->renameColumn('product_id', 'product_id');
            });
            
            Schema::table('product_variations', function (Blueprint $table) {
                $table->foreign('product_id')->references('id')->on('books')->cascadeOnDelete();
            });
        }
    }
};
