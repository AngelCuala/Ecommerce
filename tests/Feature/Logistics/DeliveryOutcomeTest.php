<?php

namespace Tests\Feature\Logistics;

use App\Models\Order;
use App\Models\ShipmentEvent;
use App\Support\ApiJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Buyer not home → rescheduled automatically; wrong address → back to the seller; buyer confirms → "Received by Buyer". */
class DeliveryOutcomeTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
    }

    public function test_buyer_not_home_is_rescheduled_at_once(): void
    {
        $parcel = $this->parcel('sorted');
        $d = $this->deliveryFor($this->rider1, 'out_for_delivery', $parcel);

        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.fail', $d->id), ['reason' => 'Buyer not home'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'rescheduled'));

        $this->assertSame('failed', $d->fresh()->status);
        $this->assertSame('sorted', $parcel->fresh()->status);   // back in the queue for the next attempt
        $this->assertTrue($this->dispatcher()->waiting($this->logistics1->id)->contains('id', $parcel->id));
        $this->assertTrue(ShipmentEvent::where('parcel_id', $parcel->id)->where('event_type', 'delivery_rescheduled')->exists());
    }

    public function test_wrong_address_returns_the_parcel_to_the_seller(): void
    {
        $parcel = $this->parcel('sorted');
        $d = $this->deliveryFor($this->rider1, 'out_for_delivery', $parcel);

        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.fail', $d->id), ['reason' => 'Wrong address'])
            ->assertSessionHas('success');

        $this->assertSame('return_to_sender', $parcel->fresh()->status);
        $this->assertFalse($this->dispatcher()->waiting($this->logistics1->id)->contains('id', $parcel->id));
        $this->actingAs($this->logistics1)->get(route('sc.rts'))->assertOk()->assertSee($parcel->tracking_number);
    }

    public function test_received_by_buyer_after_order_received(): void
    {
        $parcel = $this->parcel('sorted');
        $d = $this->deliveryFor($this->rider1, 'out_for_delivery', $parcel);
        $parcel->fresh()->update(['status' => 'delivered']);
        $d->update(['status' => 'delivered', 'delivered_at' => now()]);
        app(\App\Services\ParcelService::class)->syncOrder($parcel->fresh());
        $order = $parcel->order->fresh();
        $this->assertSame(Order::DELIVERED, $order->status);

        $track = ApiJson::tracking($order);
        $this->assertSame(4, $track['reached']);
        $this->assertSame('Delivered', $parcel->fresh()->statusLabel());

        $this->actingAs($order->user)->post(route('orders.confirm-delivery', $order->id))->assertSessionHas('success');

        $order = $order->fresh();
        $this->assertSame('Received by Buyer', $parcel->fresh()->statusLabel());
        $this->assertTrue(ShipmentEvent::where('parcel_id', $parcel->id)->where('event_type', 'received_by_buyer')->exists());
        $track = ApiJson::tracking($order);
        $this->assertSame(5, $track['reached']);
        $this->assertSame('Received', $track['milestones'][5]);

        // The seller's order page shows it on the progress line.
        $this->actingAs($order->seller)->get(route('seller.orders.show', $order->id))->assertOk()->assertSee('Received by buyer');
    }
}
