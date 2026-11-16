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
 * (Seller 1, Quezon City → Buyer 1, ADDR-1 Quezon City, COD ₱490.00):
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
        $this->online($this->rider1);
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $this->assertEquals(490, $order->total_price);

        // Seller: accept, pack, Ready for Pickup.
        $this->actingAs($this->seller1)->post(route('seller.orders.accept', $order->id));
        $this->actingAs($this->seller1)->post(route('seller.orders.pack', $order->id));
        $this->actingAs($this->seller1)->post(route('seller.orders.handover', $order->id), ['sorting_center_id' => $this->logistics1->id, 'pickup_scheduled_at' => now()->addHour()->format('Y-m-d H:i')])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Bilis Hatid Express Inc.'));
        $this->assertFalse(Delivery::where('order_id', $order->id)->exists());   // not the direct courier
        $parcel = Parcel::where('order_id', $order->id)->sole();
        $this->assertSame($this->logistics1->id, (int) $parcel->current_sorting_center_id);

        // The seller chose Logistics 1: it confirms the pickup request, then gives the job to its rider
        // (no rider covers Seller 1's barangay here, so Auto-assign says so and the hub assigns Rider 1 by hand).
        $this->assertSame('pending_pickup', $parcel->status);
        $this->actingAs($this->logistics1)->post(route('sc.pickup-requests.approve', $parcel))->assertSessionHas('success');
        $this->actingAs($this->logistics1)->post(route('sc.dispatch.auto', $parcel))->assertSessionHas('error', fn ($m) => str_contains($m, 'Assign a rider manually'));
        $this->actingAs($this->logistics1)->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider1->id])->assertSessionHas('success');
        $pickup = ParcelPickup::sole();
        $this->assertSame($this->rider1->id, $pickup->rider_id);
        $this->actingAs($this->rider1->user)->post(route('courier.rider.jobs.acknowledge', ['kind' => 'pickup', 'job' => $pickup->id]))->assertSessionHas('success');
        $this->actingAs($this->rider1->user)->post(route('courier.rider.pickups.confirm', $pickup->id), ['tracking_number' => $parcel->tracking_number, 'proof_photo' => \Illuminate\Http\UploadedFile::fake()->image('pickup.jpg')])->assertSessionHas('success');
        $this->assertSame(Order::SHIPPED, $order->fresh()->status);

        // At Hub, sorted to Brgy. Central, then the delivery job goes to Rider 1.
        // Scanning only finds the parcel; the hub then checks it and confirms receipt.
        $this->actingAs($this->logistics1)->post(route('sc.parcels.scan'), ['tracking_number' => $parcel->tracking_number])->assertRedirect(route('sc.tracking.show', $parcel));
        $this->actingAs($this->logistics1)->post(route('sc.tracking.receive', $parcel), ['confirm_received' => '1', 'condition' => 'good'])->assertSessionHas('success');
        $this->actingAs($this->logistics1)->post(route('sc.parcels.sort', $parcel), ['area_id' => $this->central->id])->assertSessionHas('success');
        $this->online($this->rider1);
        $this->actingAs($this->logistics1)->post(route('sc.dispatch.auto', $parcel))->assertSessionHas('success');
        $this->actingAs($this->rider1->user)->get(route('courier.rider'))->assertSee($parcel->fresh()->waybill_number ?: $parcel->tracking_number);
        $this->actingAs($this->rider1->user)->post(route('courier.rider.jobs.acknowledge', ['kind' => 'delivery', 'job' => ParcelDelivery::sole()->id]))->assertSessionHas('success');

        // Out for Delivery → Delivered with photo, the buyer's OTP and the COD.
        $delivery = ParcelDelivery::sole();
        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.start', $delivery->id))->assertSessionHas('success');
        $otp = $delivery->fresh()->otp_code;
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertSee($otp);
        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.deliver', $delivery->id), ['tracking_number' => $delivery->parcel->tracking_number, 
            'pod_photo' => UploadedFile::fake()->image('pod_photo.jpg'), 'otp' => $otp, 'cod_collected' => '490.00',
        ])->assertSessionHas('success');

        $this->assertSame(Order::DELIVERED, $order->fresh()->status);
        $wallet = app(RiderWallet::class);
        $this->assertEquals(80, $wallet->pay($this->rider1, now()->startOfDay(), now()->endOfDay()));   // ₱30 pickup + ₱50 delivery
        $this->assertEquals(490, $wallet->codBalance($this->rider1));
    }

    public function test_the_seller_chooses_which_logistics_company_picks_up(): void
    {
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $this->actingAs($this->seller1)->post(route('seller.orders.accept', $order->id));
        $this->actingAs($this->seller1)->post(route('seller.orders.pack', $order->id));

        $this->actingAs($this->seller1)->post(route('seller.orders.handover', $order->id), ['pickup_scheduled_at' => now()->addHour()->format('Y-m-d H:i')])
            ->assertSessionHasErrors(['sorting_center_id' => 'Choose the logistics company whose rider will pick up the parcel.']);
        $this->actingAs($this->seller1)->post(route('seller.orders.handover', $order->id), ['sorting_center_id' => $this->buyer2->id, 'pickup_scheduled_at' => now()->addHour()->format('Y-m-d H:i')])
            ->assertSessionHasErrors('sorting_center_id');

        // Agila Cargo doesn't cover Quezon City, but the seller may still choose it; its riders get the job.
        $this->online($this->agilaRider);
        $this->actingAs($this->seller1)->post(route('seller.orders.handover', $order->id), ['sorting_center_id' => $this->logistics2->id, 'pickup_scheduled_at' => now()->addHour()->format('Y-m-d H:i')])
            ->assertSessionHas('success');
        $parcel = Parcel::where('order_id', $order->id)->sole();
        $this->assertSame($this->logistics2->id, (int) $parcel->current_sorting_center_id);
        $this->assertSame('pending_pickup', $parcel->status);   // Agila confirms the request, then assigns one of its riders
        $this->assertFalse(Delivery::where('order_id', $order->id)->exists());
        $this->actingAs($this->logistics2)->get(route('sc.pickup-requests'))->assertSee($parcel->tracking_number);
    }
}
