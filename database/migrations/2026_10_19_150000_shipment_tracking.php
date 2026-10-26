<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shipment tracking on top of the existing parcels / parcel_transfers / rider job tables.
 *
 *  - orders.tracking_number: issued at checkout, so the buyer can track from "Order Placed";
 *    the parcel created at "Ready for Pickup" carries the same number.
 *  - shipment_events: append-only, structured tracking history (one row per verified event).
 *  - shipment_exceptions: damaged on arrival, wrong sorting center, delayed transfer… with resolution.
 *  - parcel_transfers: reference, dispatch / transporter / expected-by / delay / cancel columns, and
 *    status widened from an enum to a string (new statuses: in_transit, cancelled, exception).
 *  - parcels: who received / sorted it; parcel_pickups: when the rider set off ("arriving").
 *
 * Additive: existing rows are kept; existing orders and transfers get a reference backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'tracking_number')) {
            Schema::table('orders', fn (Blueprint $t) => $t->string('tracking_number', 30)->nullable()->unique()->after('id'));
            // Existing orders: the parcel's number when there is one, else a new one.
            foreach (DB::table('orders')->select('id')->orderBy('id')->get() as $o) {
                $number = DB::table('parcels')->where('order_id', $o->id)->orderBy('id')->value('tracking_number')
                    ?: 'ALVY-' . strtoupper(substr(md5(uniqid((string) $o->id, true)), 0, 10));
                if (DB::table('orders')->where('tracking_number', $number)->exists()) {
                    $number = 'ALVY-' . strtoupper(substr(md5(uniqid((string) $o->id, true)), 0, 10));
                }
                DB::table('orders')->where('id', $o->id)->update(['tracking_number' => $number]);
            }
        }

        if (! Schema::hasTable('shipment_events')) {
            Schema::create('shipment_events', function (Blueprint $t) {
                $t->id();
                $t->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
                $t->foreignId('parcel_id')->nullable()->constrained()->cascadeOnDelete();
                $t->string('event_type', 40);
                $t->string('from_status', 30)->nullable();
                $t->string('to_status', 30)->nullable();
                $t->foreignId('location_id')->nullable()->constrained('users')->nullOnDelete();      // sorting center where it happened
                $t->foreignId('origin_id')->nullable()->constrained('users')->nullOnDelete();        // transfer: from
                $t->foreignId('destination_id')->nullable()->constrained('users')->nullOnDelete();   // transfer: to
                $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $t->string('actor_role', 30)->nullable();
                $t->foreignId('transfer_id')->nullable()->constrained('parcel_transfers')->nullOnDelete();
                $t->foreignId('pickup_id')->nullable()->constrained('parcel_pickups')->nullOnDelete();
                $t->foreignId('delivery_id')->nullable()->constrained('parcel_deliveries')->nullOnDelete();
                $t->foreignId('corrects_event_id')->nullable()->constrained('shipment_events')->nullOnDelete();
                $t->text('note')->nullable();
                $t->json('meta')->nullable();
                $t->string('dedupe_key', 150)->nullable()->unique();
                $t->timestamp('occurred_at')->useCurrent();
                $t->timestamp('created_at')->nullable();
                $t->index(['parcel_id', 'occurred_at']);
                $t->index(['order_id', 'occurred_at']);
                $t->index('event_type');
            });
        }

        if (! Schema::hasTable('shipment_exceptions')) {
            Schema::create('shipment_exceptions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('parcel_id')->constrained()->cascadeOnDelete();
                $t->foreignId('transfer_id')->nullable()->constrained('parcel_transfers')->nullOnDelete();
                $t->string('type', 40);   // damaged_on_arrival, wrong_center, delayed_transfer, missing, other
                $t->text('details')->nullable();
                $t->foreignId('sorting_center_id')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
                $t->string('status', 20)->default('open');   // open, resolved
                $t->text('resolution')->nullable();
                $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('resolved_at')->nullable();
                $t->timestamps();
                $t->index(['status', 'type']);
                $t->index(['parcel_id', 'status']);
            });
        }

        Schema::table('parcel_transfers', function (Blueprint $t) {
            $t->string('status', 20)->default('pending')->change();
        });
        Schema::table('parcel_transfers', function (Blueprint $t) {
            if (! Schema::hasColumn('parcel_transfers', 'reference')) {
                $t->string('reference', 30)->nullable()->unique()->after('id');
                $t->timestamp('dispatched_at')->nullable();
                $t->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('transporter_rider_id')->nullable()->constrained('riders')->nullOnDelete();
                $t->timestamp('expected_by')->nullable();
                $t->timestamp('delayed_at')->nullable();
                $t->timestamp('cancelled_at')->nullable();
                $t->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $t->text('exception_reason')->nullable();
                $t->index(['parcel_id', 'status']);
                $t->index(['to_sorting_center_id', 'status']);
            }
        });
        foreach (DB::table('parcel_transfers')->whereNull('reference')->pluck('id') as $id) {
            DB::table('parcel_transfers')->where('id', $id)->update(['reference' => 'TRF-' . str_pad((string) $id, 8, '0', STR_PAD_LEFT)]);
        }

        Schema::table('parcels', function (Blueprint $t) {
            if (! Schema::hasColumn('parcels', 'received_by')) {
                $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('sorted_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('sorting_started_at')->nullable();
            }
        });
        Schema::table('parcel_pickups', function (Blueprint $t) {
            if (! Schema::hasColumn('parcel_pickups', 'en_route_at')) {
                $t->timestamp('en_route_at')->nullable();
            }
        });
        Schema::table('riders', function (Blueprint $t) {
            if (! Schema::hasColumn('riders', 'can_transport_transfers')) {
                // Hub-to-hub transport is a separate permission; last-mile riders don't get it automatically.
                $t->boolean('can_transport_transfers')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('riders', fn (Blueprint $t) => $t->dropColumn('can_transport_transfers'));
        Schema::table('parcel_pickups', fn (Blueprint $t) => $t->dropColumn('en_route_at'));
        Schema::table('parcels', function (Blueprint $t) {
            $t->dropConstrainedForeignId('received_by');
            $t->dropConstrainedForeignId('sorted_by');
            $t->dropColumn('sorting_started_at');
        });
        Schema::table('parcel_transfers', function (Blueprint $t) {
            $t->dropIndex(['parcel_id', 'status']);
            $t->dropIndex(['to_sorting_center_id', 'status']);
            $t->dropConstrainedForeignId('dispatched_by');
            $t->dropConstrainedForeignId('transporter_rider_id');
            $t->dropConstrainedForeignId('cancelled_by');
            $t->dropColumn(['reference', 'dispatched_at', 'expected_by', 'delayed_at', 'cancelled_at', 'exception_reason']);
        });
        Schema::dropIfExists('shipment_exceptions');
        Schema::dropIfExists('shipment_events');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('tracking_number'));
    }
};
