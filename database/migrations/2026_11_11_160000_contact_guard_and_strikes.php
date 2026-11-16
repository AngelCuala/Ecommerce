<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keeping deals on ALVY (App\Support\ContactGuard):
 *  - messages.safety_flag: 'hidden' (contact details were hidden in the message) or 'reminder' (dealing elsewhere was
 *    mentioned); the chat shows a notice under that message;
 *  - contact_strikes: a seller who sent contact details in a buyer chat (3 in 90 days → an administrator reviews).
 *    Only what kind of detail it was is kept, never the number or address itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('safety_flag', 20)->nullable()->after('body');
        });

        Schema::create('contact_strikes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();          // the seller
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('thread', 60)->nullable();                                // order:12 / direct:3_9
            $table->string('kinds', 60);                                             // phone,email,link,social
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_strikes');
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('safety_flag');
        });
    }
};
