<?php

namespace Tests\Feature\Rider;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\ParcelPickup;
use App\Services\RiderWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/**
 * The whole rider flow through the real pages, for the workbook's same-city order
 * (Seller 1, Quezon City → Buyer 1, ADDR-1 Quezon City, COD ₱500.00):
 * Ready for Pickup → Assigned → Picked Up → At Hub → Out for Delivery → Delivered (A10).
 */
class R08EndToEndFlowTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        // Logistics 1 serves Quezon City.
        $this->logistics1->update(['assigned_municipality' => 'Quezon City', 'assigned_municipality_code' => self::QUEZON_CITY,
            'assigned_province' => 'Metro Manila']);
    }

    public function test_same_city_order_goes_through_the_hub_and_its_riders(): void
    {
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $this->assertEquals(500, $order->total_price);

        // Seller: accept, pack, Ready for Pickup.
        $this->actingAs($this->seller1)->post(route('seller.orders.accept', $order->id));
        $this->actingAs($this->seller1)->post(route('seller.orders.pack', $order->id));
        $this->actingAs($this->seller1)->post(route('seller.orders.handover', $order->id), ['courier_name' => 'Bilis Hatid Express', 'pickup_scheduled_at' => now()->addHour()->format('Y-m-d H:i')])
            ->assertSessionHas('success', fn ($m) => str_contains($m, "riders"));
        $this->assertFalse(Delivery::where('order_id', $order->id)->exists());   // not the direct courier
        $parcel = Parcel::where('order_id', $order->id)->sole();
        $this->assertSame($this->logistics1->id, (int) $parcel->current_sorting_center_id);

        // Logistics confirms the pickup and auto-assigns; Rider 1 accepts and scans the waybill.
        $this->actingAs($this->logistics1)->post(route('sc.pickup-requests.approve', $parcel))->assertSessionHas('success');
        $this->online($this->rider1);
        $this->actingAs($this->logistics1)->post(route('sc.dispatch.auto', $parcel))->assertSessionHas('success');
        $offer = $parcel->jobOffers()->sole();
        $this->actingAs($this->rider1->user)->post(route('courier.rider.offers.accept', $offer->id))->assertSessionHas('success');
        $pickup = ParcelPickup::sole();
        $this->actingAs($this->rider1->user)->post(route('courier.rider.pickups.confirm', $pickup->id), ['tracking_number' => $parcel->tracking_number])->assertSessionHas('success');
        $this->assertSame(Order::SHIPPED, $order->fresh()->status);

        // At Hub, sorted to Brgy. Central, then the delivery job goes to Rider 1.
        $this->actingAs($this->logistics1)->post(route('sc.parcels.scan'), ['tracking_number' => $parcel->tracking_number])->assertSessionHas('success');
        $this->actingAs($this->logistics1)->post(route('sc.parcels.sort', $parcel), ['area_id' => $this->central->id])->assertSessionHas('success');
        $this->online($this->rider1);
        $this->actingAs($this->logistics1)->post(route('sc.dispatch.auto', $parcel))->assertSessionHas('success');
        $offer = $parcel->jobOffers()->where('kind', 'delivery')->sole();
        $this->actingAs($this->rider1->user)->get(route('courier.rider'))->assertSee('COD: ₱500.00', false)->assertSee('Pay: ₱50.00 delivery', false);
        $this->actingAs($this->rider1->user)->post(route('courier.rider.offers.accept', $offer->id))->assertSessionHas('success');

        // Out for Delivery → Delivered with photo, the buyer's OTP and the COD.
        $delivery = ParcelDelivery::sole();
        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.start', $delivery->id))->assertSessionHas('success');
        $otp = $delivery->fresh()->otp_code;
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertSee($otp);
        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.deliver', $delivery->id), [
            'pod_photo' => UploadedFile::fake()->image('pod_photo.jpg'), 'otp' => $otp, 'cod_collected' => '500.00',
        ])->assertSessionHas('success');

        $this->assertSame(Order::DELIVERED, $order->fresh()->status);
        $wallet = app(RiderWallet::class);
        $this->assertEquals(80, $wallet->pay($this->rider1, now()->startOfDay(), now()->endOfDay()));   // ₱30 pickup + ₱50 delivery
        $this->assertEquals(500, $wallet->codBalance($this->rider1));
    }

    public function test_without_a_covering_company_the_direct_courier_still_takes_same_city_orders(): void
    {
        $this->logistics1->update(['assigned_municipality' => null, 'assigned_municipality_code' => null]);
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $this->actingAs($this->seller1)->post(route('seller.orders.accept', $order->id));
        $this->actingAs($this->seller1)->post(route('seller.orders.pack', $order->id));
        $this->actingAs($this->seller1)->post(route('seller.orders.handover', $order->id), ['courier_name' => 'X', 'pickup_scheduled_at' => now()->addHour()->format('Y-m-d H:i')])
            ->assertSessionHas('success');
        $this->assertTrue(Delivery::where('order_id', $order->id)->exists());
        $this->assertFalse(Parcel::where('order_id', $order->id)->exists());
    }
}
