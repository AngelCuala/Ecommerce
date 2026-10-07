<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class LogisticsSeeder extends Seeder
{
    /**
     * Run logistics-related seeders in the correct order.
     */
    public function run(): void
    {
        $this->command->info('🚚 Starting Logistics System Seeding...');

        $this->call([
            LogisticsUsersSeeder::class,
            LogisticsDeliveryAreasSeeder::class,
            LogisticsRidersSeeder::class,
            LogisticsParcelsSeeder::class,
            LogisticsActivityLogsSeeder::class,
        ]);

        $this->command->info('✅ Logistics system seeding completed successfully!');
        $this->command->line('');
        $this->command->info('📋 Seeded Data Summary:');
        $this->command->line('• Logistics Admin Users: logistics@alvy.com, operations@alvy.com');
        $this->command->line('• Sorting Centers: sc-alpha@alvy.com (Makati), sc-beta@alvy.com (Pasig)');
        $this->command->line('• Delivery Areas: 6 areas across Makati and Pasig');
        $this->command->line('• Riders: 4 approved riders + 1 pending application');
        $this->command->line('• Sample Parcels: 20 parcels with various delivery statuses');
        $this->command->line('• Activity Logs: 16 sample logistics activity entries');
        $this->command->line('');
        $this->command->info('🔐 Default Password: respective role + "123" (e.g., logistics123, rider123)');
        $this->command->line('');
        $this->command->info('🌐 Access URLs:');
        $this->command->line('• Main Login: /login');
        $this->command->line('• Logistics Login: /logistics/login');
        $this->command->line('• Sorting Center Login: /sc/login');
    }
}
