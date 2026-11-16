<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A rider keeps their pay out of the COD cash they hold and hands the logistics company only the rest:
 * pay_kept is the pay taken from the cash in this remittance (the remitted amount + pay_kept = the COD it covers).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rider_remittances', function (Blueprint $t) {
            $t->decimal('pay_kept', 10, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('rider_remittances', function (Blueprint $t) {
            $t->dropColumn('pay_kept');
        });
    }
};
