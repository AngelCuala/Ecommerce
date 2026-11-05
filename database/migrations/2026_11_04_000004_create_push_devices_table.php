<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phones signed in to the ALVY Shop app, for push notifications (Firebase Cloud Messaging): every in-app
 * notification is also sent to the user's phones (App\Services\FcmPush). A device goes on logout.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_devices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('token', 512)->unique();          // the FCM registration token of the phone
            $t->string('platform', 20)->default('android');
            $t->unsignedBigInteger('access_token_id')->nullable()->index();   // the app login it belongs to
            $t->timestamp('last_seen_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_devices');
    }
};
