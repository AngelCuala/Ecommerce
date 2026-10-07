<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class LogisticsActivityLogsSeeder extends Seeder
{
    public function run(): void
    {
        $logisticsUsers = User::whereIn('email', [
            'logistics@alvy.com',
            'operations@alvy.com',
            'sc-alpha@alvy.com',
            'sc-beta@alvy.com'
        ])->get();

        if ($logisticsUsers->isEmpty()) {
            $this->command->warn('No logistics users found. Run LogisticsUsersSeeder first.');
            return;
        }

        $activities = [
            // Logistics admin activities
            ['type' => 'admin_login', 'description' => 'Logistics Manager signed in', 'details' => 'Maria Santos signed in to logistics dashboard.'],
            ['type' => 'parcel_status_update', 'description' => 'Parcel status updated', 'details' => 'Parcel ALVY-ABC123 status changed to delivered.'],
            ['type' => 'rider_approved', 'description' => 'Rider application approved', 'details' => 'Mike Santos rider application approved by logistics manager.'],
            ['type' => 'delivery_area_created', 'description' => 'New delivery area created', 'details' => 'Makati CBD delivery area created and assigned to SC Alpha.'],
            ['type' => 'bulk_parcel_sort', 'description' => 'Bulk parcel sorting', 'details' => '15 parcels sorted and assigned to delivery areas.'],
            
            // Operations activities
            ['type' => 'operations_review', 'description' => 'Daily operations review', 'details' => 'Operations supervisor reviewed daily delivery metrics.'],
            ['type' => 'rider_assignment', 'description' => 'Rider assignments updated', 'details' => 'Jenny Cruz assigned to 8 new deliveries in Makati area.'],
            ['type' => 'transfer_initiated', 'description' => 'Inter-SC transfer initiated', 'details' => 'Transfer of 5 parcels initiated from SC Alpha to SC Beta.'],
            
            // Sorting center activities
            ['type' => 'sc_login', 'description' => 'Sorting center staff signed in', 'details' => 'SC Alpha staff Ana Rodriguez signed in.'],
            ['type' => 'parcel_received', 'description' => 'Parcels received for sorting', 'details' => 'Batch of 12 parcels received at SC Alpha from various sellers.'],
            ['type' => 'pickup_approved', 'description' => 'Pickup request approved', 'details' => 'Pickup approved for parcel ALVY-XYZ789 from BookStore Plus.'],
            ['type' => 'delivery_failed', 'description' => 'Delivery attempt failed', 'details' => 'Delivery failed for ALVY-DEF456 - recipient not available.'],
            ['type' => 'parcel_returned', 'description' => 'Parcel returned to seller', 'details' => 'Parcel ALVY-GHI321 returned after 3 failed delivery attempts.'],
            
            // System activities
            ['type' => 'system_maintenance', 'description' => 'System maintenance performed', 'details' => 'Logistics database optimized and backup created.'],
            ['type' => 'report_generated', 'description' => 'Monthly report generated', 'details' => 'September logistics performance report generated.'],
            ['type' => 'area_reassignment', 'description' => 'Delivery area reassigned', 'details' => 'Pasig West area reassigned from Robert Tan to Lisa Garcia.'],
        ];

        foreach ($activities as $activityData) {
            ActivityLog::create([
                'action' => $activityData['type'],
                'action_label' => $activityData['description'], 
                'description' => $activityData['details'],
                'admin_id' => $logisticsUsers->random()->id,
                'admin_name' => $logisticsUsers->random()->name,
                'status' => 'success',
                'created_at' => now()->subDays(rand(0, 30))->subHours(rand(0, 23)),
                'updated_at' => now()->subDays(rand(0, 30))->subHours(rand(0, 23)),
            ]);
        }

        $this->command->info('Created ' . count($activities) . ' logistics activity log entries.');
    }
}
