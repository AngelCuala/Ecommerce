<?php

namespace Database\Seeders;

use App\Models\Courier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SortingCenterAccountSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'sorting@alvy.com'],
            [
                'name'            => 'Sorting Center',
                'password'        => Hash::make('sorting123'),
                'role'            => 'sorting_center',
                'approval_status' => 'approved',
            ]
        );

        // Ensure role is correct even if account already existed
        if ($user->role !== 'sorting_center') {
            $user->update(['role' => 'sorting_center', 'approval_status' => 'approved']);
        }

        // Create courier record if missing
        Courier::firstOrCreate(
            ['user_id' => $user->id],
            [
                'last_name'       => 'Center',
                'first_name'      => 'Sorting',
                'sex'             => 'Male',
                'contact_no'      => '09123456789',
                'birthday'        => '1990-01-01',
                'age'             => 36,
                'province'        => 'Metro Manila',
                'municipality'    => 'Quezon City',
                'barangay'        => 'Bagong Pag-asa',
                'vehicle_type'    => 'N/A',
                'plate_number'    => 'N/A',
                'or_cr_path'      => '',
                'id_license_path' => '',
                'status'          => 'approved',
            ]
        );

        $this->command->info('Sorting Center account ready.');
        $this->command->info('  Email:    sorting@alvy.com');
        $this->command->info('  Password: sorting123');
        $this->command->info('  Role:     sorting_center');
    }
}
