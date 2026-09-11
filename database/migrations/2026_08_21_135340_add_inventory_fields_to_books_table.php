<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->decimal('discount_percent', 5, 2)->default(0)->after('price');   // e.g. 15.00 = 15% off
            $table->string('voucher_code', 50)->nullable()->after('discount_percent');
            $table->boolean('is_archived')->default(false)->after('availability');
            $table->timestamp('archived_at')->nullable()->after('is_archived');
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            $table->dropColumn(['discount_percent', 'voucher_code', 'is_archived', 'archived_at']);
        });
    }
};
