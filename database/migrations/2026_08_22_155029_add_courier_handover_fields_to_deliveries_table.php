<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->string('courier_name')->nullable()->after('courier_id');
            $table->string('tracking_number')->nullable()->after('courier_name');
            $table->timestamp('pickup_scheduled_at')->nullable()->after('tracking_number');
            $table->timestamp('handed_over_at')->nullable()->after('pickup_scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropColumn(['courier_name', 'tracking_number', 'pickup_scheduled_at', 'handed_over_at']);
        });
    }
};
