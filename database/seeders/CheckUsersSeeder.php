<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class CheckUsersSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::whereIn('role', ['admin', 'courier', 'courier_pending'])
            ->orWhere('email', 'like', '%sorting%')
            ->orWhere('email', 'like', '%sorrt%')
            ->get(['id', 'name', 'email', 'role']);

        foreach ($users as $u) {
            $this->command->info("ID:{$u->id} | {$u->email} | role:{$u->role}");
        }

        if ($users->isEmpty()) {
            $this->command->warn('No admin/courier/sorting users found at all.');
        }
    }
}
