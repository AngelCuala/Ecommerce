<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PAYMENTS_SPEC §3.1 step 5, §3.2: the hub remits the item price to ALVY with a report of each order. Per delivery the
 * hub owes the COD it holds minus the parcel's shipping fee (the 30/30/40 shipping stays with the hubs and riders);
 * for a prepaid order ALVY holds the money, so the line is minus the shipping fee (ALVY owes the hub). ALVY matches
 * each line to its order, records what it received, and flags any mismatch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hub_remittances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sorting_center_id')->constrained('users')->cascadeOnDelete();
            $t->decimal('amount', 12, 2);                 // net due to ALVY (negative: ALVY owes the hub)
            $t->string('status', 12)->default('submitted'); // submitted, confirmed, flagged
            $t->string('deposit_reference', 60)->nullable();
            $t->decimal('received_amount', 12, 2)->nullable();
            $t->string('note', 300)->nullable();          // why it was flagged
            $t->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();
            $t->index(['sorting_center_id', 'status']);
        });

        Schema::create('hub_remittance_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('hub_remittance_id')->constrained()->cascadeOnDelete();
            $t->foreignId('parcel_delivery_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('parcel_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('tracking_number', 40)->nullable();
            $t->boolean('cod')->default(true);
            $t->decimal('cod_expected', 12, 2)->default(0);    // the COD the order asked for
            $t->decimal('cod_collected', 12, 2)->default(0);
            $t->decimal('shipping_fee', 12, 2)->default(0);
            $t->decimal('amount_due', 12, 2);                  // cod_collected − shipping_fee (prepaid: − shipping_fee)
            $t->boolean('mismatch')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_remittance_items');
        Schema::dropIfExists('hub_remittances');
    }
};
