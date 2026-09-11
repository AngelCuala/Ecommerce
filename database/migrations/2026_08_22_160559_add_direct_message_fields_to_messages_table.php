<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Make order_id nullable so direct messages don't need an order
            $table->foreignId('order_id')->nullable()->change();
            // Conversation thread key: "minId_maxId" of the two participants
            $table->string('thread_key', 30)->nullable()->after('order_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable(false)->change();
            $table->dropColumn('thread_key');
        });
    }
};
