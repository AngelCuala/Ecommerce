<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rider modules (R1–R7 test set), brought over from the mobile app to the web:
 *  - rider vehicle and documents (plate, license expiry, license and OR/CR files), Online/Offline
 *  - first-mile pickups (seller → sorting center), shared with the app's API (parcel_pickups)
 *  - job offers that expire after 60 seconds, then go to the next rider
 *  - delivery proof (photo + 6-digit OTP), COD collected, attempt counter and Return to Sender
 *  - COD remittances the logistics company confirms
 */
return new class extends Migration
{
    public function up(): void
    {
        // The app's API may already have created this table on the shared database.
        if (! Schema::hasTable('parcel_pickups')) {
            Schema::create('parcel_pickups', function (Blueprint $t) {
                $t->id();
                $t->foreignId('parcel_id')->constrained('parcels')->cascadeOnDelete();
                $t->foreignId('rider_id')->constrained('riders');
                $t->foreignId('sorting_center_id')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $t->string('status', 30)->default('assigned');
                $t->text('remarks')->nullable();
                $t->timestamp('picked_up_at')->nullable();
                $t->timestamp('received_at')->nullable();
                $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
                $t->index(['rider_id', 'status']);
            });
        }

        // New statuses ("Picked Up" by the rider, "Return to Sender") don't fit the old ENUMs.
        Schema::table('parcels', fn (Blueprint $t) => $t->string('status', 30)->default('pending_pickup')->change());
        Schema::table('parcel_deliveries', fn (Blueprint $t) => $t->string('status', 30)->default('assigned')->change());
        Schema::table('parcel_pickups', fn (Blueprint $t) => $t->string('status', 30)->default('assigned')->change());

        Schema::table('riders', function (Blueprint $t) {
            $t->string('plate_number', 20)->nullable();
            $t->date('license_expiry')->nullable();
            $t->string('license_path')->nullable();
            $t->string('or_cr_path')->nullable();
            $t->boolean('needs_reverification')->default(false);
            $t->boolean('is_online')->default(false);
            $t->timestamp('last_seen_at')->nullable();
        });

        Schema::table('parcel_deliveries', function (Blueprint $t) {
            $t->unsignedTinyInteger('attempt')->default(1);
            $t->string('failure_reason', 40)->nullable();
            $t->string('otp_code', 6)->nullable();
            $t->unsignedTinyInteger('otp_attempts')->default(0);
            $t->timestamp('otp_locked_until')->nullable();
            $t->string('pod_path')->nullable();
            $t->decimal('cod_amount', 12, 2)->default(0);      // to collect (0 when prepaid)
            $t->decimal('cod_collected', 12, 2)->default(0);
        });

        Schema::create('rider_job_offers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parcel_id')->constrained('parcels')->cascadeOnDelete();
            $t->string('kind', 10);                              // pickup | delivery
            $t->unsignedTinyInteger('attempt')->default(1);      // the delivery attempt this job is for
            $t->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
            $t->foreignId('sorting_center_id')->constrained('users')->cascadeOnDelete();
            $t->string('status', 20)->default('pending');        // pending | accepted | declined | expired | cancelled
            $t->timestamp('expires_at');
            $t->timestamp('responded_at')->nullable();
            $t->timestamps();
            $t->index(['parcel_id', 'kind', 'attempt', 'status']);
            $t->index(['rider_id', 'status']);
        });

        Schema::create('rider_remittances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
            $t->foreignId('sorting_center_id')->constrained('users')->cascadeOnDelete();
            $t->decimal('amount', 12, 2);
            $t->decimal('expected_amount', 12, 2);                 // COD balance when it was sent
            $t->string('status', 20)->default('pending');         // pending | confirmed
            $t->uuid('request_token')->unique();
            $t->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_remittances');
        Schema::dropIfExists('rider_job_offers');
        Schema::table('parcel_deliveries', fn (Blueprint $t) => $t->dropColumn(['attempt', 'failure_reason', 'otp_code', 'otp_attempts', 'otp_locked_until', 'pod_path', 'cod_amount', 'cod_collected']));
        Schema::table('riders', fn (Blueprint $t) => $t->dropColumn(['plate_number', 'license_expiry', 'license_path', 'or_cr_path', 'needs_reverification', 'is_online', 'last_seen_at']));
    }
};
