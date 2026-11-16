<?php

namespace Tests\Feature\Logistics;

use App\Models\Parcel;
use App\Models\RiderJobOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/**
 * Shipment page, "Sort to barangay": when the parcel is at the hub of the buyer's town it is sorted to the
 * buyer's barangay (picked from the address) instead of transferred, and the delivery goes to a rider.
 */
class SortToBarangayTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        // Logistics 1 serves Quezon City, the buyer's city; Logistics 2 is elsewhere.
        $this->logistics1->update(['assigned_municipality' => 'Quezon City', 'assigned_municipality_code' => '137404000']);
        $this->logistics2->update(['assigned_municipality' => 'Caloocan City', 'assigned_municipality_code' => '137501000']);
    }

    private function atHub(?string $address = null): Parcel
    {
        return $this->parcel('picked_up', ['received_at' => now()] + ($address ? ['dropoff_address' => $address] : []));
    }

    public function test_the_buyers_hub_sees_a_sort_form_with_the_barangay_from_the_address(): void
    {
        $parcel = $this->atHub('12 Mabini St., Brgy. Central, Quezon City, Metro Manila');

        $this->actingAs($this->logistics1)->get(route('sc.tracking.show', $parcel))->assertOk()
            ->assertSee('Sort to barangay')
            ->assertSee('action="' . route('sc.parcels.sort', $parcel) . '"', false)
            ->assertSee('<option value="' . $this->central->id . '" selected>Central (from the address)</option>', false)
            ->assertSee('Or send it to another sorting center on a linehaul trip')   // still possible, tucked away
            ->assertDontSee('Sort or transfer')
            ->assertDontSee('Start sorting');
    }

    public function test_sorting_makes_it_ready_for_the_barangay_rider(): void
    {
        $this->online($this->rider1);
        $parcel = $this->atHub();

        // Sorting doesn't offer the job to anyone: it waits in Rider Dispatch for Auto-assign (the barangay's rider).
        $this->actingAs($this->logistics1)->from(route('sc.tracking.show', $parcel))
            ->post(route('sc.parcels.sort', $parcel), ['area_id' => $this->central->id])
            ->assertRedirect(route('sc.tracking.show', $parcel))
            ->assertSessionHas('success', "{$parcel->tracking_number} sorted to Central. The delivery is waiting in Rider Dispatch.");

        $parcel->refresh();
        $this->assertSame(['sorted', $this->central->id], [$parcel->status, $parcel->area_id]);
        $this->assertSame(0, RiderJobOffer::count());
        $this->assertSame(RiderJobOffer::DELIVERY, app(\App\Services\RiderDispatch::class)->jobKind($parcel));

        $this->get(route('sc.tracking.show', $parcel))->assertSee('data-sorted-to', false)->assertSee('Sorted to <strong>Central</strong>', false);
    }

    public function test_with_no_rider_online_the_delivery_waits_in_rider_dispatch(): void
    {
        $parcel = $this->atHub();

        $this->actingAs($this->logistics1)->post(route('sc.parcels.sort', $parcel), ['area_id' => $this->central->id])
            ->assertSessionHas('success', "{$parcel->tracking_number} sorted to Central. The delivery is waiting in Rider Dispatch.");
        $this->assertSame('sorted', $parcel->fresh()->status);
        $this->assertSame(0, RiderJobOffer::count());
    }

    public function test_a_hub_that_does_not_serve_the_buyer_still_gets_the_transfer_form(): void
    {
        $parcel = $this->parcel('picked_up', ['received_at' => now()], null, $this->logistics2);

        $this->actingAs($this->logistics2)->get(route('sc.tracking.show', $parcel))->assertOk()
            ->assertSee('Send to another sorting center')->assertSee('Sort for this center')
            ->assertSee('(serves this address)')
            ->assertDontSee('data-sort-form', false);

        // And it cannot sort it to a barangay itself.
        $this->actingAs($this->logistics2)->post(route('sc.parcels.sort', $parcel), ['area_id' => $this->central->id])->assertSessionHas('error');
        $this->assertSame('picked_up', $parcel->fresh()->status);
    }
}
