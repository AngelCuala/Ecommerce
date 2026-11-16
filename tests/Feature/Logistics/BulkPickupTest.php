<?php

namespace Tests\Feature\Logistics;

use App\Models\Parcel;
use App\Models\ParcelPickup;
use App\Models\PickupStop;
use App\Services\PickupStops;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** PAYMENTS_SPEC §11: one stop per seller per day, a cutoff and a time window set by logistics, one rider for the stop. */
class BulkPickupTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seedRiderTestData();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $time): void
    {
        Carbon::setTestNow(Carbon::parse("2026-11-20 $time", 'Asia/Manila'));
    }

    /** Seller 1 makes an order ready for pickup by Logistics 1. */
    private function readyForPickup(?string $window = null)
    {
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $this->actingAs($this->seller1)->post(route('seller.orders.accept', $order->id));
        $this->actingAs($this->seller1)->post(route('seller.orders.pack', $order->id));

        return $this->actingAs($this->seller1)->post(route('seller.orders.handover', $order->id),
            ['sorting_center_id' => $this->logistics1->id] + ($window ? ['pickup_window' => $window] : []));
    }

    public function test_the_cutoff_sets_the_day_and_the_windows(): void
    {
        $this->at('10:00');
        $this->assertSame(['13:00-17:00' => 'Today, 1–5 PM'], PickupStops::choices());     // 9–12 has started

        $this->at('14:00');                                                                  // after the 12 PM cutoff
        $this->assertSame(['09:00-12:00' => 'Tomorrow, 9 AM–12 PM', '13:00-17:00' => 'Tomorrow, 1–5 PM'], PickupStops::choices());
    }

    public function test_a_sellers_parcels_that_day_share_one_stop_and_one_rider(): void
    {
        $this->at('09:30');
        $this->readyForPickup()->assertSessionHas('success', fn ($m) => str_contains($m, 'Today, 1–5 PM'));
        $this->readyForPickup()->assertSessionHas('success', fn ($m) => str_contains($m, 'with your other 2 parcels'));
        $stop = PickupStop::sole();
        $this->assertSame(2, Parcel::where('pickup_stop_id', $stop->id)->count());
        $this->actingAs($this->logistics1)->get(route('sc.pickup-requests'))->assertOk()->assertSee('Stop ' . $stop->code());

        // The hub gives one parcel to Rider 1: the whole stop goes to them.
        $this->online($this->rider1);
        $first = Parcel::where('pickup_stop_id', $stop->id)->first();
        $this->dispatcher()->assignManually($first, $this->rider1, $this->logistics1);
        $this->assertSame(2, ParcelPickup::where('rider_id', $this->rider1->id)->where('status', 'assigned')->count());
        $this->assertSame($this->rider1->id, (int) $stop->fresh()->rider_id);
        $this->actingAs($this->rider1->user)->get(route('courier.rider'))->assertOk()->assertSee('2 parcels at this seller');

        // A third parcel later that day rides along with the same rider.
        $this->readyForPickup();
        $this->assertSame(3, ParcelPickup::where('rider_id', $this->rider1->id)->where('status', 'assigned')->count());
        $this->assertSame(1, PickupStop::count());

        // Once the rider has started collecting, the next parcel gets a new stop.
        Parcel::where('pickup_stop_id', $stop->id)->update(['status' => 'in_transit_to_hub']);
        $this->readyForPickup();
        $this->assertSame(2, PickupStop::count());
    }

    public function test_the_next_day_after_the_cutoff(): void
    {
        $this->at('15:00');
        $this->readyForPickup('09:00-12:00')->assertSessionHas('success', fn ($m) => str_contains($m, 'Tomorrow, 9 AM–12 PM'));
        $this->assertSame('2026-11-21', PickupStop::sole()->pickup_date->toDateString());
        $this->readyForPickup('08:00-09:00')->assertSessionHasErrors('pickup_window');       // only the windows offered
    }
}
