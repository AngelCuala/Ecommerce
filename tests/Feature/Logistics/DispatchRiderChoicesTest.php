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
        $page->assertSee('<option value="' . $this->rider1->id . '" data-available>Pedro Jose Garcia (0 jobs)</option>', false)
            ->assertSee('<option value="' . $this->rider2->id . '" disabled>Mark Anthony Bautista · offline</option>', false)
            ->assertSee('<option value="' . $this->rider3->id . '" disabled>Ramon Cruz · suspended</option>', false)
            ->assertSee('<optgroup label="Available">', false)
            ->assertDontSee('Agila Rider')                 // another company's rider
            ->assertDontSee('data-no-rider-online', false);
    }

    public function test_with_nobody_online_the_riders_are_listed_and_a_hint_explains_it(): void
    {
        $this->actingAs($this->logistics1)->get(route('sc.dispatch'))->assertOk()
            ->assertSee('Pedro Jose Garcia · offline')->assertSee('Mark Anthony Bautista · offline')
            ->assertDontSee('<optgroup label="Available">', false)
            ->assertSee('data-no-rider-online', false)
            ->assertSee('Riders can be picked once they switch to Online');
    }

    public function test_an_offline_rider_still_cannot_be_assigned(): void
    {
        $parcel = \App\Models\Parcel::sole();
        $this->actingAs($this->logistics1)->from(route('sc.dispatch'))
            ->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider2->id])
            ->assertSessionHas('error', 'Mark Anthony Bautista is offline.');
    }
}
