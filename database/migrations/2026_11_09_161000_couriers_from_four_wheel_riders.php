<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A rider with a 4-wheel vehicle is the company's Linehaul courier. Their courier record is made from the
 * rider record, which has no birthday and may lack some documents, so these columns may be empty.
 */
return new class extends Migration
{
    private const COLUMNS = ['contact_no', 'birthday', 'age', 'plate_number', 'or_cr_path', 'id_license_path'];

    public function up(): void
    {
        Schema::table('couriers', function (Blueprint $t) {
            $t->string('contact_no')->nullable()->change();
            $t->date('birthday')->nullable()->change();
            $t->unsignedTinyInteger('age')->nullable()->change();
            $t->string('plate_number')->nullable()->change();
            $t->string('or_cr_path')->nullable()->change();
            $t->string('id_license_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Restoring NOT NULL would fail on couriers made from riders; restore the backup instead if needed.
    }
};
