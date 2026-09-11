<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seller_applications', function (Blueprint $table) {
            $table->string('last_name')->nullable()->after('user_id');
            $table->string('first_name')->nullable()->after('last_name');
            $table->string('middle_initial', 5)->nullable()->after('first_name');
            $table->enum('sex', ['Male','Female','Other'])->nullable()->after('middle_initial');
            $table->date('birthday')->nullable()->after('sex');
            $table->unsignedTinyInteger('age')->nullable()->after('birthday');
            $table->string('province')->nullable()->after('address');
            $table->string('municipality')->nullable()->after('province');
            $table->string('barangay')->nullable()->after('municipality');
            $table->string('street')->nullable()->after('barangay');
            $table->string('house_number')->nullable()->after('street');
            $table->string('business_name')->nullable()->after('shop_name');
            $table->string('line_of_business')->nullable()->after('business_name');
            $table->string('business_permit_path')->nullable()->after('government_id_path');
        });
    }

    public function down(): void
    {
        Schema::table('seller_applications', function (Blueprint $table) {
            $table->dropColumn([
                'last_name','first_name','middle_initial','sex','birthday','age',
                'province','municipality','barangay','street','house_number',
                'business_name','line_of_business','business_permit_path',
            ]);
        });
    }
};
