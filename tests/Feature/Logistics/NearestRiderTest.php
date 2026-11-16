<?php

namespace Tests\Feature\Logistics;

use App\Models\DeliveryArea;
use App\Models\ParcelDelivery;
use App\Services\NearestRiders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** A parcel whose barangay has no rider goes to the nearest of the company's riders (manual assignment). */
class NearestRiderTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    private DeliveryArea $far;
    private DeliveryArea $empty;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        // Central (riders 1 and 2), a far barangay (rider 2 moves there), and one with no rider at all.
        $this->central->update(['latitude' => 14.2000, 'longitude' => 121.1500, 'located_at' => now()]);
        $this->far = DeliveryArea::create(['sorting_center_id' => $this->logistics1->id, 'name' => 'Far', 'code' => 'QC-FAR', 'latitude' => 14.4000, 'longitude' => 121.3000, 'located_at' => now()]);
        $this->empty = DeliveryArea::create(['sorting_center_id' => $this->logistics1->id, 'name' => 'Empty', 'code' => 'QC-EMPTY', 'latitude' => 14.2100, 'longitude' => 121.1600, 'located_at' => now()]);
        $this->rider2->update(['area_id' => $this->far->id]);
    }

    public function test_the_nearest_riders_come_first_and_the_nearest_is_picked(): void
    {
        $parcel = $this->parcel('sorted', ['area_id' => $this->empty->id]);

        $ranked = NearestRiders::ranked($parcel->fresh(), collect([$this->rider2->fresh('area'), $this->rider1->fresh('area')]));
        $this->assertSame([$this->rider1->id, $this->rider2->id], $ranked->map(fn ($x) => $x['rider']->id)->all());
        $this->assertTrue($ranked[0]['km'] < 2 && $ranked[1]['km'] > 20);

        $this->actingAs($this->logistics1)->get(route('sc.delivery-assignment', ['area_id' => $this->empty->id]))->assertOk()
            ->assertSee('data-nearest-riders', false)->assertSee('No rider for this barangay · nearest first')
            ->assertSeeInOrder([$this->rider1->full_name . ' · Central', '(nearest)', $this->rider2->full_name . ' · Far']);
    }

    public function test_the_buyers_pin_is_used_when_there_is_one(): void
    {
        $parcel = $this->parcel('sorted', ['area_id' => $this->empty->id]);
        $parcel->order->deliveryAddress->update(['latitude' => 14.3990, 'longitude' => 121.2990]);   // right next to Far

        $ranked = NearestRiders::ranked($parcel->fresh(), collect([$this->rider1->fresh('area'), $this->rider2->fresh('area')]));
        $this->assertSame($this->rider2->id, $ranked[0]['rider']->id);
    }

    public function test_a_parcel_with_no_rider_in_its_barangay_can_go_to_another_barangays_rider(): void
    {
        $parcel = $this->parcel('sorted', ['area_id' => $this->empty->id]);

        $this->actingAs($this->logistics1)->post(route('sc.parcels.assign', $parcel), ['rider_id' => $this->rider1->id])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'No rider covers Empty, so it went to ' . $this->rider1->full_name . ' of Central (about'));
        $this->assertSame($this->rider1->id, ParcelDelivery::where('parcel_id', $parcel->id)->sole()->rider_id);
        $this->assertSame('assigned', $parcel->fresh()->status);
    }

    public function test_when_the_barangay_has_a_rider_it_must_be_that_rider(): void
    {
        $parcel = $this->parcel('sorted', ['area_id' => $this->far->id]);   // rider 2 covers Far

        $this->actingAs($this->logistics1)->post(route('sc.parcels.assign', $parcel), ['rider_id' => $this->rider1->id])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Choose a rider for that area'));
        $this->assertSame(0, ParcelDelivery::where('parcel_id', $parcel->id)->count());
    }

    public function test_barangays_are_placed_on_the_map_once(): void
    {
        $new = DeliveryArea::create(['sorting_center_id' => $this->logistics1->id, 'name' => 'Banadero', 'code' => 'CAL-02', 'municipality' => 'City of Calamba']);
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response([['lat' => '14.2100', 'lon' => '121.1600']])]);

        $this->artisan('areas:locate')->assertSuccessful();
        $this->assertEqualsWithDelta(14.21, $new->fresh()->latitude, 0.0001);
        $this->assertNotNull($new->fresh()->located_at);
        Http::assertSent(fn ($r) => str_contains(urldecode($r->url()), 'Banadero, City of Calamba, Philippines'));

        $this->artisan('areas:locate')->assertSuccessful();   // not asked again
        Http::assertSentCount(1);
    }
}
