<?php

namespace Tests\Feature\Rider;

use App\Models\Courier;
use App\Models\Delivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Courier dashboard "Total Earnings" = My Earnings "Total earned" (rider jobs + older courier jobs). */
class DashboardTotalEarningsTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        // Like the live riders: a company rider who also has an approved courier profile (so the dashboard opens).
        Courier::create([
            'user_id' => $this->rider1->user_id, 'last_name' => 'Rider', 'first_name' => 'One', 'sex' => 'Male',
            'contact_no' => '09181234568', 'birthday' => '1995-05-20', 'age' => 31, 'province' => 'Laguna',
            'municipality' => 'Calamba', 'barangay' => 'Real', 'vehicle_type' => 'Motorcycle',
            'plate_number' => 'ABC 1234', 'or_cr_path' => 'a', 'id_license_path' => 'b', 'status' => 'approved',
        ]);
    }

    private function totals(): array
    {
        $dash = $this->actingAs($this->rider1->user)->get(route('courier.dashboard'))->assertOk()->getContent();
        $earn = $this->actingAs($this->rider1->user)->get(route('courier.profit'))->assertOk()->getContent();
        preg_match('/₱([\d,.]+)<\/p>\s*<p class="cx-stat-label">Total Earnings</u', $dash, $d);
        preg_match('/data-earn="total">₱([\d,.]+)/u', $earn, $e);
        return [$d[1] ?? null, $e[1] ?? null];
    }

    public function test_delivered_rider_jobs_count_and_match_my_earnings(): void
    {
        // Paid: a collected pickup (₱30) and two deliveries (₱50 each).
        $pickup = $this->pickupFor($this->rider1);
        $pickup->forceFill(['status' => 'received', 'picked_up_at' => now()])->saveQuietly();
        $this->deliveryFor($this->rider1)->forceFill(['status' => 'delivered', 'delivered_at' => now(), 'cod_collected' => 900])->saveQuietly();
        $this->deliveryFor($this->rider1)->forceFill(['status' => 'delivered', 'delivered_at' => now()->subDays(40)])->saveQuietly();
        // Not paid: a failed attempt, a job still in progress, a pickup not collected, another rider's delivery.
        $this->deliveryFor($this->rider1)->forceFill(['status' => 'failed', 'failure_reason' => 'Nobody home'])->saveQuietly();
        $this->deliveryFor($this->rider1, 'out_for_delivery');
        $this->pickupFor($this->rider1);
        $this->deliveryFor($this->rider2)->forceFill(['status' => 'delivered', 'delivered_at' => now()])->saveQuietly();

        // ₱130, not the ₱900 COD the rider collected.
        $this->assertSame(['130.00', '130.00'], $this->totals());
    }

    public function test_older_courier_jobs_are_added_once(): void
    {
        $this->deliveryFor($this->rider1)->forceFill(['status' => 'delivered', 'delivered_at' => now()])->saveQuietly();   // ₱50
        $order = $this->parcel('delivered')->order;
        $courier = $this->rider1->user->courier;
        Delivery::create(['order_id' => $order->id, 'courier_id' => $courier->id, 'delivery_fee' => 60, 'status' => 'delivered', 'delivered_at' => now()]);
        Delivery::create(['order_id' => $order->id, 'courier_id' => $courier->id, 'delivery_fee' => 70, 'status' => 'in_transit']);   // not done yet

        $this->assertSame(['110.00', '110.00'], $this->totals());
    }

    public function test_no_jobs_shows_zero(): void
    {
        $this->assertSame(['0.00', '0.00'], $this->totals());
    }
}
