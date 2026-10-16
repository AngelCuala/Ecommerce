<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ParcelDelivery;
use App\Models\UserNotification;
use App\Services\RiderWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/**
 * Logic that must hold however a step is recorded (rider app, logistics override, admin override,
 * the Delivery Assignment page): order status, COD money, delivery attempts and commission.
 */
class SystemLogicTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    private const REASON = 'Rider app offline, delivery confirmed by phone.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
    }

    private function scOverride(ParcelDelivery $d, string $status, string $reason = self::REASON)
    {
        return $this->actingAs($this->logistics1)->from(route('sc.delivery-monitoring'))
            ->post(route('sc.deliveries.update-status', $d), ['status' => $status, 'remarks' => $reason]);
    }

    public function test_logistics_override_delivers_the_order_and_counts_the_cod_cash(): void
    {
        $d = $this->deliveryFor($this->rider1);   // ₱500.00 COD
        $this->scOverride($d, 'out_for_delivery')->assertSessionHas('success');

        // The buyer gets an OTP the rider can use.
        $otp = $d->fresh()->otp_code;
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $d->parcel->order_id))->assertSee($otp);

        $this->scOverride($d, 'delivered')->assertSessionHas('success');
        $order = $d->parcel->order->fresh();
        $this->assertSame(Order::DELIVERED, $order->status);   // not stuck at Shipped
        $this->assertSame('Paid', $order->payment_status);
        $this->assertNotNull($order->delivered_at);
        $this->assertEquals(500, (float) $d->fresh()->cod_collected);
        $this->assertEquals(500, app(RiderWallet::class)->codBalance($this->rider1));

        // So it completes and pays the seller like any delivered order.
        $this->actingAs($this->buyer1)->post(route('orders.confirm-delivery', $order->id))->assertSessionHas('success');
        $this->assertSame(Order::COMPLETED, $order->fresh()->status);
    }

    public function test_an_overridden_out_for_delivery_job_can_be_finished_by_the_rider(): void
    {
        $d = $this->deliveryFor($this->rider1);
        $this->scOverride($d, 'out_for_delivery');

        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.deliver', $d->id), [
            'pod_photo' => $this->jpg('pod_photo.jpg', 300), 'otp' => $d->fresh()->otp_code, 'cod_collected' => '500.00',
        ])->assertSessionHas('success');
        $this->assertSame(Order::DELIVERED, $d->parcel->order->fresh()->status);
    }

    public function test_override_of_the_last_failed_attempt_sends_the_parcel_back(): void
    {
        $parcel = $this->parcel('sorted');
        foreach ([1, 2] as $n) {
            $parcel->deliveries()->create(['rider_id' => $this->rider1->id, 'area_id' => $parcel->area_id, 'status' => 'failed', 'attempt' => $n, 'failure_reason' => 'Buyer not home']);
        }
        $third = $this->deliveryFor($this->rider1, 'assigned', $parcel->fresh());
        $this->assertSame(3, (int) $third->attempt);

        $this->scOverride($third, 'failed', 'Buyer not home for the third time.')->assertSessionHas('success');
        $this->assertSame('return_to_sender', $parcel->fresh()->status);
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'Parcel returning to you')->exists());
    }

    public function test_delivery_assignment_page_job_carries_cod_and_attempt(): void
    {
        $parcel = $this->parcel('sorted');
        $this->actingAs($this->logistics1)->from(route('sc.delivery-assignment'))
            ->post(route('sc.parcels.assign', $parcel), ['rider_id' => $this->rider1->id])->assertSessionHas('success');

        $d = ParcelDelivery::where('parcel_id', $parcel->id)->sole();
        $this->assertSame([1, 500.0], [(int) $d->attempt, (float) $d->cod_amount]);

        // The rider must collect the cash before it counts as delivered.
        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.start', $d->id));
        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.deliver', $d->id), [
            'pod_photo' => $this->jpg('pod_photo.jpg', 300), 'otp' => $d->fresh()->otp_code,
        ])->assertSessionHasErrors('cod_collected');
        $this->assertSame('out_for_delivery', $d->fresh()->status);
    }

    public function test_admin_override_follows_the_same_rules(): void
    {
        $admin = $this->account('admin', 'ops.admin@example.com', 'Ops Admin');
        $d = $this->deliveryFor($this->rider1, 'out_for_delivery');
        $url = route('admin.logistics.deliveries.update-status', $d);

        $this->actingAs($admin)->from(route('admin.logistics.deliveries.monitor'))->post($url, ['status' => 'delivered'])->assertSessionHasErrors('remarks');
        $this->actingAs($admin)->from(route('admin.logistics.deliveries.monitor'))->post($url, ['status' => 'assigned', 'remarks' => self::REASON])->assertSessionHasErrors('status');
        $this->assertSame('out_for_delivery', $d->fresh()->status);

        $this->actingAs($admin)->post($url, ['status' => 'delivered', 'remarks' => self::REASON])->assertSessionHas('success');
        $this->assertSame([Order::DELIVERED, 'Paid'], [$d->parcel->order->fresh()->status, $d->parcel->order->fresh()->payment_status]);
        $log = \App\Models\ActivityLog::where('description', 'Admin override on ' . $d->parcel->tracking_number)->sole();
        $this->assertSame(['Out for Delivery', 'Delivered', self::REASON], [$log->old_value, $log->new_value, $log->reason]);

        // A finished delivery can't be moved again.
        $this->actingAs($admin)->post($url, ['status' => 'failed', 'remarks' => self::REASON])->assertSessionHas('error');
        $this->assertSame('delivered', $d->fresh()->status);
    }

    public function test_admin_assign_only_for_a_sorted_parcel_and_an_active_rider(): void
    {
        $admin = $this->account('admin', 'ops.admin@example.com', 'Ops Admin');
        $atHub = $this->parcel('picked_up');
        $this->actingAs($admin)->post(route('admin.logistics.deliveries.assign', $atHub), ['rider_id' => $this->rider1->id])->assertSessionHas('error');

        $sorted = $this->parcel('sorted');
        $this->actingAs($admin)->post(route('admin.logistics.deliveries.assign', $sorted), ['rider_id' => $this->rider3->id])->assertSessionHas('error');   // suspended
        $this->actingAs($admin)->post(route('admin.logistics.deliveries.assign', $sorted), ['rider_id' => $this->agilaRider->id])->assertSessionHas('error'); // other company
        $this->actingAs($admin)->post(route('admin.logistics.deliveries.assign', $sorted), ['rider_id' => $this->rider1->id])->assertSessionHas('success');
        $this->actingAs($admin)->post(route('admin.logistics.deliveries.assign', $sorted), ['rider_id' => $this->rider2->id])->assertSessionHas('error');   // already assigned

        $this->assertSame(1, ParcelDelivery::where('parcel_id', $sorted->id)->count());
        $this->assertEquals(500, (float) ParcelDelivery::where('parcel_id', $sorted->id)->sole()->cod_amount);
        $this->assertSame(0, ParcelDelivery::where('parcel_id', $atHub->id)->count());
    }

    public function test_commission_is_rounded_once_per_order(): void
    {
        // Three ₱1.10 items: 5% of each is ₱0.055 (₱0.06 rounded on its own, ₱0.18 together),
        // but 5% of the order's ₱3.30 is ₱0.165 → ₱0.17 (A9: rounded per order).
        $lines = collect(['Abaca Coaster', 'Buri Coaster', 'Capiz Coaster'])
            ->map(fn ($t) => $this->cart($this->buyer1, $this->product($this->seller1, $this->homeLiving, $t, 1.10, 10, 0.1)));
        $order = $this->placeOrder($this->buyer1, $lines->all())->first();

        $items = OrderItem::where('order_id', $order->id)->get();
        $this->assertEquals(0.17, round($items->sum('commission_amount'), 2));
        $this->assertEquals(3.13, round($items->sum('seller_earning'), 2));   // 3.30 − 0.17
        foreach ($items as $item) {
            $this->assertEquals(round($item->price * $item->quantity - $item->commission_amount, 2), (float) $item->seller_earning);
        }
    }

    public function test_lost_unpaid_cod_parcel_tells_the_buyer_nothing_is_charged(): void
    {
        $parcel = $this->parcel('in_transit');
        $this->actingAs($this->logistics1)->post(route('sc.parcels.exception', $parcel), ['type' => 'lost', 'reason' => 'Parcel missing after the hub handover.'])
            ->assertSessionHas('success');

        $note = UserNotification::where('user_id', $this->buyer1->id)->where('title', 'Parcel lost')->sole();
        $this->assertStringContainsString("you won't be charged", $note->body);
        $this->assertStringNotContainsString('refund', $note->body);
    }

    public function test_full_cod_return_records_the_refund(): void
    {
        $order = $this->moveTo($this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first(), Order::DELIVERED);
        $order->update(['payment_status' => 'Paid']);
        $this->actingAs($this->buyer1)->post(route('orders.return', $order->id), [
            'reason' => 'Damaged item', 'details' => 'The basket arrived crushed.', 'evidence' => $this->jpg('review_photo.jpg', 300),
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->seller1)->post(route('seller.orders.return', $order->id), ['decision' => 'approve'])->assertSessionHas('success');
        $this->assertSame(Order::REFUNDED, $order->fresh()->status);
        $this->assertEquals(500, (float) $order->fresh()->refunded_amount);
    }
}
