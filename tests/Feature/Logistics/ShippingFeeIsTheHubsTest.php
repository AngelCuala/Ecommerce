<?php

namespace Tests\Feature\Logistics;

use App\Services\SortingCenterCommission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** ALVY's commission is on the items only: the whole shipping fee goes to the hubs, less their riders' pay. */
class ShippingFeeIsTheHubsTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    public function test_alvy_takes_nothing_from_the_shipping_fee(): void
    {
        $this->seedRiderTestData();
        $this->assertSame(0.0, SortingCenterCommission::rate());

        $d = $this->deliveryFor($this->rider1);
        $d->update(['status' => 'delivered', 'delivered_at' => now()]);
        $d->parcel->update(['status' => 'in_transit']);
        $d->parcel->update(['status' => 'delivered']);

        $line = app(SortingCenterCommission::class)->lines($this->logistics1->id)->firstWhere('parcel.id', $d->parcel_id);
        $this->assertSame(0.0, $line['commission']);
        $this->assertEqualsWithDelta($line['share'] - $line['rider_pay'], $line['net'], 0.001);   // share − rider pay, nothing to ALVY

        $this->actingAs($this->logistics1)->get(route('sc.commissions'))->assertOk()
            ->assertSee('ALVY takes no part of the shipping fee')->assertDontSee('ALVY commission (');
    }
}
