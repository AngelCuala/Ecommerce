<?php

namespace Tests\Feature\Linehaul;

use App\Models\LinehaulTrip;
use App\Services\Linehaul;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\LinehaulTestData;
use Tests\TestCase;

/** A hub-to-hub delivery: choose the route only, parcels go in by themselves, then the truck, in five steps. */
class DeliveryStepsTest extends TestCase
{
    use RefreshDatabase, LinehaulTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seedLinehaulTestData();
        $this->logistics1->update(['assigned_municipality' => 'Quezon City']);
        $this->logistics2->update(['assigned_municipality' => 'Davao City']);
    }

    private function forDavao()
    {
        return $this->parcel('sorted', ['next_center_id' => $this->logistics2->id, 'area_id' => null, 'current_hub_id' => $this->hubQc->id, 'destination_municipality' => 'Davao City']);
    }

    private function create(array $extra = [])
    {
        return $this->actingAs($this->logistics1)->post(route('sc.trips.store'), $extra + ['origin_hub_id' => $this->hubQc->id, 'destination_hub_id' => $this->hubDvo->id]);
    }

    public function test_the_form_asks_only_for_the_route(): void
    {
        $this->actingAs($this->logistics1)->get(route('sc.trips'))->assertOk()
            ->assertDontSee('name="parcel_ids[]"', false)->assertDontSee('name="courier_id"', false)->assertDontSee('Auto-assign all');
    }

    public function test_parcels_are_assigned_only_on_the_open_delivery(): void
    {
        $going = $this->forDavao();
        $local = $this->parcel('sorted', ['current_hub_id' => $this->hubQc->id]);   // delivered by this hub: stays

        // Creating it puts nothing in, even if parcels are sent along.
        $this->create(['parcel_ids' => [$local->id], 'auto' => 1])->assertSessionHas('success', fn ($m) => str_contains($m, 'assign its parcels'));
        $trip = LinehaulTrip::sole();
        $this->assertSame(0, $trip->onBoard()->count());
        $this->assertNull($trip->courier_id);
        $this->assertSame(1, $trip->stage());

        // Sorting one for that hub doesn't add it either.
        $later = $this->parcel('picked_up', ['destination_municipality' => 'Davao City', 'area_id' => null, 'current_hub_id' => $this->hubQc->id, 'received_at' => now()]);
        $this->actingAs($this->logistics1)->from(route('sc.parcel-sorting'))->post(route('sc.parcels.sort-transfer', $later), ['center_id' => $this->logistics2->id])
            ->assertSessionHas('success');
        $this->assertSame(0, $trip->onBoard()->count());

        // On the delivery's page: Auto-assign takes every parcel for that hub, and not the local one.
        $this->actingAs($this->logistics1)->get(route('sc.trips.show', $trip))->assertOk()->assertSee('Assign parcels')->assertSee('Auto-assign 2 parcel(s)');
        $this->actingAs($this->logistics1)->from(route('sc.trips.show', $trip))->post(route('sc.trips.auto', $trip))->assertSessionHas('success');
        $this->assertEqualsCanonicalizing([$going->id, $later->id], $trip->onBoard()->pluck('parcel_id')->all());
    }

    public function test_the_truck_comes_after_the_parcels(): void
    {
        $this->onDuty($this->courier1);
        $empty = app(Linehaul::class)->createTrip($this->logistics1, ['origin_hub_id' => $this->hubQc->id, 'destination_hub_id' => $this->hubDvo->id]);
        try {
            app(Linehaul::class)->assign($this->logistics1, $empty, $this->courier1->id);
            $this->fail('A truck was assigned to an empty delivery.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Assign the parcels first', $e->errors()['courier_id'][0]);
        }
        $this->assertNull($empty->fresh()->courier_id);
    }

    public function test_the_five_steps(): void
    {
        $this->forDavao();
        $this->create()->assertSessionHas('success');
        $trip = LinehaulTrip::sole();
        app(Linehaul::class)->autoAddParcels($this->logistics1, $trip);
        $page = fn () => $this->actingAs($this->logistics1)->get(route('sc.trips.show', $trip))->assertOk();
        $page()->assertSee('data-trip-stage="1"', false)->assertSee('Next:')->assertSee('assign a truck');

        $this->onDuty($this->courier1);
        app(Linehaul::class)->assign($this->logistics1, $trip->fresh(), $this->courier1->id);
        $this->assertSame([2, 'Loading parcels onto the truck'], [$trip->fresh()->stage(), $trip->fresh()->stageLabel()]);
        $page()->assertSee('data-trip-stage="2"', false);

        $trip->items()->update(['status' => 'loaded', 'loaded_at' => now()]);
        $trip->update(['status' => 'loading', 'loading_at' => now()]);
        $this->assertSame([3, 'Ready to deliver to hub'], [$trip->fresh()->stage(), $trip->fresh()->stageLabel()]);
        $page()->assertSee('data-trip-stage="3"', false);

        $trip->update(['status' => 'in_transit', 'departed_at' => now()]);
        $this->assertSame([4, 'On delivery'], [$trip->fresh()->stage(), $trip->fresh()->stageLabel()]);

        $trip->update(['status' => 'completed', 'completed_at' => now()]);
        $this->assertSame([5, 'Received by HUB-DVO'], [$trip->fresh()->stage(), $trip->fresh()->stageLabel()]);
        $page()->assertSee('Received by HUB-DVO');
        $this->actingAs($this->logistics1)->get(route('sc.trips', ['tab' => 'all']))->assertOk();
    }
}
