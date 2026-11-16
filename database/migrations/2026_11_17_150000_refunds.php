<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The money of an approved return, sent to the buyer (App\Services\Refunds):
 *   awaiting_details  the buyer has to say where to send it (GCash, Maya or a bank account)
 *   waiting_for_item  details in; sent as soon as the item is back with the seller
 *   ready_to_send     the item is back: an ALVY admin sends it now (urgent)
 *   sent              sent, with the transfer's reference number
 * An order paid online (card, GCash online, Maya) goes back the same way it was paid ("original"), without details.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('return_request_id')->nullable()->unique()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->decimal('amount', 10, 2);
            $t->string('status', 20)->index();
            $t->string('method', 10)->nullable();          // gcash | maya | bank | original
            $t->string('account_name', 100)->nullable();
            $t->text('account_number')->nullable();          // encrypted
            $t->string('bank_name', 60)->nullable();
            $t->timestamp('details_at')->nullable();
            $t->timestamp('item_back_at')->nullable();
            $t->timestamp('ready_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('reference', 40)->nullable();
            $t->string('admin_note', 300)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
