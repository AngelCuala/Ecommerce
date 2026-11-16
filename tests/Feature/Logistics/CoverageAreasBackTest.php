<?php

namespace Tests\Feature\Logistics;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Coverage Areas has a Back button to the page that opened it (My Account when opened directly). */
class CoverageAreasBackTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    public function test_back_goes_to_the_page_that_opened_coverage_areas(): void
    {
        $this->seedRiderTestData();
        $sc = $this->logistics1;

        // Opened directly: back to My Account.
        $this->actingAs($sc)->get(route('sc.areas'))->assertOk()
            ->assertSee('data-back', false)->assertSee('href="' . route('sc.account') . '"', false);

        // From the dashboard: back to the dashboard, also after the page reloads itself (an add or remove).
        $this->actingAs($sc)->from(route('sc.dashboard'))->get(route('sc.areas'))
            ->assertSee('href="' . route('sc.dashboard') . '" class="cv-back"', false);
        $this->actingAs($sc)->from(route('sc.areas'))->get(route('sc.areas'))
            ->assertSee('href="' . route('sc.dashboard') . '" class="cv-back"', false);
    }
}
