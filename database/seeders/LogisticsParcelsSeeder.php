<?php

namespace Database\Seeders;

use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Database\Seeder;

class LogisticsParcelsSeeder extends Seeder
{
    public function run(): void
    {
        // Get required data
        $sellers = User::where('role', 'seller')->take(3)->get();
        $buyers = User::where('role', 'buyer')->take(5)->get();
        $areas = DeliveryArea::all();
        $riders = Rider::where('application_status', 'approved')->get();

        if ($sellers->isEmpty() || $buyers->isEmpty() || $areas->isEmpty()) {
            $this->command->warn('Creating sample orders and users for parcels...');
            
            // Create sample sellers if none exist
            if ($sellers->isEmpty()) {
                for ($i = 1; $i <= 3; $i++) {
                    $seller = User::create([
                        'name' => "Seller $i",
                        'first_name' => 'Seller',
                        'last_name' => "$i",
                        'email' => "seller$i@example.com",
                        'password' => bcrypt('password'),
                        'role' => 'seller',
                        'approval_status' => 'approved',
                        'contact_no' => '0917000000' . $i,
                        'province' => 'Metro Manila',
                        'municipality' => 'Manila',
                        'barangay' => 'Sample Barangay',
                    ]);
                    $sellers->push($seller);
                }
            }

            // Create sample buyers if none exist
            if ($buyers->isEmpty()) {
                for ($i = 1; $i <= 5; $i++) {
                    $buyer = User::create([
                        'name' => "Buyer $i",
                        'first_name' => 'Buyer',
                        'last_name' => "$i",
                        'email' => "buyer$i@example.com",
                        'password' => bcrypt('password'),
                        'role' => 'buyer',
                        'approval_status' => 'approved',
                        'contact_no' => '0918000000' . $i,
                        'province' => 'Metro Manila',
                        'municipality' => $i <= 2 ? 'Makati' : 'Pasig',
                        'barangay' => 'Sample Barangay',
                    ]);
                    $buyers->push($buyer);
                }
            }
        }

        $parcels = [];
        $statuses = ['pending_pickup', 'pickup_approved', 'picked_up', 'sorted', 'assigned', 'in_transit', 'delivered'];

        for ($i = 1; $i <= 20; $i++) {
            $seller = $sellers->random();
            $buyer = $buyers->random();
            $area = $areas->random();
            
            // Create order first
            $order = Order::create([
                'user_id' => $buyer->id,
                'total_price' => rand(500, 5000) / 100, // Convert to decimal
                'shipping_fee' => rand(50, 200) / 100,   // Convert to decimal
                'subtotal' => rand(400, 4800) / 100,     // Convert to decimal
                'status' => 'Processing',
                'payment_status' => 'paid',
                'payment_method' => 'Credit Card',
                'shipping_address' => $buyer->address ?? 'Sample Address, ' . $buyer->municipality,
                'address_line' => $buyer->address ?? 'Sample Address',
                'city' => $buyer->municipality ?? 'Manila',
                'province' => $buyer->province ?? 'Metro Manila',
                'zip_code' => '1000',
                'full_name' => $buyer->name,
                'phone' => $buyer->contact_no ?? '09170000000',
                'email' => $buyer->email,
            ]);

            $status = $statuses[array_rand($statuses)];
            
            $parcel = [
                'tracking_number' => Parcel::generateTracking(),
                'order_id' => $order->id,
                'seller_id' => $seller->id,
                'pickup_address' => $seller->address ?? "Seller Address {$seller->id}",
                'dropoff_address' => $buyer->address ?? "Buyer Address {$buyer->id}",
                'destination_municipality' => $buyer->municipality ?? $area->municipality,
                'destination_province' => $buyer->province ?? 'Metro Manila',
                'receiver_name' => $buyer->name,
                'receiver_phone' => $buyer->contact_no ?? '09170000000',
                'weight_kg' => rand(1, 10) / 10, // 0.1 to 1.0 kg
                'size' => ['Small', 'Medium', 'Large'][rand(0, 2)],
                'notes' => "Sample parcel {$i} - handle with care",
                'area_id' => $area->id,
                'status' => $status,
                'current_sorting_center_id' => $area->sorting_center_id,
                'pickup_scheduled_at' => now()->addHours(rand(1, 48)),
            ];

            // Set timestamps based on status
            if (in_array($status, ['picked_up', 'sorted', 'assigned', 'in_transit', 'delivered'])) {
                $parcel['received_at'] = now()->subHours(rand(2, 24));
            }
            if (in_array($status, ['sorted', 'assigned', 'in_transit', 'delivered'])) {
                $parcel['sorted_at'] = now()->subHours(rand(1, 12));
            }

            $parcels[] = $parcel;
        }

        // Create parcels
        foreach ($parcels as $parcelData) {
            $parcel = Parcel::create($parcelData);

            // Create delivery assignments for parcels that are assigned or beyond
            if (in_array($parcel->status, ['assigned', 'in_transit', 'delivered']) && $riders->isNotEmpty()) {
                $rider = $riders->where('area_id', $parcel->area_id)->first() ?? $riders->random();
                
                ParcelDelivery::create([
                    'parcel_id' => $parcel->id,
                    'rider_id' => $rider->id,
                    'area_id' => $parcel->area_id,
                    'status' => match($parcel->status) {
                        'assigned' => 'assigned',
                        'in_transit' => 'out_for_delivery',
                        'delivered' => 'delivered',
                        default => 'assigned',
                    },
                    'remarks' => 'Assigned via logistics seeder',
                ]);
            }
        }

        $this->command->info('Created ' . count($parcels) . ' sample parcels with various statuses.');
    }
}
