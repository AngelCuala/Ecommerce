<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class FixSortingCenterRoleSeeder extends Seeder
{
    public function run(): void
    {
        // Find by email (note the double-r typo is intentional — that's what was set)
        $user = User::where('email', 'sorrtingcenter@gmail.com')->first();

        if ($user) {
            $old = $user->role;
            $user->role     = 'admin';
            $user->password = Hash::make('sort123');
            $user->save();
            $this->command->info("Fixed: {$user->email} | role: {$old} → admin");
        } else {
            // Create fresh
            User::create([
                'name'     => 'Sorting Center',
                'email'    => 'sorrtingcenter@gmail.com',
                'password' => Hash::make('sort123'),
                'role'     => 'admin',
            ]);
            $this->command->info('Created sorting center admin account.');
        }

        // Also ensure no other account with that email exists with wrong role
        $this->command->info('Done. Login: sorrtingcenter@gmail.com / sort123');
    }
}
