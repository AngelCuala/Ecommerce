<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Linehaul trips between sorting centers (they replace SC Transfers): a trip can go to a hub of another
 * logistics company (the hub nearest the buyer). That company accepts the trip before it departs, both
 * companies track it and message each other on it, and on arrival the destination receives the parcels,
 * which then belong to it and can be sorted there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('linehaul_trips', function (Blueprint $t) {
            // The company that receives the trip (the destination hub's company; the same company for its own hubs).
            $t->foreignId('destination_sorting_center_id')->nullable()->after('destination_hub_id')->constrained('users')->nullOnDelete();
            // Another company's acceptance before departure: pending / accepted / rejected (null: own hub, no acceptance needed).
            $t->string('handover_status', 20)->nullable()->after('destination_sorting_center_id')->index();
            $t->timestamp('handover_responded_at')->nullable()->after('handover_status');
            $t->foreignId('handover_responded_by')->nullable()->after('handover_responded_at')->constrained('users')->nullOnDelete();
            $t->string('handover_note', 300)->nullable()->after('handover_responded_by');
        });

        Schema::create('linehaul_trip_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('linehaul_trip_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();   // the sorting center that wrote it
            $t->text('body');
            $t->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linehaul_trip_messages');
        Schema::table('linehaul_trips', function (Blueprint $t) {
            $t->dropConstrainedForeignId('handover_responded_by');
            $t->dropConstrainedForeignId('destination_sorting_center_id');
            $t->dropColumn(['handover_status', 'handover_responded_at', 'handover_note']);
        });
    }
};
