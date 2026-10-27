<?php

use App\Models\Parcel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Waybill number printed on each parcel's label (Shopee-style, e.g. PH261019123456789).
     * Unique across all parcels; existing parcels get one now.
     */
    public function up(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->string('waybill_number', 20)->nullable()->after('tracking_number');
        });

        DB::table('parcels')->whereNull('waybill_number')->orderBy('id')->select('id', 'created_at')->get()
            ->each(function ($p) {
                DB::table('parcels')->where('id', $p->id)->update([
                    'waybill_number' => Parcel::generateWaybill($p->created_at ? \Carbon\Carbon::parse($p->created_at) : null),
                ]);
            });

        Schema::table('parcels', function (Blueprint $table) {
            $table->unique('waybill_number');
        });
    }

    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->dropUnique(['waybill_number']);
            $table->dropColumn('waybill_number');
        });
    }
};
