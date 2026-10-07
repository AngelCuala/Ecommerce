<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LogisticsUsersSeeder extends Seeder
{
    public function run(): void
    {
        $logisticsUsers = [
            [
                'name' => 'Logistics Manager',
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'email' => 'logistics@alvy.com',
                'password' => Hash::make('logistics123'),
                'role' => 'admin',
                'approval_status' => 'approved',
                'contact_no' => '09171234567',
                'province' => 'Metro Manila',
                'municipality' => 'Quezon City',
                'barangay' => 'Barangay 1',
                'street' => 'Logistics Center St.',
                'house_number' => '123',
                'bio' => 'Logistics operations manager overseeing parcel delivery and sorting operations.',
            ],
            [
                'name' => 'Operations Supervisor',
                'first_name' => 'Juan',
                'last_name' => 'Cruz',
                'email' => 'operations@alvy.com',
                'password' => Hash::make('operations123'),
                'role' => 'admin',
                'approval_status' => 'approved',
                'contact_no' => '09181234567',
                'province' => 'Metro Manila',
                'municipality' => 'Manila',
                'barangay' => 'Ermita',
                'street' => 'Operations Ave.',
                'house_number' => '456',
                'bio' => 'Operations supervisor managing daily logistics activities and rider coordination.',
            ],
            [
                'name' => 'Sorting Center Alpha',
                'first_name' => 'Ana',
                'last_name' => 'Rodriguez',
                'email' => 'sc-alpha@alvy.com',
                'password' => Hash::make('sorting123'),
                'role' => 'sorting_center',
                'approval_status' => 'approved',
                'contact_no' => '09191234567',
                'province' => 'Metro Manila',
                'municipality' => 'Makati',
                'barangay' => 'Poblacion',
                'street' => 'Sorting Center Blvd.',
                'house_number' => '789',
                'assigned_municipality' => 'Makati',
                'assigned_municipality_code' => '137602000',
                'assigned_province' => 'Metro Manila',
                'assigned_province_code' => '130000000',
                'bio' => 'Sorting center staff managing Makati area deliveries.',
            ],
            [
                'name' => 'Sorting Center Beta',
                'first_name' => 'Carlos',
                'last_name' => 'Mendoza',
                'email' => 'sc-beta@alvy.com',
                'password' => Hash::make('sorting123'),
                'role' => 'sorting_center',
                'approval_status' => 'approved',
                'contact_no' => '09201234567',
                'province' => 'Metro Manila',
                'municipality' => 'Pasig',
                'barangay' => 'Kapitolyo',
                'street' => 'Hub Center St.',
                'house_number' => '321',
                'assigned_municipality' => 'Pasig',
                'assigned_municipality_code' => '137603000',
                'assigned_province' => 'Metro Manila',
                'assigned_province_code' => '130000000',
                'bio' => 'Sorting center staff managing Pasig area deliveries.',
            ],
        ];

        foreach ($logisticsUsers as $userData) {
            User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        $this->command->info('Created ' . count($logisticsUsers) . ' logistics users.');
    }
}
