<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admin_name')->nullable();   // snapshot in case the admin is later deleted
            $table->string('action');                   // machine key, e.g. seller_approved
            $table->string('action_label')->nullable(); // human label, e.g. "Seller approved"
            $table->string('subject_type')->nullable(); // e.g. User, SellerApplication, Report
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->nullable();        // success | failed | info
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['admin_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
