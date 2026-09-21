<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MigrateFromSQLiteSeeder extends Seeder
{
    public function run(): void
    {
        // ── Users ─────────────────────────────────────────────
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('users')->truncate();

        DB::table('users')->insert([
            [
                'id'                => 1,
                'name'              => 'Angel Cuala',
                'email'             => 'admin@alvy.com',
                'password'          => '$2y$12$lIc6XRVrqUed28y3W4fGmOgrGU1QVZZ7gEBtYrDwDMvjXAcz1LQAq',
                'role'              => 'admin',
                'approval_status'   => 'approved',
                'is_active'         => 1,
                'profile_photo_path'=> null,
                'created_at'        => '2026-09-13 15:53:41',
                'updated_at'        => '2026-09-15 14:25:03',
            ],
            [
                'id'                => 2,
                'name'              => 'Sorting Center',
                'email'             => 'sorting@alvy.com',
                'password'          => '$2y$12$XsKoWWR6mZwZQxm7PgEd5Of.zRhEoWRoarUfNhx3z4KDQlgsW9cWi',
                'role'              => 'sorting_center',
                'approval_status'   => 'approved',
                'is_active'         => 1,
                'profile_photo_path'=> null,
                'created_at'        => '2026-09-13 15:53:42',
                'updated_at'        => '2026-09-13 15:53:42',
            ],
            [
                'id'                => 3,
                'name'              => 'angel cuala',
                'email'             => 'angelcuala9009@gmail.com',
                'password'          => '$2y$12$MxOdDbalmfvFTKmU8/JWf.5EeIcih4k9sEc7RO5944EfBxc/xs.kW',
                'role'              => 'seller',
                'first_name'        => 'angel',
                'last_name'         => 'cuala',
                'username'          => 'angelcuala12',
                'sex'               => 'Female',
                'contact_no'        => '0912345678',
                'birthday'          => '2002-05-06',
                'age'               => 24,
                'province'          => 'Laguna',
                'municipality'      => 'Luisiana',
                'barangay'          => 'San Antonio',
                'street'            => 'tapat',
                'house_number'      => '91A',
                'valid_id_path'     => 'valid-ids/omDi8PfvUDDP60yjplSKtiSGMDcfAVrPzWZgHrcB.png',
                'approval_status'   => 'approved',
                'is_active'         => 1,
                'profile_photo_path'=> 'avatars/FFVW6Hdi5mNoroo1xOhEXV88wFj9bHxalW1vhJAq.jpg',
                'created_at'        => '2026-09-13 16:02:39',
                'updated_at'        => '2026-09-15 14:56:04',
            ],
            [
                'id'                => 4,
                'name'              => 'Sorting Center 1',
                'email'             => 'sc1@alvy.com',
                'password'          => '$2y$12$zzOAgRZpHXHaHilY9MKKOesPbqf4gMFcUOLNeuH7mMn43FsCOoqNC',
                'role'              => 'sorting_center',
                'approval_status'   => 'approved',
                'is_active'         => 1,
                'profile_photo_path'=> null,
                'created_at'        => '2026-09-16 00:41:01',
                'updated_at'        => '2026-09-16 00:41:01',
            ],
            [
                'id'                => 5,
                'name'              => 'Sorting Center 2',
                'email'             => 'sc2@alvy.com',
                'password'          => '$2y$12$YgUU2sOaWEj6K8eg.pBAL.VAZ.8yzJBGh0m.LM72z5c.kTAiH7zRG',
                'role'              => 'sorting_center',
                'approval_status'   => 'approved',
                'is_active'         => 1,
                'profile_photo_path'=> null,
                'created_at'        => '2026-09-16 00:41:02',
                'updated_at'        => '2026-09-16 00:41:02',
            ],
            [
                'id'                => 6,
                'name'              => 'Sorting Center 3',
                'email'             => 'sc3@alvy.com',
                'password'          => '$2y$12$lKJn.poKE8EDvFqjlKJL2eKHoEkC6PEy4K/XIREq05yRQDYpgQEW6',
                'role'              => 'sorting_center',
                'approval_status'   => 'approved',
                'is_active'         => 1,
                'profile_photo_path'=> null,
                'created_at'        => '2026-09-16 00:41:03',
                'updated_at'        => '2026-09-16 00:41:03',
            ],
        ]);
        $this->command->info('✓ 6 users inserted');

        // ── Couriers ──────────────────────────────────────────
        DB::table('couriers')->truncate();
        DB::table('couriers')->insert([
            [
                'id'           => 1, 'user_id' => 2,
                'last_name'    => 'Center',    'first_name' => 'Sorting',
                'sex'          => 'Male',      'contact_no' => '09123456789',
                'birthday'     => '1990-01-01','age'        => 36,
                'province'     => 'Metro Manila','municipality' => 'Quezon City','barangay' => 'Bagong Pag-asa',
                'vehicle_type' => 'N/A',       'plate_number' => 'N/A',
                'or_cr_path'   => '',          'id_license_path' => '',
                'status'       => 'approved',  'total_earnings' => 0,
                'created_at'   => '2026-09-15 12:46:07','updated_at' => '2026-09-15 12:46:07',
            ],
            [
                'id'           => 2, 'user_id' => 4,
                'last_name'    => 'Center One','first_name' => 'Sorting',
                'sex'          => 'Male',      'contact_no' => '09000000000',
                'birthday'     => '1990-01-01','age'        => 36,
                'province'     => 'Metro Manila','municipality' => 'Quezon City','barangay' => 'Bagong Pag-asa',
                'vehicle_type' => 'N/A',       'plate_number' => 'N/A',
                'or_cr_path'   => '',          'id_license_path' => '',
                'status'       => 'approved',  'total_earnings' => 0,
                'created_at'   => '2026-09-16 00:41:01','updated_at' => '2026-09-16 00:41:01',
            ],
            [
                'id'           => 3, 'user_id' => 5,
                'last_name'    => 'Center Two','first_name' => 'Sorting',
                'sex'          => 'Male',      'contact_no' => '09000000000',
                'birthday'     => '1990-01-01','age'        => 36,
                'province'     => 'Metro Manila','municipality' => 'Makati City','barangay' => 'Bel-Air',
                'vehicle_type' => 'N/A',       'plate_number' => 'N/A',
                'or_cr_path'   => '',          'id_license_path' => '',
                'status'       => 'approved',  'total_earnings' => 0,
                'created_at'   => '2026-09-16 00:41:02','updated_at' => '2026-09-16 00:41:02',
            ],
            [
                'id'           => 4, 'user_id' => 6,
                'last_name'    => 'Center Three','first_name' => 'Sorting',
                'sex'          => 'Male',      'contact_no' => '09000000000',
                'birthday'     => '1990-01-01','age'        => 36,
                'province'     => 'Metro Manila','municipality' => 'Pasig City','barangay' => 'Kapitolyo',
                'vehicle_type' => 'N/A',       'plate_number' => 'N/A',
                'or_cr_path'   => '',          'id_license_path' => '',
                'status'       => 'approved',  'total_earnings' => 0,
                'created_at'   => '2026-09-16 00:41:03','updated_at' => '2026-09-16 00:41:03',
            ],
        ]);
        $this->command->info('✓ 4 couriers inserted');

        // ── Categories ────────────────────────────────────────
        DB::table('categories')->truncate();
        $cats = [
            'Pet Supplies','Kids & Baby','Electronics & Gadgets',
            "Women's Apparel",'Sports & Outdoors','Home & Garden',
            "Men's Apparel",'Health & Beauty','Books & Media',
            'Food & Gourmet','Furniture & Office','Jewelry & Watches',
        ];
        foreach ($cats as $i => $name) {
            DB::table('categories')->insert([
                'id'         => $i + 1,
                'name'       => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $this->command->info('✓ 12 categories inserted');

        // ── Seller Application ────────────────────────────────
        DB::table('seller_applications')->truncate();
        DB::table('seller_applications')->insert([
            'id'                           => 1,
            'user_id'                      => 3,
            'full_name'                    => 'angel cuala',
            'first_name'                   => 'angel',
            'last_name'                    => 'cuala',
            'sex'                          => 'Male',
            'birthday'                     => '2003-06-14',
            'age'                          => 23,
            'shop_name'                    => 'Lucy',
            'business_name'               => 'lucy',
            'line_of_business'             => 'Kids & Baby',
            'phone'                        => '0123456789',
            'address'                      => 'tapat, 91A, San Antonio, Luisiana, Laguna',
            'province'                     => 'Laguna',
            'municipality'                 => 'Luisiana',
            'barangay'                     => 'San Antonio',
            'street'                       => 'tapat',
            'house_number'                 => '91A',
            'government_id_path'           => 'seller-ids/LPcQC0zw5RPyd4B6vapzWBDCyiXfMinisSOxXmWv.png',
            'business_permit_path'         => 'seller-permits/YGliZThhbjxp31rL6nX2Y3ZgsSx6NJRlB5qLHvcW.png',
            'description'                  => 'kids supply',
            'status'                       => 'approved',
            'shop_name_changes_this_month' => 0,
            'shop_name_last_changed_at'    => null,
            'created_at'                   => '2026-09-13 16:40:39',
            'updated_at'                   => '2026-09-13 16:41:51',
        ]);
        $this->command->info('✓ 1 seller application inserted');

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('');
        $this->command->info('All data transferred from SQLite → MySQL successfully!');
    }
}
