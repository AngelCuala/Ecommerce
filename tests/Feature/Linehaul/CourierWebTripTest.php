<?php

namespace Tests\Feature\Linehaul;

use App\Models\LinehaulTrip;
use App\Models\Parcel;
use App\Services\Linehaul;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LinehaulTestData;
use Tests\TestCase;

/** A truck courier takes a trip to another sorting center's hub on the website: accept, load and scan, depart, delay, arrive. */
class CourierWebTripTest extends TestCase
{
    use RefreshDatabase, LinehaulTestData;

    private const AT_DVO = [7.1100, 125.6100];

    public function test_courier_delivers_a_trip_to_the_hub_on_the_website(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seedLinehaulTestData();
        // The truck is a rider's 4-wheeler (riders and couriers are one account).
        $rider = app(\App\Services\TruckRiders::class)->adopt($this->courier1);
        $this->assertTrue($rider->isTruck());
        $parcels = $this->tripParcels(2);
        $trip = app(Linehaul::class)->createTrip($this->logistics1, ['origin_hub_id' => $this->hubQc->id, 'destination_hub_id' => $this->hubDvo->id,
            'parcel_ids' => array_map(fn (Parcel $p) => $p->id, $parcels)]);
        $this->onDuty($this->courier1);
        app(Linehaul::class)->assign($this->logistics1, $trip, $this->courier1->id);
        $c = $this->courier1->user;
        $page = fn () => $this->actingAs($c)->get(route('courier.trips'))->assertOk();
        $post = fn (string $name, array $data = []) => $this->actingAs($c)->from(route('courier.trips'))->post(route("courier.trips.$name", $trip->id), $data);

        $page()->assertSee($trip->code)->assertSee('Accept trip');
        $post('accept')->assertSessionHas('success');

        // Loading waits for the receiving sorting center to accept the trip.
        $page()->assertSee('Waiting for')->assertDontSee('Start loading');
        $post('loading')->assertSessionHas('error');
        app(Linehaul::class)->respondHandover($this->logistics2, $trip->fresh(), true);
        $page()->assertSee('Start loading');
        $post('loading')->assertSessionHas('success');
        $this->assertSame('loading', $trip->fresh()->status);

        // Scan: the QR pop-up (JSON) and the typed form; a wrong code is refused.
        $page()->assertSee('Scan parcel QR')->assertSee('0/2 loaded')->assertSee('>Depart</button>', false)->assertDontSee('seal_number', false);
        // The waybill's QR code holds the waybill number (PH…), not the tracking number.
        $waybill = $parcels[0]->fresh()->waybill_number;
        $this->assertNotEmpty($waybill);
        $this->actingAs($c)->postJson(route('courier.trips.scan', $trip->id), ['tracking_number' => $waybill])
            ->assertOk()->assertJson(['ok' => true, 'loaded' => 1, 'total' => 2])->assertJsonPath('message', fn ($m) => str_contains($m, $parcels[0]->tracking_number))->assertJsonPath('url', fn ($u) => str_contains($u, '/courier/trips'));
        $this->actingAs($c)->postJson(route('courier.trips.scan', $trip->id), ['tracking_number' => 'TRK-0000000'])
            ->assertStatus(422)->assertJson(['ok' => false])->assertJsonPath('message', fn ($m) => str_contains($m, 'not found'));
        $post('depart', ['lat' => self::AT_QC[0], 'lng' => self::AT_QC[1]])
            ->assertSessionHas('error', fn ($m) => str_contains($m, $parcels[1]->tracking_number));
        $post('scan', ['tracking_number' => strtolower($parcels[1]->tracking_number)])->assertSessionHas('success', fn ($m) => str_contains($m, 'Every parcel is on board'));

        // Depart at the origin hub (no seal number: the trip's code identifies it).
        $post('depart', ['lat' => self::AT_DVO[0], 'lng' => self::AT_DVO[1]])->assertSessionHas('error', fn ($m) => str_contains($m, 'HUB-QC'));
        $post('depart', ['lat' => self::AT_QC[0], 'lng' => self::AT_QC[1]])->assertSessionHas('success');
        $this->assertSame('in_transit', $trip->fresh()->status);
        $this->assertSame('linehaul', $parcels[0]->fresh()->status);

        // On the road: a delay, then Arrived only at the destination hub.
        $page()->assertSee('Report a delay')->assertSee('arrived at HUB-DVO');
        $post('delay', ['reason' => 'Heavy traffic', 'eta' => now()->subHour()->toDateTimeString()])->assertSessionHas('error');
        $post('delay', ['reason' => 'Heavy traffic', 'eta' => now()->timezone(config('app.business_timezone'))->addHours(3)->format('Y-m-d\TH:i')])->assertSessionHas('success');
        $this->assertTrue((bool) $trip->fresh()->delayed);
        $post('arrived', ['lat' => self::AT_QC[0], 'lng' => self::AT_QC[1]])->assertSessionHas('error', fn ($m) => str_contains($m, 'HUB-DVO'));
        $post('arrived', ['lat' => self::AT_DVO[0], 'lng' => self::AT_DVO[1]])->assertSessionHas('success');
        $this->assertSame('arrived', $trip->fresh()->status);
        $page()->assertSee('Arrived at HUB-DVO');

        // The receiving hub receives the parcels straight away (no seal check): the trip is paid, like a rider's finished job.
        app(Linehaul::class)->receiveScan($this->logistics2, $this->hubDvo->id, $parcels[1]->fresh()->waybill_number);   // by waybill too
        app(Linehaul::class)->receiveAll($this->logistics2, $trip->fresh());
        $this->assertContains($trip->fresh()->status, LinehaulTrip::DONE);
        $pay = '₱' . number_format(Linehaul::payFor($this->courier1->fresh()), 2);
        $this->actingAs($c)->get(route('courier.trips.history'))->assertOk()
            ->assertSee('Job History')->assertSee('data-history="' . $trip->code . '"', false)->assertSee('HUB-QC → HUB-DVO')->assertSee($pay);
        $this->actingAs($c)->get(route('courier.trips.pay', ['period' => 'today']))->assertOk()
            ->assertSee('Rider Pay')->assertSee('data-pay-row="' . $trip->code . '"', false)->assertSee('+' . $pay);
        $this->actingAs($c)->get(route('courier.account'))->assertOk()
            ->assertSee('Courier · truck')->assertSee('Paid trips')->assertSee('data-account-earned', false)->assertSee($pay)->assertSee('NAB4521');
        // A 4-wheel rider's rider pages open the hub-to-hub ones.
        $this->actingAs($c)->get(route('courier.rider'))->assertRedirect(route('courier.trips'));
        $this->actingAs($c)->get(route('courier.rider.history'))->assertRedirect(route('courier.trips.history'));

        // Someone else's trip can't be touched.
        $this->actingAs($this->courier4->user)->post(route('courier.trips.arrived', $trip->id))->assertNotFound();
    }
}
