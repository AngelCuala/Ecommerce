<?php

namespace Tests\Feature\Logistics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Sorting center messages after an action show as pop-ups (toasts), not as banners above the page. */
class ScToastMessagesTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
    }

    public function test_error_and_success_messages_pop_up(): void
    {
        $this->actingAs($this->logistics1)->from(route('sc.incoming-parcels'))->followingRedirects()
            ->post(route('sc.parcels.scan'), ['tracking_number' => 'PH000000000000000'])
            ->assertOk()
            ->assertSee('id="sc-toasts"', false)->assertSee('data-toast="error"', false)
            ->assertSee('Parcel PH000000000000000 not found at your sorting center.')
            ->assertDontSee('class="flash flash-error"', false);

        $parcel = $this->parcel('pickup_approved');
        $this->actingAs($this->logistics1)->from(route('sc.tracking.show', $parcel))->followingRedirects()
            ->post(route('sc.tracking.receive', $parcel), ['confirm_received' => '1', 'condition' => 'good'])
            ->assertOk()
            ->assertSee('data-toast="success"', false)->assertSee($parcel->tracking_number . ' received at')
            ->assertDontSee('class="flash flash-success"', false);
    }

    public function test_no_messages_means_no_pop_up(): void
    {
        $this->actingAs($this->logistics1)->get(route('sc.incoming-parcels'))->assertOk()->assertDontSee('id="sc-toasts"', false);
    }
}
