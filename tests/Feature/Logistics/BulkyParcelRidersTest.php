<?php

namespace Tests\Feature\Logistics;

use App\Models\ParcelPickup;
use App\Models\Rider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Riders: motorcycle, tricycle, bicycle, car, van (cars and vans also take bulky parcels). Couriers: trucks, hub to hub only. */
class BulkyParcelRidersTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
    }

    public function test_only_trucks_are_couriers(): void
    {
        $this->assertTrue(Rider::isFourWheel('Truck'));
        $this->assertTrue(Rider::isFourWheel('6-Wheeler Truck'));
        $this->assertFalse(Rider::isFourWheel('Van'));
        $this->assertFalse(Rider::isFourWheel('Car'));
        $this->rider2->update(['vehicle_type' => 'Van']);
        $this->assertFalse($this->rider2->fresh()->isTruck());
        $this->assertTrue($this->rider2->fresh()->canCarryBulky());
        $this->assertFalse($this->rider1->canCarryBulky());
    }

    public function test_a_bulky_parcel_goes_only_to_a_car_or_van_rider(): void
    {
        $parcel = $this->parcel('pending_pickup', ['weight_kg' => 25]);
        $this->assertTrue($parcel->isBulky());

        // A motorcycle rider is refused.
        $this->actingAs($this->logistics1)->from(route('sc.pickup-requests'))
            ->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider1->id])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'bulky'));
        $this->assertSame(0, ParcelPickup::where('parcel_id', $parcel->id)->count());

        // A van rider takes it.
        $this->rider2->update(['vehicle_type' => 'Van']);
        $this->actingAs($this->logistics1)->from(route('sc.pickup-requests'))
            ->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider2->id])
            ->assertSessionHas('success');
        $this->assertSame($this->rider2->id, ParcelPickup::where('parcel_id', $parcel->id)->value('rider_id'));

        // A light parcel can still go to a motorcycle rider.
        $light = $this->parcel('pending_pickup', ['weight_kg' => 2]);
        $this->assertFalse($light->isBulky());
        $this->actingAs($this->logistics1)->from(route('sc.pickup-requests'))
            ->post(route('sc.dispatch.assign', $light), ['rider_id' => $this->rider1->id])
            ->assertSessionHas('success');
    }

    public function test_a_big_item_is_bulky_even_when_light(): void
    {
        $parcel = $this->parcel('pending_pickup', ['weight_kg' => 8]);
        $this->assertFalse($parcel->isBulky());
        $item = $parcel->order->items()->with('product')->get()->first(fn ($i) => (int) $i->product->seller_id === (int) $parcel->seller_id);
        $item->product->update(['length_cm' => 120, 'width_cm' => 60, 'height_cm' => 75]);   // a table
        $this->assertTrue($parcel->fresh()->isBulky());

        $this->actingAs($this->logistics1)->get(route('sc.pickup-requests'))->assertOk()->assertSee('data-bulky', false);
    }
}
