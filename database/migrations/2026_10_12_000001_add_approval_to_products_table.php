<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New products from sellers wait for an admin's approval before they appear in the shop.
 * Products that already exist stay live ("approved"). Editing an approved product does not
 * need a new approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('approval_status', 20)->default('approved')->after('status'); // pending | approved | rejected
            $table->text('approval_note')->nullable()->after('approval_status');          // why it was rejected
            $table->timestamp('approval_reviewed_at')->nullable()->after('approval_note');
            $table->foreignId('approval_reviewed_by')->nullable()->after('approval_reviewed_at')
                ->constrained('users')->nullOnDelete();
            $table->index('approval_status');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approval_reviewed_by');
            $table->dropIndex(['approval_status']);
            $table->dropColumn(['approval_status', 'approval_note', 'approval_reviewed_at']);
        });
    }
};
