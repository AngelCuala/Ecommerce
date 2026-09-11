<?php

use Illuminate\Database\Migrations\Migration;

// This migration has been voided — the rejection_reason column is not
// used on the users table in this project. Seller applications use
// their own rejection_reason column in seller_applications table.
return new class extends Migration
{
    public function up(): void {}
    public function down(): void {}
};
