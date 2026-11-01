<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One-time login code (OTP) emailed on an approved account's first login.
 * The code itself is stored hashed; email_verified_at is set once it is verified.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('otp_code')->nullable()->after('email_verified_at');
            $table->timestamp('otp_expires_at')->nullable()->after('otp_code');
            $table->timestamp('otp_sent_at')->nullable()->after('otp_expires_at');
            $table->unsignedTinyInteger('otp_attempts')->default(0)->after('otp_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['otp_code', 'otp_expires_at', 'otp_sent_at', 'otp_attempts']);
        });
    }
};
