<?php

namespace Database\Seeders;

use App\Models\DeliveryArea;
use App\Models\User;
use Illuminate\Database\Seeder;

class LogisticsDeliveryAreasSeeder extends Seeder
{
    public function run(): void
    {
        // Get sorting centers
        $scAlpha = User::where('email', 'sc-alpha@alvy.com')->first();
        $scBeta = User::where('email', 'sc-beta@alvy.com')->first();

        if (!$scAlpha || !$scBeta) {
            $this->command->error('Please run LogisticsUsersSeeder first to create sorting centers.');
            return;
        }

        $deliveryAreas = [
            // Makati areas (SC Alpha)
            [
                'name' => 'Makati CBD',
                'code' => 'MKT-CBD',
                'description' => 'Central Business District including Ayala Avenue and surrounding commercial areas',
                'sorting_center_id' => $scAlpha->id,
                'municipality' => 'Makati',
                'municipality_code' => '137602000',
                'barangay_code' => '137602001', // Poblacion
            ],
            [
                'name' => 'Makati Residential',
                'code' => 'MKT-RES',
                'description' => 'Residential areas including Bel-Air, San Lorenzo, and surrounding neighborhoods',
                'sorting_center_id' => $scAlpha->id,
                'municipality' => 'Makati',
                'municipality_code' => '137602000',
                'barangay_code' => '137602002', // Bel-Air
            ],
            [
                'name' => 'Makati North',
                'code' => 'MKT-NTH',
                'description' => 'Northern Makati areas including Rockwell and nearby districts',
                'sorting_center_id' => $scAlpha->id,
                'municipality' => 'Makati',
                'municipality_code' => '137602000',
                'barangay_code' => '137602003', // Rockwell area
            ],

            // Pasig areas (SC Beta)
            [
                'name' => 'Ortigas Center',
                'code' => 'PSG-ORT',
                'description' => 'Ortigas business district and surrounding commercial areas',
                'sorting_center_id' => $scBeta->id,
                'municipality' => 'Pasig',
                'municipality_code' => '137603000',
                'barangay_code' => '137603001', // Kapitolyo
            ],
            [
                'name' => 'Pasig Residential East',
                'code' => 'PSG-RES-E',
                'description' => 'Eastern residential areas including Antipolo boundary zones',
                'sorting_center_id' => $scBeta->id,
                'municipality' => 'Pasig',
                'municipality_code' => '137603000',
                'barangay_code' => '137603002', // Eastern areas
            ],
            [
                'name' => 'Pasig West',
                'code' => 'PSG-WST',
                'description' => 'Western Pasig areas near Mandaluyong boundary',
                'sorting_center_id' => $scBeta->id,
                'municipality' => 'Pasig',
                'municipality_code' => '137603000',
                'barangay_code' => '137603003', // Western areas
            ],
        ];

        foreach ($deliveryAreas as $areaData) {
            DeliveryArea::firstOrCreate(
                ['code' => $areaData['code']],
                $areaData
            );
        }

        $this->command->info('Created ' . count($deliveryAreas) . ' delivery areas.');
    }
}