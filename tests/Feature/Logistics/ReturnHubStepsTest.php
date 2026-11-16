<?php

namespace Tests\Feature\Logistics;

use App\Models\LinehaulTrip;
use App\Models\Order;
use App\Models\ReturnShipment;
use App\Models\SellerApplication;
use App\Services\Linehaul;
use App\Services\TripHandover;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\LinehaulTestData;
use Tests\TestCase;

/**
 * A return whose seller is in another town: the hub that delivered it (Pasig) sorts it for the hub of the seller's
 * town (HUB-QC), checks it, scans it for the record and puts it on a hub-to-hub trip; HUB-QC receives it from the
 * trip, does the same steps, and its rider brings it to the seller. The delivered parcel's own status doesn't change.
 */
class ReturnHubStepsTest extends TestCase
{
    use RefreshDatabase, LinehaulTestData;

    public function test_a_return_to_another_town_goes_back_on_a_hub_to_hub_trip(): void
    {
        $this->seedLinehaulTestData();
        // Seller 1 is in Quezon City, served by Logistics 1 (HUB-QC); the buyer was served by the Pasig company (HUB-PSG).
        $this->logistics1->forceFill(['assigned_municipality' => 'Quezon City', 'assigned_municipality_code' => '137404000'])->save();
        $this->pasig->forceFill(['assigned_municipality' => 'Pasig City', 'assigned_municipality_code' => '137403000'])->save();
        SellerApplication::create(['user_id' => $this->seller1->id, 'full_name' => 'Seller One', 'shop_name' => 'Seller One Shop', 'phone' => '09170000001',
            'address' => 'Commonwealth, Quezon City', 'municipality' => 'Quezon City', 'municipality_code' => '137404000', 'government_id_path' => 'ids/x.jpg', 'status' => 'approved']);

        $parcel = $this->parcel('delivered', ['current_sorting_center_id' => $this->pasig->id, 'current_hub_id' => $this->hubPsg->id]);
        $parcel->order->update(['status' => Order::DELIVERED, 'delivered_at' => now()]);
        $this->actingAs($this->buyer1)->post(route('orders.return', $parcel->order_id), [
            'reason' => 'Damaged item', 'details' => 'The basket arrived crushed.', 'evidence' => UploadedFile::fake()->image('d.jpg'),
        ])->assertSessionHas('success');
        $rs = ReturnShipment::sole();
        $this->assertSame($this->pasig->id, (int) $rs->sorting_center_id);
        $rs->forceFill(['status' => 'picked_up', 'picked_up_at' => now()])->save();   // collected from the buyer

        // HUB-PSG: sort (it is still coming in, so this also receives it) → for HUB-QC, the seller's town.
        $pasig = fn () => $this->actingAs($this->pasig)->from(route('sc.returns.show', $rs));
        $this->actingAs($this->pasig)->get(route('sc.returns.show', $rs))->assertOk()->assertSee('data-step-sort', false)->assertSee('HUB-QC');
        $pasig()->post(route('sc.returns.send', $rs))->assertSessionHas('error');   // not in order
        $pasig()->post(route('sc.returns.sort', $rs))->assertSessionHas('success', fn ($m) => str_contains($m, 'HUB-QC'));
        $this->assertSame(['at_hub', 'hub', $this->hubQc->id, $this->hubPsg->id], [$rs->fresh()->status, $rs->fresh()->next_stop, (int) $rs->fresh()->next_hub_id, (int) $rs->fresh()->current_hub_id]);
        $pasig()->post(route('sc.returns.check', $rs), ['condition' => 'good'])->assertSessionHas('success');
        $pasig()->post(route('sc.returns.scan', $rs), ['tracking_number' => $parcel->waybill_number])->assertSessionHas('success');
        // Not to a rider: the seller is in another town.
        $pasig()->post(route('sc.returns.to-seller', $rs), ['rider_id' => $this->rider1->id])->assertSessionHas('error');

        // Send: onto a trip HUB-PSG → HUB-QC (made now); the trip page opens.
        $r = $pasig()->post(route('sc.returns.send', $rs));
        $trip = LinehaulTrip::sole();
        $r->assertRedirect(route('sc.trips.show', $trip));
        $this->assertSame(['HUB-PSG → HUB-QC', $trip->id], [$trip->route(), (int) $rs->fresh()->linehaul_trip_id]);
        $this->assertSame($rs->id, (int) $trip->items()->sole()->return_shipment_id);

        // The trip, as for parcels: Logistics 1 accepts it, a Pasig truck takes it, the Handover QR at both ends.
        $truck = $this->linehaulCourier($this->pasig, $this->hubPsg, 'truck.pasig@example.com', 'Ramon', 'Cruz', 'Courier@2026', 'Truck', 'PSG1001', 4000, 'N09-00-000001', 'B, C');
        app(Linehaul::class)->respondHandover($this->logistics1, $trip->fresh(), true);
        $this->onDuty($truck);
        $this->actingAs($this->pasig);
        app(Linehaul::class)->assign($this->pasig, $trip->fresh(), $truck->id);
        app(Linehaul::class)->accept($truck, $trip->id);
        $this->sealTrip($trip);   // HUB-PSG scans the driver's QR, scans the item onto the truck, seals
        app(Linehaul::class)->depart($truck, $trip->id, null, null);
        $this->assertSame('in_transit', $rs->fresh()->status);
        $this->assertSame('delivered', $parcel->fresh()->status);   // the delivered parcel is untouched
        app(Linehaul::class)->arrived($truck, $trip->id, self::AT_QC[0], self::AT_QC[1]);
        $this->arrivalHandover($trip);
        $this->actingAs($this->logistics1);
        app(Linehaul::class)->receiveScan($this->logistics1, $this->hubQc->id, $parcel->waybill_number);

        // HUB-QC now has it: its own steps start again, and its rider brings it to the seller.
        $rs->refresh();
        $this->assertSame(['at_hub', $this->logistics1->id, $this->hubQc->id, 'sort'], [$rs->status, (int) $rs->sorting_center_id, (int) $rs->current_hub_id, $rs->stepAtHub()]);
        $this->assertSame('delivered', $parcel->fresh()->status);
        $qc = fn () => $this->actingAs($this->logistics1)->from(route('sc.returns.show', $rs));
        $this->actingAs($this->logistics1)->get(route('sc.returns', ['tab' => 'process']))->assertSee('data-process-return', false);
        $qc()->post(route('sc.returns.sort', $rs))->assertSessionHas('success', fn ($m) => str_contains($m, 'to the seller from this hub'));
        $qc()->post(route('sc.returns.check', $rs), ['condition' => 'damaged', 'note' => 'Corner crushed.', 'photo' => UploadedFile::fake()->image('c.jpg')])->assertSessionHas('success');
        $qc()->post(route('sc.returns.scan', $rs), ['tracking_number' => $parcel->tracking_number])->assertSessionHas('success');
        $qc()->post(route('sc.returns.to-seller', $rs), ['rider_id' => $this->rider1->id])->assertSessionHas('success');
        $this->actingAs($this->rider1->user)->post(route('courier.rider.return-pickups.handed', $rs->id))->assertSessionHas('success');
        $this->assertSame('returned', $rs->fresh()->status);

        // The record: both hubs' steps and the trip; the buyer sees the trip on the return card.
        $this->assertSame(['arrived', 'sorted', 'checked', 'scanned', 'loaded', 'departed', 'received', 'sorted', 'checked', 'scanned', 'to_seller'],
            $rs->scans()->pluck('type')->all());
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $parcel->order_id))->assertOk()
            ->assertSee('On a hub-to-hub trip')->assertSee('Arrived at HUB-QC');
    }

    public function test_a_cancelled_trip_puts_the_return_back_at_the_hub(): void
    {
        $this->seedLinehaulTestData();
        $this->logistics1->forceFill(['assigned_municipality' => 'Quezon City', 'assigned_municipality_code' => '137404000'])->save();
        SellerApplication::create(['user_id' => $this->seller1->id, 'full_name' => 'Seller One', 'shop_name' => 'Seller One Shop', 'phone' => '09170000001',
            'address' => 'Quezon City', 'municipality' => 'Quezon City', 'municipality_code' => '137404000', 'government_id_path' => 'ids/x.jpg', 'status' => 'approved']);
        $parcel = $this->parcel('delivered', ['current_sorting_center_id' => $this->pasig->id, 'current_hub_id' => $this->hubPsg->id]);
        $parcel->order->update(['status' => Order::DELIVERED, 'delivered_at' => now()]);
        $this->actingAs($this->buyer1)->post(route('orders.return', $parcel->order_id), [
            'reason' => 'Damaged item', 'details' => 'The basket arrived crushed.', 'evidence' => UploadedFile::fake()->image('d.jpg')]);
        $rs = ReturnShipment::sole();
        $rs->forceFill(['status' => 'picked_up', 'picked_up_at' => now()])->save();
        foreach ([['sort', []], ['check', ['condition' => 'good']], ['scan', ['tracking_number' => $parcel->waybill_number]], ['send', []]] as [$step, $data]) {
            $this->actingAs($this->pasig)->post(route('sc.returns.' . $step, $rs), $data);
        }
        $trip = LinehaulTrip::sole();
        $this->assertNull($rs->fresh()->stepAtHub());   // on the trip, waiting to leave
        $this->actingAs($this->pasig)->post(route('sc.returns.send', $rs))->assertSessionHas('error');   // not twice

        app(Linehaul::class)->cancel($trip, 'Truck broke down before loading.');
        $this->assertSame(['at_hub', null, 'send'], [$rs->fresh()->status, $rs->fresh()->linehaul_trip_id, $rs->fresh()->stepAtHub()]);
        $this->assertTrue($rs->scans()->where('type', 'removed')->exists());
    }
}
