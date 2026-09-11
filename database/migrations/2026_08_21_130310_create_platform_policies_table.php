<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_policies', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();        // e.g. 'terms_of_service', 'privacy_policy'
            $table->string('title');
            $table->longText('content');
            $table->foreignId('updated_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_policies');
    }
};
