<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Break shipping_address into structured fields
            $table->string('full_name')->nullable()->after('user_id');
            $table->string('phone')->nullable()->after('full_name');
            $table->string('email')->nullable()->after('phone');
            $table->string('address_line')->nullable()->after('email');
            $table->string('city')->nullable()->after('address_line');
            $table->string('province')->nullable()->after('city');
            $table->string('zip_code')->nullable()->after('province');

            // Payment status
            $table->enum('payment_status', ['Pending', 'Paid', 'Failed'])
                  ->default('Pending')->after('payment_method');

            // Subtotal & shipping breakdown
            $table->decimal('subtotal', 10, 2)->default(0)->after('total_price');
            $table->decimal('shipping_fee', 8, 2)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'full_name','phone','email',
                'address_line','city','province','zip_code',
                'payment_status','subtotal','shipping_fee',
            ]);
        });
    }
};
