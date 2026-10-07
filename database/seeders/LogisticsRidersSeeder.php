<?php

namespace Database\Seeders;

use App\Models\DeliveryArea;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LogisticsRidersSeeder extends Seeder
{
    public function run(): void
    {
        // Get sorting centers and areas
        $scAlpha = User::where('email', 'sc-alpha@alvy.com')->first();
        $scBeta = User::where('email', 'sc-beta@alvy.com')->first();
        
        $makatiAreas = DeliveryArea::where('municipality', 'Makati')->get();
        $pasigAreas = DeliveryArea::where('municipality', 'Pasig')->get();

        if (!$scAlpha || !$scBeta || $makatiAreas->isEmpty() || $pasigAreas->isEmpty()) {
            $this->command->error('Please run LogisticsUsersSeeder and LogisticsDeliveryAreasSeeder first.');
            return;
        }

        // Create rider users first
        $riderUsers = [
            [
                'name' => 'Mike Santos',
                'first_name' => 'Mike',
                'last_name' => 'Santos',
                'email' => 'mike.rider@alvy.com',
                'password' => Hash::make('rider123'),
                'role' => 'courier',
                'approval_status' => 'approved',
                'contact_no' => '09171111111',
                'province' => 'Metro Manila',
                'municipality' => 'Makati',
                'barangay' => 'Poblacion',
                'street' => 'Rider St.',
                'house_number' => '101',
            ],
            [
                'name' => 'Jenny Cruz',
                'first_name' => 'Jenny',
                'last_name' => 'Cruz',
                'email' => 'jenny.rider@alvy.com',
                'password' => Hash::make('rider123'),
                'role' => 'courier',
                'approval_status' => 'approved',
                'contact_no' => '09172222222',
                'province' => 'Metro Manila',
                'municipality' => 'Makati',
                'barangay' => 'Bel-Air',
                'street' => 'Delivery Ave.',
                'house_number' => '102',
            ],
            [
                'name' => 'Robert Tan',
                'first_name' => 'Robert',
                'last_name' => 'Tan',
                'email' => 'robert.rider@alvy.com',
                'password' => Hash::make('rider123'),
                'role' => 'courier',
                'approval_status' => 'approved',
                'contact_no' => '09173333333',
                'province' => 'Metro Manila',
                'municipality' => 'Pasig',
                'barangay' => 'Kapitolyo',
                'street' => 'Courier Blvd.',
                'house_number' => '103',
            ],
            [
                'name' => 'Lisa Garcia',
                'first_name' => 'Lisa',
                'last_name' => 'Garcia',
                'email' => 'lisa.rider@alvy.com',
                'password' => Hash::make('rider123'),
                'role' => 'courier',
                'approval_status' => 'approved',
                'contact_no' => '09174444444',
                'province' => 'Metro Manila',
                'municipality' => 'Pasig',
                'barangay' => 'San Antonio',
                'street' => 'Express Lane',
                'house_number' => '104',
            ],
            [
                'name' => 'Danny Reyes',
                'first_name' => 'Danny',
                'last_name' => 'Reyes',
                'email' => 'danny.rider@alvy.com',
                'password' => Hash::make('rider123'),
                'role' => 'courier_pending',
                'approval_status' => 'pending',
                'contact_no' => '09175555555',
                'province' => 'Metro Manila',
                'municipality' => 'Makati',
                'barangay' => 'Magallanes',
                'street' => 'Pending St.',
                'house_number' => '105',
            ],
        ];

        // Create users
        $createdUsers = [];
        foreach ($riderUsers as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData
            );
            $createdUsers[] = $user;
        }

        // Create rider profiles
        $riders = [
            [
                'user_id' => $createdUsers[0]->id,
                'sorting_center_id' => $scAlpha->id,
                'full_name' => 'Mike Santos',
                'phone' => '09171111111',
                'vehicle_type' => 'Motorcycle',
                'license_number' => 'D12-34-567890',
                'area_id' => $makatiAreas->first()->id,
                'application_status' => 'approved',
                'approved_at' => now()->subDays(10),
                'approved_by' => $scAlpha->id,
                'is_active' => true,
            ],
            [
                'user_id' => $createdUsers[1]->id,
                'sorting_center_id' => $scAlpha->id,
                'full_name' => 'Jenny Cruz',
                'phone' => '09172222222',
                'vehicle_type' => 'Bicycle',
                'license_number' => 'D12-34-567891',
                'area_id' => $makatiAreas->skip(1)->first()->id,
                'application_status' => 'approved',
                'approved_at' => now()->subDays(8),
                'approved_by' => $scAlpha->id,
                'is_active' => true,
            ],
            [
                'user_id' => $createdUsers[2]->id,
                'sorting_center_id' => $scBeta->id,
                'full_name' => 'Robert Tan',
                'phone' => '09173333333',
                'vehicle_type' => 'Motorcycle',
                'license_number' => 'D12-34-567892',
                'area_id' => $pasigAreas->first()->id,
                'application_status' => 'approved',
                'approved_at' => now()->subDays(5),
                'approved_by' => $scBeta->id,
                'is_active' => true,
            ],
            [
                'user_id' => $createdUsers[3]->id,
                'sorting_center_id' => $scBeta->id,
                'full_name' => 'Lisa Garcia',
                'phone' => '09174444444',
                'vehicle_type' => 'Van',
                'license_number' => 'D12-34-567893',
                'area_id' => $pasigAreas->skip(1)->first()->id,
                'application_status' => 'approved',
                'approved_at' => now()->subDays(3),
                'approved_by' => $scBeta->id,
                'is_active' => true,
            ],
            [
                'user_id' => $createdUsers[4]->id,
                'sorting_center_id' => $scAlpha->id,
                'full_name' => 'Danny Reyes',
                'phone' => '09175555555',
                'vehicle_type' => 'Motorcycle',
                'license_number' => 'D12-34-567894',
                'area_id' => $makatiAreas->last()->id,
                'application_status' => 'pending',
                'is_active' => false,
            ],
        ];

        foreach ($riders as $riderData) {
            Rider::firstOrCreate(
                ['user_id' => $riderData['user_id']],
                $riderData
            );
        }

        $this->command->info('Created ' . count($riders) . ' riders with their user accounts.');
    }
}
