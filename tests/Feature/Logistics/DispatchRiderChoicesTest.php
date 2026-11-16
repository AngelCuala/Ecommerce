<?php

namespace Tests\Feature\Logistics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Rider Dispatch "Choose rider" lists: every rider of the hub, available ones to pick, the others greyed out with why. */
class DispatchRiderChoicesTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        $this->parcel('pickup_approved');   // a job waiting, so the per-job Assign list shows too
    }

    public function test_available_riders_can_be_picked_and_the_rest_show_why_not(): void
    {
        $this->online($this->rider1);   // rider2 offline, rider3 suspended

        $page = $this->actingAs($this->logistics1)->get(route('sc.dispatch'))->assertOk();
        // Staff may also give a job by hand to an offline rider (only Auto-assign needs Online): listed, marked offline.
        $page->assertSee('<option value="' . $this->rider1->id . '" data-available>Pedro Jose Garcia', false)
            ->assertSee('<option value="' . $this->rider2->id . '" data-available>Mark Anthony Bautista', false)
            ->assertSee(' · offline</option>', false)
            ->assertSee('<option value="' . $this->rider3->id . '" disabled>Ramon Cruz · suspended</option>', false)
            ->assertSee('<optgroup label="Available">', false)
            ->assertDontSee('Agila Rider')                 // another company's rider
            ->assertDontSee('data-no-rider-online', false);
    }

    public function test_with_nobody_online_the_riders_can_still_be_picked_by_hand(): void
    {
        $this->actingAs($this->logistics1)->get(route('sc.dispatch'))->assertOk()
            ->assertSee('<optgroup label="Available">', false)
            ->assertSee('<option value="' . $this->rider1->id . '" data-available>Pedro Jose Garcia', false)
            ->assertDontSee('data-no-rider-online', false);   // that hint is only for a center with nobody who can take jobs
    }

    public function test_an_offline_rider_can_be_assigned_by_hand_but_not_by_auto_assign(): void
    {
        $parcel = \App\Models\Parcel::sole();
        $this->assertNotContains($this->rider2->id, app(\App\Services\RiderDispatch::class)->eligibleRiders($this->logistics1->id)->pluck('id')->all());
        $this->actingAs($this->logistics1)->from(route('sc.dispatch'))
            ->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider2->id])
            ->assertSessionHas('success');
        $this->assertSame($this->rider2->id, \App\Models\ParcelPickup::where('parcel_id', $parcel->id)->sole()->rider_id);
    }
}
