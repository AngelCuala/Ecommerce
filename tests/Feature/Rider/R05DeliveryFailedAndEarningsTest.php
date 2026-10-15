<?php

namespace Tests\Feature\Rider;

use App\Models\Order;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\ParcelPickup;
use App\Models\RiderRemittance;
use App\Models\UserNotification;
use App\Services\RiderWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Rider workbook R5 (Delivery & Status Updates), R6 (Failed Delivery) and R7 (History, Earnings & COD Remittance). */
class R05DeliveryFailedAndEarningsTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
    }

    private function deliver(ParcelDelivery $d, array $over = [], $rider = null)
    {
        return $this->actingAs(($rider ?? $this->rider1)->user)->from(route('courier.rider.deliveries.show', $d->id))
            ->post(route('courier.rider.deliveries.deliver', $d->id), array_merge([
                'pod_photo' => UploadedFile::fake()->image('pod_photo.jpg')->size(1100), 'otp' => $d->fresh()->otp_code, 'cod_collected' => '500.00',
            ], $over));
    }

    private function markFailed(ParcelDelivery $d, ?string $reason)
    {
        return $this->actingAs($this->rider1->user)->from(route('courier.rider.deliveries.show', $d->id))
            ->post(route('courier.rider.deliveries.fail', $d->id), array_filter(['reason' => $reason]));
    }

    private function wallet(): RiderWallet
    {
        return app(RiderWallet::class);
    }

    // ── R5 Delivery & Status Updates ────────────────────────────

    /** RID-DLV-001 (A10, A32, A33) */
    public function test_rid_dlv_001_deliver_with_proof_in_sequence(): void
    {
        $d = $this->deliveryFor($this->rider1);   // At Hub, assigned to Rider 1
        $order = $d->parcel->order;
        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.start', $d->id))->assertSessionHas('success');
        $d->refresh();
        $this->assertSame('out_for_delivery', $d->status);
        $this->assertMatchesRegularExpression('/^\d{6}$/', $d->otp_code);
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertSee('data-delivery-otp', false)->assertSee($d->otp_code);

        $this->deliver($d)->assertSessionHas('success');
        $d->refresh();
        $this->assertSame('delivered', $d->status);
        $this->assertNotNull($d->delivered_at);
        $this->assertNotNull($d->picked_up_at);
        $this->assertSame('delivered', $d->parcel->status);
        $this->assertSame(Order::DELIVERED, $order->fresh()->status);
        $this->assertSame('Paid', $order->fresh()->payment_status);

        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertSee('data-proof-of-delivery', false)->assertSee($d->pod_path);
        $this->assertEquals(500, $this->wallet()->codBalance($this->rider1));
        $this->assertEquals(50, $this->wallet()->pay($this->rider1, now()->startOfDay(), now()->endOfDay()));
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'Parcel delivered')->exists());
        $this->actingAs($this->logistics1)->get(route('sc.delivery-monitoring'))->assertOk()->assertSee($d->parcel->tracking_number);
    }

    /** RID-DLV-002 (A10) */
    public function test_rid_dlv_002_cannot_skip_a_status(): void
    {
        $d = $this->deliveryFor($this->rider1);
        $this->actingAs($this->rider1->user)->get(route('courier.rider.deliveries.show', $d->id))->assertSee('Mark Out for Delivery')->assertDontSee('>Delivered</button>', false);
        $this->deliver($d, ['otp' => '123456'])->assertSessionHas('error', 'Mark the parcel Out for Delivery first.');
        $this->assertSame('assigned', $d->fresh()->status);
    }

    /** RID-DLV-003 (A16, A32) */
    public function test_rid_dlv_003_photo_is_required(): void
    {
        $d = $this->deliveryFor($this->rider1, 'out_for_delivery');
        $this->deliver($d, ['pod_photo' => null])->assertSessionHasErrors(['pod_photo' => 'Take a proof-of-delivery photo.']);
        $this->deliver($d, ['pod_photo' => UploadedFile::fake()->image('size_5.1MB.jpg')->size(5222)])->assertSessionHasErrors(['pod_photo' => 'The photo must be 5 MB or smaller.']);
        $this->assertSame('out_for_delivery', $d->fresh()->status);
    }

    /** RID-DLV-004 (A32) */
    public function test_rid_dlv_004_otp_checks(): void
    {
        $d = $this->deliveryFor($this->rider1, 'out_for_delivery');
        $wrong = $d->otp_code === '000000' ? '111111' : '000000';

        $this->deliver($d, ['otp' => '12345'])->assertSessionHasErrors(['otp' => 'The OTP has exactly 6 digits.']);
        for ($i = 1; $i <= 4; $i++) {
            $this->deliver($d, ['otp' => $wrong])->assertSessionHasErrors(['otp' => 'Wrong OTP. ' . (5 - $i) . ' ' . Str::plural('try', 5 - $i) . ' left.']);
        }
        $this->deliver($d, ['otp' => $wrong])->assertSessionHasErrors(['otp' => 'Wrong OTP. OTP entry is now locked for 15 minutes.']);
        $this->deliver($d)->assertSessionHasErrors('otp');   // even the right OTP is refused while locked
        $this->assertSame('out_for_delivery', $d->fresh()->status);

        $this->travel(16)->minutes();
        $this->deliver($d)->assertSessionHas('success');
        $this->assertSame('delivered', $d->fresh()->status);
    }

    /** RID-DLV-005 (A24) */
    public function test_rid_dlv_005_cod_amount_must_match(): void
    {
        $d = $this->deliveryFor($this->rider1, 'out_for_delivery');
        foreach (['499.99', '500.01'] as $amount) {
            $this->deliver($d, ['cod_collected' => $amount])->assertSessionHasErrors(['cod_collected' => 'The COD amount doesn\'t match. Collect exactly ₱500.00.']);
        }
        $this->assertSame('out_for_delivery', $d->fresh()->status);
        $this->deliver($d, ['cod_collected' => '500.00'])->assertSessionHas('success');
        $this->assertEquals(500, $d->fresh()->cod_collected);
    }

    /** RID-DLV-006 */
    public function test_rid_dlv_006_prepaid_order_has_no_cod(): void
    {
        $parcel = $this->parcel('sorted');
        $parcel->order->update(['payment_method' => 'GCASH', 'payment_status' => 'Paid']);
        $d = $this->deliveryFor($this->rider1, 'out_for_delivery', $parcel);

        $this->actingAs($this->rider1->user)->get(route('courier.rider.deliveries.show', $d->id))
            ->assertSee('₱0.00 · Paid (no cash to collect)')->assertDontSee('name="cod_collected"', false);
        $this->deliver($d, ['cod_collected' => null])->assertSessionHas('success');
        $this->assertEquals(0, $this->wallet()->codBalance($this->rider1));
    }

    /** RID-DLV-007 (A22) */
    public function test_rid_dlv_007_cannot_update_another_riders_job(): void
    {
        $d = $this->deliveryFor($this->rider2, 'out_for_delivery');
        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.start', $d->id))->assertNotFound();
        $this->deliver($d, [], $this->rider1)->assertNotFound();
        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.fail', $d->id), ['reason' => 'Refused'])->assertNotFound();
        $this->assertSame('out_for_delivery', $d->fresh()->status);
    }

    // ── R6 Failed Delivery ──────────────────────────────────────

    /** RID-FAIL-001 (A13, A23, A33) */
    public function test_rid_fail_001_mark_failed_with_a_reason(): void
    {
        $d = $this->deliveryFor($this->rider1, 'out_for_delivery');
        $this->markFailed($d, 'Buyer not home')->assertSessionHas('success', 'Failed delivery recorded (attempt 1 of 3). Logistics will reschedule it.');

        $d->refresh();
        $this->assertSame(['failed', 'Buyer not home', 1], [$d->status, $d->failure_reason, $d->attempt]);
        $this->assertSame('failed', $d->parcel->status);
        $this->assertTrue(UserNotification::where('user_id', $this->buyer1->id)->where('title', 'Delivery attempt failed')->where('body', 'like', '%attempt 1 of 3%')->exists());
        $this->assertTrue(UserNotification::where('user_id', $this->logistics1->id)->where('title', 'Failed delivery')->exists());
        $this->assertEquals(0, $d->cod_collected);
        $this->assertEquals(0, $this->wallet()->pay($this->rider1, now()->startOfDay(), now()->endOfDay()));
        $this->actingAs($this->logistics1)->get(route('sc.delivery-monitoring'))->assertSee($d->parcel->tracking_number);
    }

    /** RID-FAIL-002 (A13) */
    public function test_rid_fail_002_reason_required(): void
    {
        $d = $this->deliveryFor($this->rider1, 'out_for_delivery');
        $this->markFailed($d, null)->assertSessionHasErrors(['reason' => 'Choose why the delivery failed.']);
        $this->markFailed($d, 'Raining')->assertSessionHasErrors(['reason' => 'Choose a reason from the list.']);
        $this->assertSame('out_for_delivery', $d->fresh()->status);
    }

    /** RID-FAIL-003 (A13) */
    public function test_rid_fail_003_refused_goes_straight_to_return_to_sender(): void
    {
        $d = $this->deliveryFor($this->rider1, 'out_for_delivery');
        $this->markFailed($d, 'Refused')->assertSessionHas('success', fn ($m) => str_contains($m, 'Return to Sender'));
        $parcel = $d->parcel->fresh();
        $this->assertSame('return_to_sender', $parcel->status);
        $this->assertSame('Return to Sender', $parcel->statusLabel());
        $this->actingAs($this->logistics1)->post(route('sc.deliveries.reschedule', $d->id))->assertSessionHas('error');
        $this->assertSame('return_to_sender', $parcel->fresh()->status);
    }

    /** RID-FAIL-004 (A13) */
    public function test_rid_fail_004_attempt_counter_and_reschedule(): void
    {
        $this->online($this->rider1);
        $first = $this->deliveryFor($this->rider1, 'out_for_delivery');
        $this->markFailed($first, 'Buyer not home');

        $this->actingAs($this->logistics1)->post(route('sc.deliveries.reschedule', $first->id))->assertSessionHas('success');
        $parcel = $first->parcel->fresh();
        $this->assertSame('sorted', $parcel->status);
        $this->actingAs($this->logistics1)->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider1->id])->assertSessionHas('success');

        $second = ParcelDelivery::where('parcel_id', $parcel->id)->latest('id')->first();
        $this->assertSame(2, $second->attempt);
        $this->actingAs($this->rider1->user)->get(route('courier.rider.deliveries.show', $second->id))->assertSee('attempt 2 of 3');
        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.start', $second->id));
        $this->deliver($second)->assertSessionHas('success');
        $this->assertSame('delivered', $parcel->fresh()->status);
    }

    /** RID-FAIL-005 (A13) */
    public function test_rid_fail_005_return_to_sender_after_the_third_attempt(): void
    {
        $this->online($this->rider1);
        $parcel = $this->parcel('sorted');
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $d = $this->deliveryFor($this->rider1, 'out_for_delivery', $parcel->fresh());
            $this->assertSame($attempt, $d->attempt);
            $this->markFailed($d, $attempt < 3 ? 'Buyer not home' : 'Wrong address');
            if ($attempt < 3) {
                $this->actingAs($this->logistics1)->post(route('sc.deliveries.reschedule', $d->id))->assertSessionHas('success');
            }
        }

        $this->assertSame('return_to_sender', $parcel->fresh()->status);
        $this->actingAs($this->logistics1)->post(route('sc.deliveries.reschedule', $d->id))->assertSessionHas('error');
        $this->assertNull(app(\App\Services\RiderDispatch::class)->jobKind($parcel->fresh()));   // no 4th attempt
        $this->assertTrue(UserNotification::where('user_id', $this->buyer1->id)->where('title', 'Returning to seller')->exists());
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'Parcel returning to you')->exists());
    }

    // ── R7 History, Earnings & COD Remittance ───────────────────

    /** Delivered COD job with the given amount, done today. */
    private function deliveredCod(float $cod): ParcelDelivery
    {
        $parcel = $this->parcel('delivered');
        return ParcelDelivery::create(['parcel_id' => $parcel->id, 'rider_id' => $this->rider1->id, 'status' => 'delivered',
            'delivered_at' => now(), 'cod_amount' => $cod, 'cod_collected' => $cod]);
    }

    /** RID-EARN-001 */
    public function test_rid_earn_001_delivery_history(): void
    {
        $ok = $this->deliveryFor($this->rider1, 'out_for_delivery');
        $this->deliver($ok);
        $bad = $this->deliveryFor($this->rider1, 'out_for_delivery');
        $this->markFailed($bad, 'Buyer not home');
        $old = $this->deliveredCod(0);
        $old->forceFill(['delivered_at' => now()->subDays(10)])->save();

        $page = $this->actingAs($this->rider1->user)->get(route('courier.rider.history'))->assertOk();
        foreach ([$ok, $bad, $old] as $d) {
            $this->assertSame(1, substr_count($page->getContent(), 'data-history="' . $d->parcel->tracking_number . '"'));
        }
        $page->assertSee('Delivered')->assertSee('Failed (Buyer not home)')->assertSee('₱50.00')->assertSee('₱0.00');

        $filtered = $this->actingAs($this->rider1->user)->get(route('courier.rider.history', ['from' => now()->toDateString(), 'to' => now()->toDateString()]))->getContent();
        $this->assertStringNotContainsString($old->parcel->tracking_number, $filtered);
    }

    /** RID-EARN-002 (A33) */
    public function test_rid_earn_002_pay_per_job_and_daily_total(): void
    {
        foreach (range(1, 2) as $i) {
            ParcelPickup::create(['parcel_id' => $this->parcel()->id, 'rider_id' => $this->rider1->id, 'status' => 'received', 'picked_up_at' => now(), 'received_at' => now()]);
        }
        foreach (range(1, 3) as $i) {
            $this->deliveredCod(0);
        }
        ParcelDelivery::create(['parcel_id' => $this->parcel('failed')->id, 'rider_id' => $this->rider1->id, 'status' => 'failed', 'failure_reason' => 'Buyer not home']);
        // Last week's job counts in history, not in today's or this week's pay.
        $this->deliveredCod(0)->forceFill(['delivered_at' => now()->subWeeks(2)])->save();

        $this->actingAs($this->rider1->user)->get(route('courier.rider.earnings'))->assertOk()->assertSee('data-today-pay', false);
        $this->assertEquals(210, $this->wallet()->pay($this->rider1, now()->startOfDay(), now()->endOfDay()));   // 2 × 30 + 3 × 50 + 0
        $this->assertGreaterThanOrEqual(210, $this->wallet()->pay($this->rider1, now()->startOfWeek(), now()->endOfWeek()));
        $this->actingAs($this->rider1->user)->get(route('courier.rider.earnings'))->assertSee('₱210.00');
    }

    /** RID-EARN-003 (A24) */
    public function test_rid_earn_003_cod_balance_to_remit(): void
    {
        $a = $this->deliveredCod(500);
        $b = $this->deliveredCod(1300);   // P-03 ₱1,250 + ₱50 shipping

        $this->assertEquals(1800, $this->wallet()->codBalance($this->rider1));
        $this->actingAs($this->rider1->user)->get(route('courier.rider.cod'))->assertOk()->assertSee('₱1,800.00')
            ->assertSee('data-cod-order="' . $a->parcel->tracking_number . '"', false)->assertSee('data-cod-order="' . $b->parcel->tracking_number . '"', false);
    }

    /** RID-EARN-004 (A24) */
    public function test_rid_earn_004_remit_cod_and_confirmation(): void
    {
        $this->deliveredCod(500);
        $this->deliveredCod(1300);
        $this->actingAs($this->rider1->user)->post(route('courier.rider.cod.remit'), ['amount' => '1800.00', 'request_token' => (string) Str::uuid()])->assertSessionHas('success');

        $remit = RiderRemittance::sole();
        $this->assertSame('pending', $remit->status);
        $this->assertEquals(1800, $this->wallet()->codBalance($this->rider1));
        $this->assertEquals(1800, $this->wallet()->pendingRemittance($this->rider1));

        $this->actingAs($this->logistics1)->post(route('sc.remittances.confirm', $remit))->assertSessionHas('success', 'Remittance confirmed.');
        $this->assertEquals(0, $this->wallet()->codBalance($this->rider1));
        $this->actingAs($this->rider1->user)->get(route('courier.rider.cod'))->assertSee('Confirmed');

        // Sending the same form twice creates one remittance only (A37).
        $this->deliveredCod(100);
        $token = (string) Str::uuid();
        $this->actingAs($this->rider1->user)->post(route('courier.rider.cod.remit'), ['amount' => '100.00', 'request_token' => $token]);
        $this->actingAs($this->rider1->user)->post(route('courier.rider.cod.remit'), ['amount' => '100.00', 'request_token' => $token])
            ->assertSessionHas('success', 'Your remittance was already sent.');
        $this->assertSame(2, RiderRemittance::count());
    }

    /** RID-EARN-005 (A24) */
    public function test_rid_earn_005_short_remittance(): void
    {
        $this->deliveredCod(500);
        $this->deliveredCod(1300);
        $this->actingAs($this->rider1->user)->post(route('courier.rider.cod.remit'), ['amount' => '1750.00', 'request_token' => (string) Str::uuid()])->assertSessionHas('success');
        $remit = RiderRemittance::sole();
        $this->assertEquals(50, $remit->shortfall());

        $this->actingAs($this->logistics1)->get(route('sc.remittances'))->assertOk()->assertSee('data-shortfall', false)->assertSee('Short ₱50.00');
        $this->actingAs($this->logistics1)->post(route('sc.remittances.confirm', $remit))->assertSessionHas('success', fn ($m) => str_contains($m, 'Shortfall of ₱50.00'));
        $this->assertEquals(50, $this->wallet()->codBalance($this->rider1));
        $this->actingAs($this->rider1->user)->get(route('courier.rider.cod'))->assertSee('₱50.00');
    }

    /** RID-EARN-006 (A22, A24) */
    public function test_rid_earn_006_rider_cannot_confirm_own_remittance(): void
    {
        $this->deliveredCod(500);
        $this->actingAs($this->rider1->user)->post(route('courier.rider.cod.remit'), ['amount' => '500.00', 'request_token' => (string) Str::uuid()]);
        $remit = RiderRemittance::sole();

        $this->actingAs($this->rider1->user)->post(route('sc.remittances.confirm', $remit))->assertForbidden();
        $this->actingAs($this->logistics2)->post(route('sc.remittances.confirm', $remit))->assertForbidden();   // another company
        $this->assertSame('pending', $remit->fresh()->status);
    }
}
