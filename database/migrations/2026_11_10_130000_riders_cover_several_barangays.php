<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One rider account can cover several barangays (riders.area_id stays as the main one).
 * Also: Juan Carlo Reyes's Banlic was a second rider record under the logistics account (no login);
 * Banlic moves to his own account (rider01) and that record is deactivated (kept for history).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_areas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
            $t->foreignId('area_id')->constrained('delivery_areas')->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['rider_id', 'area_id']);
        });

        $now = now();
        DB::table('rider_areas')->insertUsing(['rider_id', 'area_id', 'created_at', 'updated_at'],
            DB::table('riders')->whereNotNull('area_id')->select('id', 'area_id', DB::raw("'{$now}'"), DB::raw("'{$now}'")));

        // The Banlic duplicate: same company, same name, the record sits on the company's own account.
        $dup  = DB::table('riders')->where('id', 12)->first();
        $main = DB::table('riders')->where('id', 1)->first();
        if ($dup && $main && $dup->user_id == $dup->sorting_center_id && $dup->sorting_center_id == $main->sorting_center_id
            && strcasecmp(trim($dup->full_name), trim($main->full_name)) === 0 && $dup->area_id) {
            DB::table('rider_areas')->insertOrIgnore(['rider_id' => $main->id, 'area_id' => $dup->area_id, 'created_at' => $now, 'updated_at' => $now]);
            // Its open jobs could never be done (no login): they go to the same person's own account.
            DB::table('parcel_deliveries')->where('rider_id', $dup->id)->whereIn('status', \App\Models\ParcelDelivery::OPEN)
                ->update(['rider_id' => $main->id, 'updated_at' => $now]);
            DB::table('parcel_pickups')->where('rider_id', $dup->id)->whereIn('status', \App\Models\ParcelPickup::OPEN)
                ->update(['rider_id' => $main->id, 'updated_at' => $now]);
            DB::table('riders')->where('id', $dup->id)->update(['is_active' => false, 'is_online' => false, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_areas');
    }
};
