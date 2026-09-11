<?php

namespace Database\Seeders;

use App\Models\Courier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SortingCenterSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::create([
            'name'     => 'Sorting Center',
            'email'    => 'sorting@alvy.com',
            'password' => Hash::make('sorting123'),
            'role'     => 'courier',
        ]);

        Courier::create([
            'user_id'         => $user->id,
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
        ]);

        echo "Sorting center account created.\n";
        echo "Email:    sorting@alvy.com\n";
        echo "Password: sorting123\n";
    }
}
