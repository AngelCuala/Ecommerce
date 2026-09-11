<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('book_id')->nullable()->constrained('books')->nullOnDelete();

            $table->enum('type', [
                'wrong_category',       // Product in wrong category
                'prohibited_product',   // Prohibited / illegal item
                'inappropriate_content',// Offensive images/text
                'misleading_info',      // False product info
                'other',
            ]);

            $table->enum('action', [
                'warning',              // Warning issued
                'product_removed',      // Product taken down
                'account_suspended',    // Seller suspended
                'account_deactivated',  // Seller deactivated
            ]);

            $table->text('note');                       // Admin explanation
            $table->boolean('acknowledged')->default(false); // Seller read it
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_violations');
    }
};
