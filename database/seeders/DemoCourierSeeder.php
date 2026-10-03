<?php

namespace Database\Seeders;

use App\Models\Courier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoCourierSeeder extends Seeder
{
    /**
     * Creates a ready-to-use, approved courier account for the courier portal.
     *
     * Login:  courier@alvy.com  /  courier123
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'courier@alvy.com'],
            [
                'name'            => 'Juan Dela Cruz',
                'first_name'      => 'Juan',
                'last_name'       => 'Dela Cruz',
                'username'        => 'courier_juan',
                'password'        => Hash::make('courier123'),
                'role'            => 'courier',
                'approval_status' => 'approved',
                'sex'             => 'Male',
                'contact_no'      => '09171234567',
                'birthday'        => '1995-05-20',
                'age'             => 31,
                'province'        => 'Metro Manila',
                'municipality'    => 'Quezon City',
                'barangay'        => 'Bagong Pag-asa',
                'street'          => 'Mabuhay St.',
                'house_number'    => '12',
            ]
        );

        Courier::updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_name'    => 'Juan',
                'last_name'     => 'Dela Cruz',
                'middle_initial'=> 'D',
                'sex'           => 'Male',
                'contact_no'    => '09171234567',
                'birthday'      => '1995-05-20',
                'age'           => 31,
                'province'      => 'Metro Manila',
                'municipality'  => 'Quezon City',
                'barangay'      => 'Bagong Pag-asa',
                'street'        => 'Mabuhay St.',
                'vehicle_type'  => 'Motorcycle',
                'plate_number'  => 'ABC 1234',
                'or_cr_path'    => '',
                'id_license_path' => '',
                'status'        => 'approved',
                'total_earnings'=> 0,
                'submitted_at'  => now(),
                'reviewed_at'   => now(),
            ]
        );

        $this->command->info('✓ Courier account ready — login: courier@alvy.com / courier123');
    }
}
