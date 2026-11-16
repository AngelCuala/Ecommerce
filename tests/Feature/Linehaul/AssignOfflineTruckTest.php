<?php

namespace Tests\Feature\Linehaul;

use App\Services\Linehaul;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LinehaulTestData;
use Tests\TestCase;

/** The trip page lists every truck of the company: on duty, offline (assignable by hand), and busy ones with why. */
class AssignOfflineTruckTest extends TestCase
{
    use RefreshDatabase, LinehaulTestData;

    public function test_an_offline_truck_can_be_assigned_by_hand_and_a_busy_one_is_shown_with_its_trip(): void
    {
        $this->seedLinehaulTestData();
        $trip = $this->trip1(false);                                   // nobody on duty

        // Courier 2 is busy on another trip.
        $this->onDuty($this->courier2);
        $other = app(Linehaul::class)->createTrip($this->logistics1, ['origin_hub_id' => $this->hubQc->id, 'destination_hub_id' => $this->hubPsg->id,
            'parcel_ids' => [$this->parcel('picked_up', ['current_hub_id' => $this->hubQc->id])->id]]);
        app(Linehaul::class)->assign($this->logistics1, $other, $this->courier2->id);
        $this->courier2->forceFill(['on_duty' => false])->save();

        $this->actingAs($this->logistics1)->get(route('sc.trips.show', $trip))->assertOk()
            ->assertSee('<optgroup label="Offline (sees it when they go On Duty)">', false)
            ->assertSee('Ernesto Villareal · 4-Wheeler Closed Van Truck · offline')
            ->assertSee('Rogelio Dimaculangan · on ' . $other->code)          // busy: listed, not selectable
            ->assertDontSee('No truck courier free');

        // Auto-assign still only uses trucks that are on duty.
        $this->assertSame([], app(Linehaul::class)->assignable($this->logistics1, $trip)->all());

        // By hand, the offline truck gets the trip.
        $this->actingAs($this->logistics1)->from(route('sc.trips.show', $trip))
            ->post(route('sc.trips.assign', $trip), ['courier_id' => $this->courier1->id])->assertSessionHasNoErrors();
        $this->assertSame($this->courier1->id, $trip->fresh()->courier_id);

        // Courier 2 loads their own trip and scans a parcel that belongs to TRIP-1: told which trip it is on and where it goes.
        $other->update(['status' => 'loading', 'accepted_at' => now(), 'loading_at' => now()]);
        app(Linehaul::class)->respondHandover($this->pasig, $other->fresh(), true);
        $mine = $trip->items()->first()->parcel;
        try {
            app(Linehaul::class)->scan($this->courier2, $other->id, $mine->waybill_number ?: $mine->tracking_number);
            $this->fail('A parcel of another trip was loaded.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertSame("{$mine->tracking_number} is not on {$other->code} (to HUB-PSG). It belongs to {$trip->code}, going to HUB-PSG. Leave it for that trip.",
                $e->errors()['tracking'][0]);
        }

        // The busy one is refused.
        $this->actingAs($this->logistics1)->from(route('sc.trips.show', $trip))
            ->post(route('sc.trips.assign', $trip), ['courier_id' => $this->courier2->id, 'reason' => 'Swap'])->assertSessionHasErrors('courier_id');
    }
}
