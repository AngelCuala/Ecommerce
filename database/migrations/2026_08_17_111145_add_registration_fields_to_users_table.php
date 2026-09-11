<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->after('name')->nullable();
            $table->string('last_name')->after('first_name')->nullable();
            $table->string('middle_initial', 5)->after('last_name')->nullable();
            $table->string('username')->after('middle_initial')->unique()->nullable();

            $table->enum('sex', ['Male', 'Female'])->after('username')->nullable();
            $table->string('contact_no', 20)->after('email')->nullable();

            $table->date('birthday')->after('contact_no')->nullable();
            $table->unsignedTinyInteger('age')->after('birthday')->nullable();

            $table->string('province')->after('age')->nullable();
            $table->string('municipality')->after('province')->nullable();
            $table->string('barangay')->after('municipality')->nullable();
            $table->string('street')->after('barangay')->nullable();
            $table->string('house_number')->after('street')->nullable();

            $table->string('valid_id_path')->after('house_number')->nullable();

            $table->enum('approval_status', ['pending', 'approved', 'rejected'])
                  ->default('pending')
                  ->after('valid_id_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'middle_initial', 'username',
                'sex', 'contact_no', 'birthday', 'age',
                'province', 'municipality', 'barangay', 'street', 'house_number',
                'valid_id_path', 'approval_status',
            ]);
        });
    }
};