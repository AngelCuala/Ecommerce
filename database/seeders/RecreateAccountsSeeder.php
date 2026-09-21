<?php

namespace Database\Seeders;

use App\Models\Courier;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RecreateAccountsSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin ─────────────────────────────────────────────
        User::updateOrCreate(
            ['email' => 'admin@alvy.com'],
            [
                'name'            => 'Admin',
                'password'        => Hash::make('admin123'),
                'role'            => 'admin',
                'approval_status' => 'approved',
                'is_active'       => true,
            ]
        );
        $this->command->info('✓ Admin         → admin@alvy.com / admin123');

        // ── Sorting Centers ───────────────────────────────────
        $centers = [
            ['name'=>'Sorting Center',   'email'=>'sorting@alvy.com', 'password'=>'sorting123',
             'courier'=>['first'=>'Sorting','last'=>'Center',      'province'=>'Metro Manila','city'=>'Quezon City','barangay'=>'Bagong Pag-asa']],
            ['name'=>'Sorting Center 1', 'email'=>'sc1@alvy.com',     'password'=>'sorting123',
             'courier'=>['first'=>'Sorting','last'=>'Center One',   'province'=>'Metro Manila','city'=>'Quezon City','barangay'=>'Bagong Pag-asa']],
            ['name'=>'Sorting Center 2', 'email'=>'sc2@alvy.com',     'password'=>'sorting456',
             'courier'=>['first'=>'Sorting','last'=>'Center Two',   'province'=>'Metro Manila','city'=>'Makati City','barangay'=>'Bel-Air']],
            ['name'=>'Sorting Center 3', 'email'=>'sc3@alvy.com',     'password'=>'sorting789',
             'courier'=>['first'=>'Sorting','last'=>'Center Three', 'province'=>'Metro Manila','city'=>'Pasig City', 'barangay'=>'Kapitolyo']],
        ];

        foreach ($centers as $sc) {
            $user = User::updateOrCreate(
                ['email' => $sc['email']],
                [
                    'name'            => $sc['name'],
                    'password'        => Hash::make($sc['password']),
                    'role'            => 'sorting_center',
                    'approval_status' => 'approved',
                    'is_active'       => true,
                ]
            );

            Courier::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'first_name'      => $sc['courier']['first'],
                    'last_name'       => $sc['courier']['last'],
                    'sex'             => 'Male',
                    'contact_no'      => '09000000000',
                    'birthday'        => '1990-01-01',
                    'age'             => 36,
                    'province'        => $sc['courier']['province'],
                    'municipality'    => $sc['courier']['city'],
                    'barangay'        => $sc['courier']['barangay'],
                    'vehicle_type'    => 'N/A',
                    'plate_number'    => 'N/A',
                    'or_cr_path'      => '',
                    'id_license_path' => '',
                    'status'          => 'approved',
                ]
            );

            $this->command->info("✓ {$sc['name']} → {$sc['email']} / {$sc['password']}");
        }

        // ── Categories ────────────────────────────────────────
        $cats = [
            'Pet Supplies','Kids & Baby','Electronics & Gadgets',
            "Women's Apparel",'Sports & Outdoors','Home & Garden',
            "Men's Apparel",'Health & Beauty','Books & Media',
            'Food & Gourmet','Furniture & Office','Jewelry & Watches',
        ];
        foreach ($cats as $name) {
            \App\Models\Category::firstOrCreate(['name' => $name]);
        }
        $this->command->info('✓ 12 categories seeded');

        $this->command->info('');
        $this->command->info('All accounts ready. Login at /login or /sc/login');
    }
}
