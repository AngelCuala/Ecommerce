<?php

namespace Database\Seeders;

use App\Models\DeliveryArea;
use Illuminate\Database\Seeder;

class DeliveryAreaSeeder extends Seeder
{
    public function run(): void
    {
        $areas = [
            ['name' => 'Tanauan City',  'code' => 'TAN'],
            ['name' => 'Sto. Tomas',    'code' => 'STO'],
            ['name' => 'Lipa City',     'code' => 'LIP'],
            ['name' => 'Batangas City', 'code' => 'BAT'],
        ];

        foreach ($areas as $area) {
            DeliveryArea::firstOrCreate(['code' => $area['code']], $area);
        }

        $this->command->info('Delivery areas seeded: ' . implode(', ', array_column($areas, 'code')));
    }
}
