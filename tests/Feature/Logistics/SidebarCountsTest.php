<?php

namespace Tests\Feature\Logistics;

use App\Models\RiderRemittance;
use App\Support\SortingCenterBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** The sorting center's sidebar shows how much work waits on each page. */
class SidebarCountsTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    private function sidebar()
    {
        return $this->actingAs($this->logistics1)->get(route('sc.dashboard'))->assertOk();
    }

    public function test_nothing_waiting_shows_no_numbers(): void
    {
        $this->seedRiderTestData();
        $this->sidebar()->assertDontSee('data-nav-count=', false);
    }

    public function test_each_page_shows_what_waits_there(): void
    {
        $this->seedRiderTestData();
        $this->parcel('pending_pickup');                                  // a request to confirm (also a pickup job)
        $this->parcel('pending_pickup');
        $this->parcel('picked_up', ['received_at' => now()]);             // at the hub, to sort
        $this->parcel('sorted', ['area_id' => $this->central->id]);       // sorted, waiting for a delivery rider
        $assigned = $this->parcel('pickup_approved');
        $this->pickupFor($this->rider1, $assigned);                        // has a rider: not waiting
        RiderRemittance::create(['rider_id' => $this->rider1->id, 'sorting_center_id' => $this->logistics1->id, 'amount' => 490, 'expected_amount' => 490, 'request_token' => 'test-token', 'status' => 'pending']);

        $this->assertSame(['pickups' => 2, 'sorting' => 1, 'dispatch' => 3, 'remittances' => 1], SortingCenterBadges::for($this->logistics1->id));
        $this->sidebar()
            ->assertSee('data-nav-count="pickups" ', false)->assertSee('title="Pickup requests to confirm">2</span>', false)
            ->assertSee('title="Parcels waiting to be sorted">1</span>', false)
            ->assertSee('title="Pickups and deliveries waiting for a rider">3</span>', false)
            ->assertSee('title="Cash remittances to confirm">1</span>', false);

        // Another company sees none of it.
        $this->assertSame(['pickups' => 0, 'sorting' => 0, 'dispatch' => 0, 'remittances' => 0], SortingCenterBadges::for($this->logistics2->id));
    }
}
