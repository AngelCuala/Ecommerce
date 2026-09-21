<?php

namespace Database\Seeders;

use App\Models\Courier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SortingCenterMultiSeeder extends Seeder
{
    public function run(): void
    {
        $centers = [
            [
                'name'         => 'Sorting Center 1',
                'email'        => 'sc1@alvy.com',
                'password'     => 'sorting123',
                'courier_name' => ['first' => 'Sorting', 'last' => 'Center One'],
                'province'     => 'Metro Manila',
                'municipality' => 'Quezon City',
                'barangay'     => 'Bagong Pag-asa',
            ],
            [
                'name'         => 'Sorting Center 2',
                'email'        => 'sc2@alvy.com',
                'password'     => 'sorting456',
                'courier_name' => ['first' => 'Sorting', 'last' => 'Center Two'],
                'province'     => 'Metro Manila',
                'municipality' => 'Makati City',
                'barangay'     => 'Bel-Air',
            ],
            [
                'name'         => 'Sorting Center 3',
                'email'        => 'sc3@alvy.com',
                'password'     => 'sorting789',
                'courier_name' => ['first' => 'Sorting', 'last' => 'Center Three'],
                'province'     => 'Metro Manila',
                'municipality' => 'Pasig City',
                'barangay'     => 'Kapitolyo',
            ],
        ];

        foreach ($centers as $sc) {
            $user = User::firstOrCreate(
                ['email' => $sc['email']],
                [
                    'name'            => $sc['name'],
                    'password'        => Hash::make($sc['password']),
                    'role'            => 'sorting_center',
                    'approval_status' => 'approved',
                ]
            );

            // Ensure role is correct if user already existed
            if ($user->role !== 'sorting_center') {
                $user->update(['role' => 'sorting_center', 'approval_status' => 'approved']);
            }

            // Create courier record if missing
            Courier::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'last_name'       => $sc['courier_name']['last'],
                    'first_name'      => $sc['courier_name']['first'],
                    'sex'             => 'Male',
                    'contact_no'      => '09000000000',
                    'birthday'        => '1990-01-01',
                    'age'             => 36,
                    'province'        => $sc['province'],
                    'municipality'    => $sc['municipality'],
                    'barangay'        => $sc['barangay'],
                    'vehicle_type'    => 'N/A',
                    'plate_number'    => 'N/A',
                    'or_cr_path'      => '',
                    'id_license_path' => '',
                    'status'          => 'approved',
                ]
            );

            $this->command->info("✓ {$sc['name']} — {$sc['email']} / {$sc['password']}");
        }

        $this->command->info('');
        $this->command->info('Login at: /sc/login');
    }
}
