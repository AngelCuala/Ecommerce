<?php

namespace Database\Seeders;

use App\Models\Courier;
use App\Models\DeliveryArea;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Parcel;
use App\Models\ParcelPickup;
use App\Models\ParcelTransfer;
use App\Models\Product;
use App\Models\Rider;
use App\Models\RiderJobOffer;
use App\Models\RiderRemittance;
use App\Models\User;
use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds, to the sample logistics company (SampleLogisticsSeeder), the statuses it doesn't have yet, so every
 * logistics and rider page has something to check (php artisan db:seed --class=SampleLogisticsAllStatusesSeeder):
 *
 * - parcels: Ready for Pickup, Pickup Rejected, Cancelled, Damaged, Returned to Seller, a Return to Sender given to Pedro,
 *   a second failed attempt; pickups At Hub and Cancelled; a Return to Sender delivery row;
 * - transfers: outgoing (waiting), incoming (to accept or reject), received and rejected;
 * - riders: job offers (waiting, declined, expired), a rejected rider application, a confirmed remittance;
 * - a sample direct courier (courier.sample@example.com / Kurier@2026) with deliveries Available, Accepted,
 *   Picked Up, In Transit, Delivered and Failed.
 *
 * Runs once: it stops if the sample courier already exists.
 */
class SampleLogisticsAllStatusesSeeder extends SampleLogisticsSeeder
{
    public const COURIER_EMAIL    = 'courier.sample@example.com';
    public const COURIER_PASSWORD = 'Kurier@2026';

    public function run(): void
    {
        if (! User::where('email', self::EMAIL)->exists()) {
            parent::run();
        }
        if (User::where('email', self::COURIER_EMAIL)->exists()) {
            $this->command?->warn('Sample statuses were already added — nothing added.');
            return;
        }

        Model::unguarded(fn () => DB::transaction(function () {
            $this->load();
            $this->moreParcels();
            $this->transfers();
            $this->riders();
            $this->directCourier();
        }));

        $this->command?->info('Every logistics / rider status added to ' . self::EMAIL . '. Direct courier: ' . self::COURIER_EMAIL . ' / ' . self::COURIER_PASSWORD);
    }

    /** The sample company's accounts, made by SampleLogisticsSeeder. */
    private function load(): void
    {
        $this->sc = User::where('email', self::EMAIL)->firstOrFail();
        $this->seller = User::where('email', 'mariaclara.santos@example.com')->firstOrFail();
        $this->buyer = User::where('email', 'juanmiguel.delacruz@example.com')->firstOrFail();
        $rider = fn (string $email) => Rider::where('sorting_center_id', $this->sc->id)->whereHas('user', fn ($q) => $q->where('email', $email))->firstOrFail();
        $this->pedro = $rider('pedrojose.garcia@example.com');
        $this->mark = $rider('markanthony.bautista@example.com');
        $this->central = DeliveryArea::where('sorting_center_id', $this->sc->id)->where('code', 'BH-QC-CENTRAL')->firstOrFail();
        $this->commonwealth = DeliveryArea::where('sorting_center_id', $this->sc->id)->where('code', 'BH-QC-COMMONWEALTH')->firstOrFail();
        $this->basket = Product::where('seller_id', $this->seller->id)->where('title', 'Rattan Fruit Basket')->firstOrFail();
        $this->address = UserAddress::where('user_id', $this->buyer->id)->where('is_default', true)->firstOrFail();
    }

    private function moreParcels(): void
    {
        $now = now();
        $hub = ['received_at' => $now, 'sorted_at' => $now];

        // A new pickup request waiting for the company (Ready for Pickup).
        $this->parcel('pending_pickup', Order::PROCESSING);

        // The company turned a pickup down.
        $this->parcel('pickup_rejected', Order::PROCESSING, ['verified_by' => $this->sc->id, 'verified_at' => $now,
            'failure_reason' => "The seller's pickup address is outside our service area."]);

        // Cancelled before pickup (the order was cancelled).
        $p = $this->parcel('cancelled', Order::CANCELLED);
        $p->order->forceFill(['cancelled_at' => $now, 'cancellation_reason' => 'Cancelled by the buyer before pickup.'])->saveQuietly();

        // Damaged at the hub (order refunded).
        $this->parcel('damaged', Order::REFUNDED, $hub + ['failure_reason' => 'Box crushed during sorting; the basket was broken.']);

        // A second failed attempt (one more try before Return to Sender).
        $p = $this->parcel('failed', Order::SHIPPED, $hub + ['failure_reason' => 'Wrong address']);
        $this->delivery($p, $this->mark, 'failed', 1, ['failure_reason' => 'Buyer not home', 'remarks' => 'Buyer not home']);
        $this->delivery($p, $this->pedro, 'failed', 2, ['failure_reason' => 'Wrong address', 'remarks' => 'Wrong address']);

        // Return to Sender given to Pedro: it shows in his Returns, to hand back to the seller.
        $p = $this->parcel('return_to_sender', Order::SHIPPED, $hub + ['failure_reason' => 'Refused',
            'rts_rider_id' => $this->pedro->id, 'rts_assigned_at' => $now]);
        foreach (['Buyer not home', 'Buyer not home', 'Refused'] as $i => $reason) {
            $this->delivery($p, $i === 1 ? $this->mark : $this->pedro, 'failed', $i + 1, ['failure_reason' => $reason, 'remarks' => $reason]);
        }

        // Returned to the seller, who confirmed receiving it; the last delivery row reads "Return to Sender".
        $p = $this->parcel('returned', Order::CANCELLED, $hub + ['failure_reason' => 'Wrong address',
            'rts_rider_id' => $this->mark->id, 'rts_assigned_at' => $now->copy()->subDays(2),
            'returned_at' => $now->copy()->subDay(), 'returned_received_at' => $now], $now->copy()->subDays(4));
        foreach (['Wrong address', 'Wrong address', 'Wrong address'] as $i => $reason) {
            $this->delivery($p, $this->mark, $i === 2 ? 'returned' : 'failed', $i + 1, ['failure_reason' => $reason, 'remarks' => $reason]);
        }

        // Pickups: one finished at the hub (At Hub), and one taken from Mark and given to Pedro (Cancelled → Assigned).
        $p = $this->parcel('picked_up', Order::SHIPPED, ['received_at' => $now, 'verified_by' => $this->sc->id, 'verified_at' => $now]);
        ParcelPickup::create(['parcel_id' => $p->id, 'rider_id' => $this->pedro->id, 'sorting_center_id' => $this->sc->id, 'assigned_by' => $this->sc->id,
            'status' => 'received', 'picked_up_at' => $now->copy()->subHour(), 'received_at' => $now, 'received_by' => $this->sc->id]);
        $p = $this->parcel('pickup_approved', Order::PROCESSING, ['verified_by' => $this->sc->id, 'verified_at' => $now]);
        ParcelPickup::create(['parcel_id' => $p->id, 'rider_id' => $this->mark->id, 'sorting_center_id' => $this->sc->id, 'assigned_by' => $this->sc->id,
            'status' => 'cancelled', 'remarks' => 'Reassigned to another rider.']);
        ParcelPickup::create(['parcel_id' => $p->id, 'rider_id' => $this->pedro->id, 'sorting_center_id' => $this->sc->id, 'assigned_by' => $this->sc->id, 'status' => 'assigned']);
    }

    private function transfers(): void
    {
        // Another hub that can receive parcels (it has a municipality assigned).
        $other = User::where('role', 'sorting_center')->whereKeyNot($this->sc->id)->get()->first(fn (User $u) => $u->hasAssignedMunicipality());
        if (! $other) {
            return;   // no second hub to transfer with
        }
        $now = now();
        $hub = ['received_at' => $now, 'sorted_at' => $now];
        $transfer = fn (Parcel $p, int $from, int $to, string $status, string $reason, array $extra = []) => ParcelTransfer::create($extra + [
            'parcel_id' => $p->id, 'from_sorting_center_id' => $from, 'to_sorting_center_id' => $to, 'initiated_by' => $from,
            'reason' => $reason, 'status' => $status]);

        // Outgoing, waiting for the other hub.
        $p = $this->parcel('sorted', Order::SHIPPED, $hub + ['transfer_status' => 'outgoing']);
        $transfer($p, $this->sc->id, $other->id, 'pending', 'The buyer moved: the address is in their area.');

        // Incoming from the other hub: accept or reject it on Transfers.
        $p = $this->parcel('sorted', Order::SHIPPED, $hub + ['transfer_status' => 'outgoing', 'current_sorting_center_id' => $other->id, 'area_id' => null]);
        $transfer($p, $other->id, $this->sc->id, 'pending', 'Closer to the buyer: Quezon City.');

        // History: one received here, one the other hub rejected (the parcel stayed here).
        $p = $this->parcel('picked_up', Order::SHIPPED, ['received_at' => $now, 'area_id' => null]);
        $transfer($p, $other->id, $this->sc->id, 'received', 'Wrong hub at pickup.', ['received_by' => $this->sc->id, 'received_at' => $now]);
        $p = $this->parcel('sorted', Order::SHIPPED, $hub);
        $transfer($p, $this->sc->id, $other->id, 'rejected', 'Overflow at our hub.', ['received_at' => $now]);
    }

    private function riders(): void
    {
        $now = now();

        // Job offers: Pedro has a pickup offer waiting; Mark a delivery offer; plus declined and expired ones.
        $pick = $this->parcel('pickup_approved', Order::PROCESSING, ['verified_by' => $this->sc->id, 'verified_at' => $now]);
        RiderJobOffer::create(['parcel_id' => $pick->id, 'kind' => RiderJobOffer::PICKUP, 'attempt' => 1, 'rider_id' => $this->pedro->id,
            'sorting_center_id' => $this->sc->id, 'status' => 'pending', 'expires_at' => $now->copy()->addHours(6)]);
        $drop = $this->parcel('sorted', Order::SHIPPED, ['received_at' => $now, 'sorted_at' => $now]);
        RiderJobOffer::create(['parcel_id' => $drop->id, 'kind' => RiderJobOffer::DELIVERY, 'attempt' => 1, 'rider_id' => $this->pedro->id,
            'sorting_center_id' => $this->sc->id, 'status' => 'declined', 'expires_at' => $now->copy()->subMinutes(20), 'responded_at' => $now->copy()->subMinutes(25)]);
        RiderJobOffer::create(['parcel_id' => $drop->id, 'kind' => RiderJobOffer::DELIVERY, 'attempt' => 1, 'rider_id' => $this->mark->id,
            'sorting_center_id' => $this->sc->id, 'status' => 'pending', 'expires_at' => $now->copy()->addHours(6)]);
        $old = $this->parcel('sorted', Order::SHIPPED, ['received_at' => $now, 'sorted_at' => $now]);
        RiderJobOffer::create(['parcel_id' => $old->id, 'kind' => RiderJobOffer::DELIVERY, 'attempt' => 1, 'rider_id' => $this->mark->id,
            'sorting_center_id' => $this->sc->id, 'status' => 'expired', 'expires_at' => $now->copy()->subHour()]);

        // A rider application the company turned down.
        $u = $this->user('courier', 'rider.test03@example.com', 'Rodel Aquino', 'Rider@2026');
        Rider::create(['user_id' => $u->id, 'sorting_center_id' => $this->sc->id, 'full_name' => 'Rodel Aquino', 'phone' => '09170000012',
            'vehicle_type' => 'Bicycle', 'plate_number' => 'N/A', 'area_id' => $this->central->id, 'license_number' => 'N01-23-111111',
            'license_expiry' => '2027-01-15', 'application_status' => 'rejected', 'rejection_reason' => 'The license photo is blurry. Please upload a clear copy.',
            'is_active' => false, 'is_online' => false]);

        // Mark: a second remittance, confirmed last week.
        RiderRemittance::create(['rider_id' => $this->mark->id, 'sorting_center_id' => $this->sc->id, 'amount' => 1000, 'expected_amount' => 1000,
            'status' => 'confirmed', 'confirmed_by' => $this->sc->id, 'confirmed_at' => $now->copy()->subWeek(), 'request_token' => (string) Str::uuid()])
            ->forceFill(['created_at' => $now->copy()->subWeek()])->saveQuietly();
    }

    /** An independent courier (not a company rider) with direct deliveries in every status. */
    private function directCourier(): void
    {
        $now = now();
        $u = $this->user('courier', self::COURIER_EMAIL, 'Mario Santos', self::COURIER_PASSWORD);
        $u->forceFill(['phone' => '09170000013', 'contact_no' => '09170000013'])->save();
        $courier = Courier::create(['user_id' => $u->id, 'sorting_center_id' => $this->sc->id, 'last_name' => 'Santos', 'first_name' => 'Mario',
            'sex' => 'Male', 'contact_no' => '09170000013', 'birthday' => '1995-05-20', 'age' => 31, 'province' => 'Metro Manila',
            'municipality' => 'Quezon City', 'barangay' => 'Central', 'street' => '8 Malakas St.', 'vehicle_type' => 'Motorcycle',
            'plate_number' => 'MSN1234', 'or_cr_path' => 'sample/orcr.jpg', 'id_license_path' => 'sample/license.jpg', 'status' => 'approved']);

        $deliveries = [
            ['available', Order::PROCESSING, []],
            ['available', Order::PROCESSING, []],
            ['accepted', Order::PROCESSING, ['accepted_at' => $now]],
            ['picked_up', Order::SHIPPED, ['accepted_at' => $now->copy()->subHours(2), 'picked_up_at' => $now->copy()->subHour()]],
            ['in_transit', Order::SHIPPED, ['accepted_at' => $now->copy()->subHours(3), 'picked_up_at' => $now->copy()->subHours(2)]],
            ['delivered', Order::DELIVERED, ['accepted_at' => $now->copy()->subDay(), 'picked_up_at' => $now->copy()->subDay(), 'delivered_at' => $now->copy()->subHours(20)]],
            ['delivered', Order::DELIVERED, ['accepted_at' => $now->copy()->subDays(3), 'picked_up_at' => $now->copy()->subDays(3), 'delivered_at' => $now->copy()->subDays(3)]],
            ['failed', Order::CANCELLED, ['accepted_at' => $now->copy()->subDays(2), 'notes' => 'Order cancelled by the seller.']],
        ];
        foreach ($deliveries as [$status, $orderStatus, $extra]) {
            $order = $this->order($orderStatus);
            if ($orderStatus === Order::DELIVERED) {
                $order->forceFill(['delivered_at' => $extra['delivered_at'], 'payment_status' => 'Paid'])->saveQuietly();
            }
            Delivery::create($extra + ['order_id' => $order->id, 'tracking_number' => 'DC-' . strtoupper(Str::random(8)), 'delivery_fee' => 50,
                'status' => $status, 'pickup_scheduled_at' => $now->copy()->addHours(2)]
                + ($status === 'available' ? [] : ['courier_id' => $courier->id, 'courier_name' => 'Mario Santos']));
        }
    }

    /** A ₱500.00 COD order with no company parcel (for the direct courier). */
    private function order(string $status): Order
    {
        $a = $this->address;
        $order = Order::create([
            'user_id' => $this->buyer->id, 'seller_id' => $this->seller->id, 'address_id' => $a->id, 'checkout_group' => (string) Str::uuid(),
            'full_name' => $a->full_name, 'phone' => $a->phone, 'email' => $this->buyer->email,
            'address_line' => $a->address_line, 'barangay' => $a->barangay, 'city' => $a->city, 'province' => $a->province,
            'region' => $a->region, 'zip_code' => $a->zip, 'municipality_code' => $a->municipality_code,
            'shipping_address' => '45 Kalayaan Ave., Central, Quezon City, Metro Manila 1100',
            'subtotal' => 450, 'shipping_fee' => 50, 'shipping_weight_kg' => 1, 'total_price' => 500,
            'payment_method' => 'cod', 'payment_status' => 'Unpaid', 'status' => $status, 'accepted_at' => now(), 'packed_at' => now(),
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $this->basket->id, 'quantity' => 1, 'price' => 450,
            'commission_rate' => 5, 'commission_amount' => 22.5, 'seller_earning' => 427.5]);
        return $order;
    }
}
