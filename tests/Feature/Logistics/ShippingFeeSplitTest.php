<?php

namespace Tests\Feature\Logistics;

use App\Models\LinehaulTripParcel;
use App\Models\LogisticsHub;
use App\Services\Linehaul;
use App\Services\RiderDispatch;
use App\Services\RiderWallet;
use App\Services\SortingCenterCommission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LinehaulTestData;
use Tests\TestCase;

/** The shipping fee split 30/30/40: pickup rider 30%, delivery rider 30%, the hubs the parcel passed share 40% evenly. */
class ShippingFeeSplitTest extends TestCase
{
    use RefreshDatabase, LinehaulTestData;

    protected function setUp(): void
    {
        parent::setUp();
        config(['marketplace.rider_share_since' => '2020-01-01 00:00:00']);
        $this->seedLinehaulTestData();
    }

    /** A parcel alone in an order with this shipping fee. */
    private function parcelWithFee(float $fee, string $status = 'picked_up', array $extra = [])
    {
        $p = $this->parcel($status, $extra);
        $p->order->update(['shipping_fee' => $fee]);
        \App\Models\Parcel::where('order_id', $p->order_id)->whereKeyNot($p->id)->delete();

        return $p->fresh('order');
    }

    public function test_riders_get_30_percent_a_job(): void
    {
        $p = $this->parcelWithFee(100);
        $this->assertSame([30.0, 30.0], [RiderDispatch::pickupPay($p), RiderDispatch::deliveryPay($p)]);

        // The same rider picks it up and delivers it: 60%.
        \App\Models\ParcelPickup::create(['parcel_id' => $p->id, 'rider_id' => $this->rider1->id, 'sorting_center_id' => $this->logistics1->id,
            'status' => 'picked_up', 'picked_up_at' => now()]);
        \App\Models\ParcelDelivery::create(['parcel_id' => $p->id, 'rider_id' => $this->rider1->id, 'status' => 'delivered', 'delivered_at' => now(), 'attempt' => 1]);
        $this->assertSame(60.0, app(RiderWallet::class)->history($this->rider1)->sum('pay'));

        // Jobs from before the switch keep the old fixed pay.
        $this->assertSame([30.0, 50.0], [RiderDispatch::pickupPay($p, '2019-12-31'), RiderDispatch::deliveryPay($p, '2019-12-31')]);
    }

    public function test_four_hubs_share_the_40_percent(): void
    {
        $p = $this->parcelWithFee(1000);   // hubs' 40% = ₱400
        $hubDv2 = LogisticsHub::create(['sorting_center_id' => $this->logistics2->id, 'code' => 'HUB-DV2', 'name' => 'Agila Davao Hub 2',
            'address' => 'Davao City', 'latitude' => 7.1, 'longitude' => 125.6]);

        // QC (company 1) → PSG (Pasig) → DVO (company 2), now at DV2 (company 2): 4 hubs, ₱100 each.
        $lh = app(Linehaul::class);
        $a = $lh->createTrip($this->logistics1, ['origin_hub_id' => $this->hubQc->id, 'destination_hub_id' => $this->hubPsg->id]);
        $b = $lh->createTrip($this->pasig, ['origin_hub_id' => $this->hubPsg->id, 'destination_hub_id' => $this->hubDvo->id]);
        foreach ([$a, $b] as $trip) {
            LinehaulTripParcel::create(['linehaul_trip_id' => $trip->id, 'parcel_id' => $p->id, 'status' => 'received', 'received_at' => now()]);
        }
        \App\Models\Parcel::whereKey($p->id)->update(['current_hub_id' => $hubDv2->id, 'current_sorting_center_id' => $this->logistics2->id]);

        $share = fn ($sc) => app(SortingCenterCommission::class)->lines($sc->id)->firstWhere('parcel.id', $p->id);
        $this->assertSame([100.0, 4], [$share($this->logistics1)['share'], $share($this->logistics1)['hubs']]);
        $this->assertSame(100.0, $share($this->pasig)['share']);
        $this->assertSame(200.0, $share($this->logistics2)['share']);   // two of the four hubs

        // A trip it was taken off doesn't count.
        LinehaulTripParcel::where('linehaul_trip_id', $b->id)->update(['status' => 'removed']);
        $this->assertSame(3, $share($this->logistics1)['hubs']);
    }

    public function test_the_truck_courier_is_not_paid_per_trip(): void
    {
        $this->assertSame(0.0, Linehaul::payFor($this->courier1));
    }
}
