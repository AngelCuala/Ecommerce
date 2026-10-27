<?php

namespace Tests\Feature\Rider;

use App\Models\ParcelPickup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Courier "My Earnings" from the rider's real jobs, and the rider profile on the Account page. */
class EarningsAndAccountPageTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        $this->online($this->rider1);
    }

    public function test_earnings_come_from_the_riders_pickups_and_deliveries(): void
    {
        // Today: one pickup (₱30) and one delivery (₱50). Last month: one delivery (₱50). One failed attempt (₱0).
        $pickup = $this->pickupFor($this->rider1);
        $pickup->forceFill(['status' => 'received', 'picked_up_at' => now()])->saveQuietly();
        $this->deliveryFor($this->rider1)->forceFill(['status' => 'delivered', 'delivered_at' => now()])->saveQuietly();
        $this->deliveryFor($this->rider1)->forceFill(['status' => 'delivered', 'delivered_at' => now()->subMonthNoOverflow()->startOfMonth()->addDays(3)])->saveQuietly();
        $this->deliveryFor($this->rider1)->forceFill(['status' => 'failed', 'failure_reason' => 'Nobody home'])->saveQuietly();
        // Still in progress: one delivery (₱50 when done) and one pickup not yet collected (₱30).
        $this->deliveryFor($this->rider1, 'assigned');
        $this->pickupFor($this->rider1);
        // Another rider's job does not count.
        $this->deliveryFor($this->rider2)->forceFill(['status' => 'delivered', 'delivered_at' => now()])->saveQuietly();

        $this->actingAs($this->rider1->user)->get(route('courier.profit'))->assertOk()
            ->assertSee('data-earn="total">₱130.00', false)
            ->assertSee('data-earn="today">₱80.00', false)
            ->assertSee('data-earn="deliveries">2', false)
            ->assertSee('data-earn="pickups">1', false)
            ->assertSee('data-earn="average">₱43.33', false)
            ->assertSee('data-earn="pending">₱80.00', false)
            ->assertSee('Bilis Hatid Express Inc.')
            ->assertSee($pickup->parcel->order->number())
            ->assertSee('Failed (Nobody home)')
            ->assertSee('+₱50.00')->assertSee('+₱30.00');
    }

    public function test_a_new_rider_sees_zero_and_the_empty_state(): void
    {
        $this->actingAs($this->rider1->user)->get(route('courier.profit'))->assertOk()
            ->assertSee('data-earn="total">₱0.00', false)
            ->assertSee('No jobs yet.');
    }

    public function test_account_has_the_rider_profile_and_the_sidebar_no_longer_lists_it(): void
    {
        $page = $this->actingAs($this->rider1->user)->get(route('courier.account'))->assertOk();
        $page->assertSee('id="rider-profile"', false)
            ->assertSee('Bilis Hatid Express Inc.')->assertSee('123ABC')->assertSee('N01-23-456789')->assertSee('03/22/2030')
            ->assertSee('action="' . route('courier.rider.profile.update') . '"', false)
            ->assertSee(route('courier.rider.document', [$this->rider1->id, 'license']), false)
            ->assertDontSee('>Rider Profile<', false);

        // Saving the vehicle form returns to the Account page.
        $this->actingAs($this->rider1->user)->from(route('courier.account'))->put(route('courier.rider.profile.update'), [
            'vehicle_type' => 'Motorcycle', 'plate_number' => '123ABC', 'license_number' => 'N01-23-999999', 'license_expiry' => '2031-01-01',
        ])->assertRedirect(route('courier.account') . '#rider-profile')->assertSessionHas('success', 'Profile saved.');
        $this->assertSame('N01-23-999999', $this->rider1->fresh()->license_number);
    }
}
