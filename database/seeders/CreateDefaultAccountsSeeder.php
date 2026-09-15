<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CreateDefaultAccountsSeeder extends Seeder
{
    public function run(): void
    {
        // Admin account
        User::firstOrCreate(
            ['email' => 'admin@alvy.com'],
            [
                'name'            => 'Admin',
                'password'        => Hash::make('admin123'),
                'role'            => 'admin',
                'approval_status' => 'approved',
            ]
        );

        // Sorting Center account
        User::firstOrCreate(
            ['email' => 'sorting@alvy.com'],
            [
                'name'            => 'Sorting Center',
                'password'        => Hash::make('sorting123'),
                'role'            => 'sorting_center',
                'approval_status' => 'approved',
            ]
        );

        $this->command->info('Accounts ready:');
        $this->command->info('Admin          → admin@alvy.com       / admin123');
        $this->command->info('Sorting Center → sorting@alvy.com     / sorting123');
    }
}
