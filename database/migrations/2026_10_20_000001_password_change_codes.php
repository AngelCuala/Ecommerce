<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A new password waits for the code emailed to the account before it is used. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('pending_password')->nullable();                // hash of the new password
            $t->string('password_otp')->nullable();                    // hashed 6-digit code
            $t->timestamp('password_otp_expires_at')->nullable();
            $t->unsignedTinyInteger('password_otp_attempts')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['pending_password', 'password_otp', 'password_otp_expires_at', 'password_otp_attempts']);
        });
    }
};
