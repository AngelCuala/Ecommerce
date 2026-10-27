<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Riders and couriers made by a logistics company (the app's Add Rider / Add Courier): they start
 * "pending_activation" and set their own password from an emailed link (24 hours). A company has
 * hubs (HUB-QC, HUB-PSG…); a courier drives from a home hub. Couriers also keep their license,
 * DL codes, load capacity and NBI clearance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_hubs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sorting_center_id')->constrained('users')->cascadeOnDelete();
            $t->string('code', 20)->unique();
            $t->string('name', 100);
            $t->string('address', 255)->nullable();
            $t->timestamps();
        });

        Schema::create('account_activations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('token_hash', 64)->unique();
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->enum('approval_status', ['unverified', 'pending', 'approved', 'rejected', 'pending_activation'])->default('pending')->change();
        });
        Schema::table('riders', function (Blueprint $t) {
            $t->enum('application_status', ['pending', 'approved', 'rejected', 'pending_activation'])->default('pending')->change();
        });
        Schema::table('couriers', function (Blueprint $t) {
            $t->enum('status', ['pending', 'approved', 'rejected', 'suspended', 'pending_activation'])->default('pending')->change();
            $t->string('license_number', 20)->nullable()->after('plate_number');
            $t->string('license_type', 30)->nullable()->after('license_number');
            $t->string('dl_codes', 60)->nullable()->after('license_type');
            $t->date('license_expiry')->nullable()->after('dl_codes');
            $t->unsignedInteger('load_capacity_kg')->nullable()->after('license_expiry');
            $t->string('nbi_path')->nullable()->after('dti_permit_path');
            $t->foreignId('home_hub_id')->nullable()->after('sorting_center_id')->constrained('logistics_hubs')->nullOnDelete();
        });

        // Every logistics company starts with one hub (its own office); it can add more in the app.
        foreach (DB::table('users')->where('role', 'sorting_center')->get(['id', 'name']) as $sc) {
            $company = DB::table('sorting_center_applications')->where('user_id', $sc->id)->where('status', 'approved')->value('business_name') ?: $sc->name;
            DB::table('logistics_hubs')->insert([
                'sorting_center_id' => $sc->id, 'code' => 'HUB-' . $sc->id, 'name' => $company . ' Main Hub',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('couriers', function (Blueprint $t) {
            $t->dropConstrainedForeignId('home_hub_id');
            $t->dropColumn(['license_number', 'license_type', 'dl_codes', 'license_expiry', 'load_capacity_kg', 'nbi_path']);
        });
        DB::table('couriers')->where('status', 'pending_activation')->update(['status' => 'pending']);
        DB::table('riders')->where('application_status', 'pending_activation')->update(['application_status' => 'pending']);
        DB::table('users')->where('approval_status', 'pending_activation')->update(['approval_status' => 'pending']);
        Schema::table('couriers', fn (Blueprint $t) => $t->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending')->change());
        Schema::table('riders', fn (Blueprint $t) => $t->enum('application_status', ['pending', 'approved', 'rejected'])->default('pending')->change());
        Schema::table('users', fn (Blueprint $t) => $t->enum('approval_status', ['unverified', 'pending', 'approved', 'rejected'])->default('pending')->change());
        Schema::dropIfExists('account_activations');
        Schema::dropIfExists('logistics_hubs');
    }
};
