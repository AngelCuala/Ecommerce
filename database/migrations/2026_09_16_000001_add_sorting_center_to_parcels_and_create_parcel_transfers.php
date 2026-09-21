<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Track which sorting center currently holds each parcel
        Schema::table('parcels', function (Blueprint $table) {
            $table->foreignId('current_sorting_center_id')
                ->nullable()
                ->after('area_id')
                ->constrained('users')
                ->nullOnDelete();

            // Add 'transferring' and 'transfer_received' to the status enum
            // SQLite doesn't support ALTER COLUMN on enums, so we add a separate column
            // for the transfer sub-status instead of modifying the enum.
            $table->string('transfer_status')->nullable()->after('status');
            // Values: null | 'outgoing' | 'incoming' | null (cleared when received)
        });

        // SC-to-SC transfer log
        Schema::create('parcel_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parcel_id')
                ->constrained('parcels')
                ->cascadeOnDelete();
            $table->foreignId('from_sorting_center_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('to_sorting_center_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('initiated_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('received_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'received', 'rejected'])
                ->default('pending');
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcel_transfers');
        Schema::table('parcels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_sorting_center_id');
            $table->dropColumn('transfer_status');
        });
    }
};
