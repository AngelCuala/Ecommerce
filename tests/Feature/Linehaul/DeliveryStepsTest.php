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

    public function test_a_trip_can_be_cancelled_and_removed_for_good(): void
    {
        $parcel = $this->forDavao();
        $this->create()->assertSessionHas('success');
        $trip = LinehaulTrip::sole();
        app(Linehaul::class)->autoAddParcels($this->logistics1, $trip);
        $trip->events()->create(['type' => 'note', 'note' => 'x', 'user_id' => $this->logistics1->id]);

        // Only the sending hub; the page offers it.
        $this->actingAs($this->logistics2)->post(route('sc.trips.remove', $trip))->assertForbidden();
        $this->actingAs($this->logistics1)->get(route('sc.trips.show', $trip))->assertOk()->assertSee('data-trip-remove', false);

        // Cancel, then remove: the trip and its history are gone, the parcel stays at the hub with no trip.
        $this->actingAs($this->logistics1)->from(route('sc.trips.show', $trip))->post(route('sc.trips.cancel', $trip), ['reason' => 'Truck is not available'])
            ->assertSessionHas('success');
        $this->actingAs($this->logistics1)->post(route('sc.trips.remove', $trip))->assertRedirect(route('sc.trips'))->assertSessionHas('success');
        $this->assertNull(LinehaulTrip::find($trip->id));
        $this->assertSame(0, \App\Models\LinehaulTripEvent::where('linehaul_trip_id', $trip->id)->count());
        $this->assertSame(0, \App\Models\LinehaulTripParcel::where('linehaul_trip_id', $trip->id)->count());
        $this->assertSame('sorted', $parcel->fresh()->status);

        // A trip on the road can't be removed.
        $this->create();
        $road = LinehaulTrip::latest('id')->first();
        $road->forceFill(['status' => 'in_transit', 'departed_at' => now()])->save();
        $this->actingAs($this->logistics1)->from(route('sc.trips.show', $road))->post(route('sc.trips.remove', $road))->assertSessionHasErrors('trip');
        $this->assertNotNull(LinehaulTrip::find($road->id));
    }

    public function test_a_trip_can_be_edited_before_it_departs(): void
    {
        $this->forDavao();
        $this->create()->assertSessionHas('success');
        $trip = LinehaulTrip::sole();
        app(Linehaul::class)->autoAddParcels($this->logistics1, $trip);
        $this->actingAs($this->logistics1)->get(route('sc.trips.show', $trip))->assertOk()->assertSee('data-trip-edit', false);

        // The departure time.
        $when = now()->addDay()->setTime(9, 30)->format('Y-m-d\TH:i');
        $this->actingAs($this->logistics1)->from(route('sc.trips.show', $trip))->post(route('sc.trips.update', $trip), ['scheduled_departure_at' => $when])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'departure'));
        $this->assertSame('09:30', $trip->fresh()->scheduled_departure_at->format('H:i'));

        // Where it goes (no truck yet): its parcels come off; the new sorting center accepts it first.
        $other = \App\Models\User::where('role', 'sorting_center')->whereNotIn('id', [$this->logistics1->id, $this->logistics2->id])->first()
            ?? $this->company('third@hub.example.com', 'Third Hub', 'Third Hub Inc.', 'Third#Hub26');
        $hub3 = \App\Models\LogisticsHub::create(['sorting_center_id' => $other->id, 'code' => 'HUB-3RD', 'name' => 'Third Hub', 'address' => 'Somewhere', 'latitude' => 14.2, 'longitude' => 121.1]);
        $this->actingAs($this->logistics1)->from(route('sc.trips.show', $trip))->post(route('sc.trips.update', $trip), ['destination_hub_id' => $hub3->id])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'HUB-3RD'));
        $trip->refresh();
        $this->assertSame([$hub3->id, 'pending', 0], [(int) $trip->destination_hub_id, $trip->handover_status, $trip->onBoard()->count()]);

        // With a truck assigned, the destination stays; another hub can't edit; after departure nothing can.
        $trip->forceFill(['courier_id' => $this->courier1->id, 'status' => 'assigned'])->save();
        $this->actingAs($this->logistics1)->from(route('sc.trips.show', $trip))->post(route('sc.trips.update', $trip), ['destination_hub_id' => $this->hubDvo->id])
            ->assertSessionHasErrors('destination_hub_id');
        $this->actingAs($this->logistics2)->post(route('sc.trips.update', $trip), ['scheduled_departure_at' => $when])->assertForbidden();
        $trip->forceFill(['status' => 'in_transit'])->save();
        $this->actingAs($this->logistics1)->from(route('sc.trips.show', $trip))->post(route('sc.trips.update', $trip), ['scheduled_departure_at' => $when])
            ->assertSessionHasErrors('trip');
    }

    public function test_the_courier_can_cancel_while_loading(): void
    {
        $this->forDavao();
        $this->create();
        $trip = LinehaulTrip::sole();
        app(Linehaul::class)->autoAddParcels($this->logistics1, $trip);
        $trip->items()->update(['status' => 'loaded', 'loaded_at' => now()]);
        $trip->forceFill(['courier_id' => $this->courier1->id, 'status' => 'loading', 'loading_at' => now()])->save();

        $this->actingAs($this->courier1->user)->get(route('courier.trips'))->assertOk()->assertSee('data-courier-cancel', false);
        $this->actingAs($this->courier1->user)->from(route('courier.trips'))->post(route('courier.trips.decline', $trip->id), ['reason' => 'Vehicle problem'])
            ->assertSessionHas('success');
        $trip->refresh();
        $this->assertSame(['scheduled', null, 1], [$trip->status, $trip->courier_id, $trip->onBoard()->where('status', 'pending')->count()]);
        $this->assertSame(1, $trip->stage());
    }

    public function test_the_courier_departs_from_anywhere(): void
    {
        $this->forDavao();
        $this->create();
        $trip = LinehaulTrip::sole();
        app(Linehaul::class)->autoAddParcels($this->logistics1, $trip);
        $trip->items()->update(['status' => 'loaded', 'loaded_at' => now()]);
        $trip->forceFill(['courier_id' => $this->courier1->id, 'status' => 'loading', 'loading_at' => now(), 'handover_status' => 'accepted'])->save();

        // Far from the hub (about 50 km), and with no location at all: Depart is just the signal.
        app(Linehaul::class)->depart($this->courier1, $trip->id, 14.2, 121.4);
        $this->assertSame('in_transit', $trip->fresh()->status);
        $this->assertSame(1, $trip->locations()->count());

        $trip->fresh()->forceFill(['status' => 'loading', 'departed_at' => null])->save();
        $trip->items()->update(['status' => 'loaded']);
        app(Linehaul::class)->depart($this->courier1, $trip->id, null, null);
        $this->assertSame('in_transit', $trip->fresh()->status);
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
