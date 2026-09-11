<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UpdateSortingCenterSeeder extends Seeder
{
    public function run(): void
    {
        // Find by old email OR by role=admin with 'Sorting Center' name
        $user = User::where('email', 'sorting@alvy.com')
            ->orWhere('name', 'Sorting Center')
            ->first();

        if ($user) {
            $user->email    = 'sorrtingcenter@gmail.com';
            $user->password = Hash::make('sort123');
            $user->save();
            $this->command->info('Updated: ' . $user->email);
        } else {
            $this->command->warn('Sorting center account not found — creating it now.');
            User::create([
                'name'     => 'Sorting Center',
                'email'    => 'sorrtingcenter@gmail.com',
                'password' => Hash::make('sort123'),
                'role'     => 'admin',
            ]);
            $this->command->info('Created sorrtingcenter@gmail.com');
        }
    }
}
