<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The seller's own GCash, set in Store Settings: buyers scan this QR at checkout, pay the store's amount,
 * and upload the receipt with its Ref No. (the seller checks it before accepting the order).
 *  - gcash_qr_path: the permanent GCash QR (public disk, gcash-qr/)
 *  - gcash_name / gcash_number: shown under the QR so buyers can check they pay the right account
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_applications', function (Blueprint $t) {
            $t->string('gcash_qr_path')->nullable()->after('banner_path');
            $t->string('gcash_name', 60)->nullable()->after('gcash_qr_path');
            $t->string('gcash_number', 11)->nullable()->after('gcash_name');
        });
    }

    public function down(): void
    {
        Schema::table('seller_applications', fn (Blueprint $t) => $t->dropColumn(['gcash_qr_path', 'gcash_name', 'gcash_number']));
    }
};
