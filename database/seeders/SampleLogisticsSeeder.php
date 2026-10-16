<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\DeliveryArea;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\ParcelPickup;
use App\Models\Product;
use App\Models\Rider;
use App\Models\RiderRemittance;
use App\Models\SellerApplication;
use App\Models\SortingCenterApplication;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * One sample logistics company for manual testing of the L1–L8 pages (php artisan db:seed --class=SampleLogisticsSeeder):
 * Bilis Hatid Express with 4 riders (2 Online, 1 Suspended, 1 Pending), a sample seller and buyer, and
 * parcels in every shipment status, COD collections and remittances (one short by ₱50).
 * Runs once: it stops if the sample account already exists.
 */
class SampleLogisticsSeeder extends Seeder
{
    public const EMAIL    = 'bilishatid.sample@example.com';
    public const PASSWORD = 'Hatid@2026';

    private User $sc, $seller, $buyer;
    private Rider $pedro, $mark;
    private DeliveryArea $central, $commonwealth;
    private Product $basket;
    private UserAddress $address;

    public function run(): void
    {
        if (User::where('email', self::EMAIL)->exists()) {
            $this->command?->warn('Sample logistics account already exists — nothing added.');
            return;
        }

        Model::unguarded(fn () => DB::transaction(function () {
            $this->accounts();
            $this->parcels();
        }));

        $this->command?->info('Sample logistics: ' . self::EMAIL . ' / ' . self::PASSWORD);
    }

    private function user(string $role, string $email, string $name, string $password): User
    {
        return User::create(['name' => $name, 'email' => $email, 'password' => Hash::make($password), 'role' => $role,
            'approval_status' => 'approved', 'email_verified_at' => now(), 'phone' => '09170000010']);
    }

    private function accounts(): void
    {
        $this->sc = $this->user('sorting_center', self::EMAIL, 'Carlos Antonio Mendoza', self::PASSWORD);
        SortingCenterApplication::create([
            'user_id' => $this->sc->id, 'business_name' => 'Bilis Hatid Express Inc.', 'status' => 'approved',
            'last_name' => 'Mendoza', 'first_name' => 'Carlos Antonio', 'sex' => 'Male', 'birthday' => '1985-01-01', 'age' => 41,
            'contact_no' => '09170000010', 'region' => 'NCR', 'province' => 'Metro Manila', 'municipality' => 'Quezon City',
            'municipality_code' => '137404000', 'barangay' => 'Central', 'street' => 'EDSA', 'house_number' => '1',
            'zip_code' => '1100', 'address' => '1 EDSA, Central, Quezon City, Metro Manila',
            'government_id_path' => 'sample/id.jpg', 'business_permit_path' => 'sample/permit.pdf',
        ]);

        $this->central = DeliveryArea::create(['sorting_center_id' => $this->sc->id, 'name' => 'Central', 'code' => 'BH-QC-CENTRAL', 'municipality' => 'Quezon City']);
        $this->commonwealth = DeliveryArea::create(['sorting_center_id' => $this->sc->id, 'name' => 'Commonwealth', 'code' => 'BH-QC-COMMONWEALTH', 'municipality' => 'Quezon City']);

        $rider = function (string $email, string $name, string $password, string $vehicle, string $plate, DeliveryArea $area, array $extra = []) {
            $u = $this->user('courier', $email, $name, $password);
            return Rider::create($extra + ['user_id' => $u->id, 'sorting_center_id' => $this->sc->id, 'full_name' => $name, 'phone' => '09170000011',
                'vehicle_type' => $vehicle, 'plate_number' => $plate, 'area_id' => $area->id, 'license_number' => 'N01-23-456789',
                'license_expiry' => '2030-03-22', 'application_status' => 'approved', 'approved_at' => now(), 'approved_by' => $this->sc->id,
                'is_active' => true, 'is_online' => true, 'last_seen_at' => now()]);
        };
        $this->pedro = $rider('pedrojose.garcia@example.com', 'Pedro Jose Garcia', 'Rider@2026', 'Motorcycle', '123ABC', $this->commonwealth);
        $this->mark  = $rider('markanthony.bautista@example.com', 'Mark Anthony Bautista', 'Hatid#Mark26', 'Van', 'NBC1234', $this->central);
        $rider('rider.test01@example.com', 'Ramon Cruz', 'Rider@2026', 'Motorcycle', '456DEF', $this->central, ['is_active' => false, 'is_online' => false]);
        $rider('rider.test02@example.com', 'Rina Santos', 'Rider@2026', 'Motorcycle', '789GHI', $this->central,
            ['application_status' => 'pending', 'approved_at' => null, 'approved_by' => null, 'is_active' => false, 'is_online' => false]);

        $this->seller = $this->user('seller', 'mariaclara.santos@example.com', 'Maria Clara Santos', 'Tindahan@2026');
        SellerApplication::create(['user_id' => $this->seller->id, 'full_name' => 'Maria Clara Santos', 'shop_name' => 'Maria Clara Handicrafts',
            'phone' => '09170000001', 'region' => 'NCR', 'province' => 'Metro Manila', 'municipality' => 'Quezon City', 'municipality_code' => '137404000',
            'barangay' => 'Commonwealth', 'street' => '12 Tandang Sora Ave.', 'zip_code' => '1121',
            'address' => '12 Tandang Sora Ave., Commonwealth, Quezon City, Metro Manila, 1121',
            'government_id_path' => 'sample/id.jpg', 'status' => 'approved']);
        $category = Category::firstOrCreate(['name' => 'Home & Garden']);
        $this->basket = Product::create(['category_id' => $category->id, 'seller_id' => $this->seller->id, 'title' => 'Rattan Fruit Basket',
            'description' => 'Handwoven rattan fruit basket, 30 cm across.', 'price' => 450, 'stock' => 20, 'weight_kg' => 0.8,
            'status' => 'active', 'approval_status' => 'approved']);

        $this->buyer = $this->user('buyer', 'juanmiguel.delacruz@example.com', 'Juan Miguel Dela Cruz', 'Mamimili@2026');
        $this->address = UserAddress::create(['user_id' => $this->buyer->id, 'label' => 'Home', 'full_name' => 'Juan Miguel Dela Cruz',
            'phone' => '09171234567', 'address_line' => '45 Kalayaan Ave.', 'barangay' => 'Central', 'city' => 'Quezon City',
            'province' => 'Metro Manila', 'region' => 'NCR', 'zip' => '1100', 'country' => 'Philippines',
            'municipality_code' => '137404000', 'is_default' => true]);
    }

    /** A ₱500.00 COD order (1 × Rattan Fruit Basket ₱450 + ₱50 shipping) and its parcel at this company. */
    private function parcel(string $status, string $orderStatus, array $extra = [], ?\Carbon\Carbon $at = null): Parcel
    {
        $at ??= now();
        $a = $this->address;
        $order = Order::create([
            'user_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'address_id' => $a->id, 'checkout_group' => (string) Str::uuid(),
            'full_name' => $a->full_name, 'phone' => $a->phone, 'email' => $this->buyer->email,
            'address_line' => $a->address_line, 'barangay' => $a->barangay, 'city' => $a->city, 'province' => $a->province,
            'region' => $a->region, 'zip_code' => $a->zip, 'municipality_code' => $a->municipality_code,
            'shipping_address' => '45 Kalayaan Ave., Central, Quezon City, Metro Manila 1100',
            'subtotal' => 450, 'shipping_fee' => 50, 'shipping_weight_kg' => 1, 'total_price' => 500,
            'payment_method' => 'cod', 'payment_status' => $orderStatus === Order::DELIVERED ? 'Paid' : 'Unpaid',
            'status' => $orderStatus, 'accepted_at' => $at, 'packed_at' => $at,
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $this->basket->id, 'quantity' => 1, 'price' => 450,
            'commission_rate' => 5, 'commission_amount' => 22.5, 'seller_earning' => 427.5]);

        $parcel = Parcel::create($extra + [
            'tracking_number' => Parcel::generateTracking(), 'order_id' => $order->id, 'seller_id' => $this->seller->id,
            'pickup_address' => '12 Tandang Sora Ave., Commonwealth, Quezon City', 'dropoff_address' => $order->shipping_address,
            'destination_municipality' => 'Quezon City', 'destination_province' => 'Metro Manila',
            'receiver_name' => $a->full_name, 'receiver_phone' => $a->phone, 'weight_kg' => 0.8,
            'current_sorting_center_id' => $this->sc->id, 'area_id' => $this->central->id, 'status' => $status,
        ]);
        $parcel->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
        $order->forceFill(['created_at' => $at])->saveQuietly();

        return $parcel;
    }

    private function delivery(Parcel $parcel, Rider $rider, string $status, int $attempt = 1, array $extra = []): ParcelDelivery
    {
        return ParcelDelivery::create($extra + ['parcel_id' => $parcel->id, 'rider_id' => $rider->id, 'area_id' => $parcel->area_id,
            'assigned_by' => $this->sc->id, 'status' => $status, 'attempt' => $attempt, 'cod_amount' => 500]);
    }

    private function parcels(): void
    {
        $now = now();

        // Pending: waiting for the company to confirm the pickup, then waiting for a rider.
        $this->parcel('pending_pickup', Order::PROCESSING);
        $this->parcel('pickup_approved', Order::PROCESSING, ['verified_by' => $this->sc->id, 'verified_at' => $now]);
        $this->parcel('pickup_approved', Order::PROCESSING, ['verified_by' => $this->sc->id, 'verified_at' => $now]);

        // A pickup given to Pedro but not collected yet (can be reassigned).
        $p = $this->parcel('pickup_approved', Order::PROCESSING, ['verified_by' => $this->sc->id, 'verified_at' => $now]);
        ParcelPickup::create(['parcel_id' => $p->id, 'rider_id' => $this->pedro->id, 'sorting_center_id' => $this->sc->id, 'assigned_by' => $this->sc->id, 'status' => 'assigned']);

        // Picked up by Pedro, on the way to the hub (scan it at Incoming Parcels).
        $p = $this->parcel('in_transit_to_hub', Order::SHIPPED);
        ParcelPickup::create(['parcel_id' => $p->id, 'rider_id' => $this->pedro->id, 'sorting_center_id' => $this->sc->id, 'status' => 'picked_up', 'picked_up_at' => $now]);

        // At the hub, to sort; and two sorted, ready for a delivery rider.
        $this->parcel('picked_up', Order::SHIPPED, ['received_at' => $now]);
        $this->parcel('sorted', Order::SHIPPED, ['received_at' => $now, 'sorted_at' => $now]);
        $this->parcel('sorted', Order::SHIPPED, ['received_at' => $now, 'sorted_at' => $now]);

        // Delivery given to Mark, not started (can be reassigned); and one out for delivery with Pedro.
        $p = $this->parcel('assigned', Order::SHIPPED, ['received_at' => $now, 'sorted_at' => $now]);
        $this->delivery($p, $this->mark, 'assigned');
        $p = $this->parcel('in_transit', Order::SHIPPED, ['received_at' => $now, 'sorted_at' => $now]);
        $this->delivery($p, $this->pedro, 'out_for_delivery', 1, ['picked_up_at' => $now, 'otp_code' => (string) random_int(100000, 999999)]);

        // Delivered with COD: 3 by Pedro today (₱1,500), 1 by Mark yesterday (₱500).
        foreach ([[$this->pedro, $now], [$this->pedro, $now], [$this->pedro, $now], [$this->mark, $now->copy()->subDay()]] as [$rider, $at]) {
            $p = $this->parcel('delivered', Order::DELIVERED, ['received_at' => $at, 'sorted_at' => $at], $at);
            $this->delivery($p, $rider, 'delivered', 1, ['picked_up_at' => $at, 'delivered_at' => $at, 'cod_collected' => 500]);
            $p->order->forceFill(['delivered_at' => $at])->saveQuietly();
        }

        // Remittances: Mark remitted yesterday's ₱500 (confirmed); Pedro remitted ₱1,450 of ₱1,500 today (₱50 short, to confirm).
        RiderRemittance::create(['rider_id' => $this->mark->id, 'sorting_center_id' => $this->sc->id, 'amount' => 500, 'expected_amount' => 500,
            'status' => 'confirmed', 'confirmed_by' => $this->sc->id, 'confirmed_at' => $now->copy()->subDay(), 'request_token' => (string) Str::uuid()])
            ->forceFill(['created_at' => $now->copy()->subDay()])->saveQuietly();
        RiderRemittance::create(['rider_id' => $this->pedro->id, 'sorting_center_id' => $this->sc->id, 'amount' => 1450, 'expected_amount' => 1500,
            'status' => 'pending', 'request_token' => (string) Str::uuid()]);

        // A failed first attempt (reschedule it), and a parcel that failed 3 times (Return to Sender queue).
        $p = $this->parcel('failed', Order::SHIPPED, ['received_at' => $now, 'sorted_at' => $now, 'failure_reason' => 'Buyer not home']);
        $this->delivery($p, $this->mark, 'failed', 1, ['failure_reason' => 'Buyer not home', 'remarks' => 'Buyer not home']);

        $p = $this->parcel('return_to_sender', Order::SHIPPED, ['received_at' => $now, 'sorted_at' => $now, 'failure_reason' => 'Buyer not home']);
        foreach (['Buyer not home', 'Wrong address', 'Buyer not home'] as $i => $reason) {
            $this->delivery($p, $i === 1 ? $this->mark : $this->pedro, 'failed', $i + 1, ['failure_reason' => $reason, 'remarks' => $reason]);
        }

        // Marked Lost (order refunded).
        $this->parcel('lost', Order::REFUNDED, ['received_at' => $now, 'failure_reason' => 'Parcel missing after the hub-to-rider handover.']);
    }
}
