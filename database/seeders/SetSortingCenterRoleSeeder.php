<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SetSortingCenterRoleSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'sorrtingcenter@gmail.com')->first();

        if ($user) {
            $user->role     = 'sorting_center';
            $user->password = Hash::make('sort123');
            $user->save();
            $this->command->info("Updated {$user->email} → role: sorting_center");
        } else {
            User::create([
                'name'     => 'Sorting Center',
                'email'    => 'sorrtingcenter@gmail.com',
                'password' => Hash::make('sort123'),
                'role'     => 'sorting_center',
            ]);
            $this->command->info('Created sorting center account with role: sorting_center');
        }
    }
}
