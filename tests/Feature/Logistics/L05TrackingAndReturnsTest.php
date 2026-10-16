<?php

namespace Tests\Feature\Logistics;

use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Parcel;
use App\Models\Payment;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Logistics workbook L5 (Shipment Tracking & Exceptions) and L6 (Returns / Return-to-Sender). */
class L05TrackingAndReturnsTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    private const OVERRIDE_REASON = 'Rider app offline, delivery confirmed by phone.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
    }

    private function search(string $q)
    {
        return $this->actingAs($this->logistics1)->get(route('sc.shipments', ['q' => $q]))->assertOk();
    }

    private function override($delivery, string $reason)
    {
        return $this->actingAs($this->logistics1)->from(route('sc.delivery-monitoring'))
            ->post(route('sc.deliveries.update-status', $delivery), ['status' => 'delivered', 'remarks' => $reason]);
    }

    private function exception(Parcel $parcel, array $data)
    {
        return $this->actingAs($this->logistics1)->from(route('sc.shipments'))->post(route('sc.parcels.exception', $parcel), $data);
    }

    // ── L5 Shipment Tracking & Exceptions ───────────────────────

    /** LOG-TRK-001 */
    public function test_log_trk_001_search_by_tracking_order_or_buyer(): void
    {
        $juan = $this->parcel('in_transit');
        $ana  = $this->parcel('in_transit', [], $this->placeOrder($this->buyer2, [$this->cart($this->buyer2, $this->p02)])->first());

        foreach ([$juan->tracking_number, $juan->order->number(), (string) $juan->order_id, 'Juan Miguel Dela Cruz'] as $q) {
            $found = $this->search($q)->viewData('parcels');
            $this->assertSame([$juan->id], $found->pluck('id')->all(), "Search for '{$q}'");
        }
        $this->assertSame([$ana->id], $this->search($ana->tracking_number)->viewData('parcels')->pluck('id')->all());

        foreach (['TRK-0000000', "' OR '1'='1", '<script>alert(1)</script>'] as $q) {
            $res = $this->search($q)->assertSee('No shipments found.');
            $this->assertSame(0, $res->viewData('parcels')->total(), "Search for '{$q}'");
        }
        $this->search('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false);
    }

    /** LOG-TRK-002 */
    public function test_log_trk_002_manual_override_needs_a_reason(): void
    {
        $delivery = $this->deliveryFor($this->rider1, 'out_for_delivery');

        $this->override($delivery, '')->assertSessionHasErrors(['remarks' => 'This is a logistics override: give the reason (10 to 300 characters).']);
        $this->assertSame('in_transit', $delivery->parcel->fresh()->status);

        $this->override($delivery, self::OVERRIDE_REASON)->assertSessionHas('success');
        $this->assertSame('Delivered', $delivery->parcel->fresh()->statusLabel());
        $this->assertSame('delivered', $delivery->fresh()->status);
    }

    /** LOG-TRK-003 */
    public function test_log_trk_003_override_leaves_a_read_only_audit_entry(): void
    {
        $this->travelTo(now()->setTime(14, 5));
        $delivery = $this->deliveryFor($this->rider1, 'out_for_delivery');
        $this->override($delivery, self::OVERRIDE_REASON)->assertSessionHas('success');

        $log = ActivityLog::where('description', 'Logistics override on ' . $delivery->parcel->tracking_number)->sole();
        $this->assertSame([$this->logistics1->id, 'Out for Delivery', 'Delivered', self::OVERRIDE_REASON],
            [(int) $log->admin_id, $log->old_value, $log->new_value, $log->reason]);
        $this->assertSame(now()->format('Y-m-d H:i'), $log->created_at->format('Y-m-d H:i'));

        $this->actingAs($this->logistics1)->get(route('sc.activity'))->assertOk()
            ->assertSee('Carlos Antonio Mendoza')->assertSee('Out for Delivery → Delivered')->assertSee(self::OVERRIDE_REASON)
            ->assertSee(now()->format('M j, Y g:i A'))->assertDontSee('>Edit<', false)->assertDontSee('>Delete<', false);

        // No route can change or remove a log entry.
        foreach (Route::getRoutes() as $route) {
            if (str_contains($route->uri(), 'activity')) {
                $this->assertSame(['GET', 'HEAD'], $route->methods(), $route->uri() . ' must be read only');
            }
        }
        $this->actingAs($this->logistics1)->delete('/sc/activity/' . $log->id)->assertNotFound();
        $this->assertNotNull($log->fresh());
    }

    /** LOG-TRK-004 */
    public function test_log_trk_004_mark_a_parcel_lost(): void
    {
        $parcel = $this->parcel('in_transit');
        $reason = 'Parcel missing after the hub-to-rider handover.';

        $this->exception($parcel, ['type' => 'lost', 'reason' => $reason])->assertSessionHas('success');
        $this->assertSame(['Lost', $reason], [$parcel->fresh()->statusLabel(), $parcel->fresh()->failure_reason]);
        $this->assertSame(Order::REFUNDED, $parcel->order->fresh()->status);   // refund / claim started
        $this->assertTrue(UserNotification::where('user_id', $this->buyer1->id)->where('title', 'Parcel lost')->exists());
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'Parcel lost')->exists());
        $this->assertSame($reason, ActivityLog::where('description', $parcel->tracking_number . ' marked Lost')->sole()->reason);
        $this->assertSame(19, $this->p02->fresh()->stock);   // nothing comes back to stock
    }

    /** LOG-TRK-005 */
    public function test_log_trk_005_mark_damaged_needs_a_photo(): void
    {
        $parcel = $this->parcel('picked_up');
        $reason = 'Box crushed and wet when it arrived at the hub.';

        $this->exception($parcel, ['type' => 'damaged', 'reason' => $reason])->assertSessionHasErrors(['photo' => 'Add a photo of the damage.']);
        $this->assertSame('picked_up', $parcel->fresh()->status);
        $this->assertSame(0, UserNotification::where('title', 'Parcel damaged')->count());

        $this->exception($parcel, ['type' => 'damaged', 'reason' => $reason, 'photo' => UploadedFile::fake()->image('pod_photo.jpg')])->assertSessionHas('success');
        $parcel->refresh();
        $this->assertSame('Damaged', $parcel->statusLabel());
        Storage::disk('public')->assertExists($parcel->exception_photo_path);
        $this->assertTrue(UserNotification::where('user_id', $this->buyer1->id)->where('title', 'Parcel damaged')->exists());
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'Parcel damaged')->exists());
    }

    // ── L6 Returns (Return-to-Sender) ───────────────────────────

    /** A parcel that failed 3 delivery attempts (through the rider app). */
    private function failedThreeTimes(): Parcel
    {
        $parcel = $this->parcel('sorted');
        foreach (['Buyer not home', 'Wrong address', 'Buyer not home'] as $i => $reason) {
            $d = $this->deliveryFor($this->rider1, 'out_for_delivery', $parcel->fresh());
            $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.fail', $d->id), ['reason' => $reason])->assertSessionHas('success');
            if ($i < 2) {
                $this->actingAs($this->logistics1)->post(route('sc.deliveries.reschedule', $d))->assertSessionHas('success');
            }
        }
        return $parcel->fresh();
    }

    /** RTS parcel brought back to the seller by Rider 1. */
    private function handBack(Parcel $parcel): void
    {
        $this->online($this->rider1);
        $this->actingAs($this->logistics1)->from(route('sc.rts'))->post(route('sc.rts.assign', $parcel), ['rider_id' => $this->rider1->id])->assertSessionHas('success');
        $this->actingAs($this->rider1->user)->get(route('courier.rider'))->assertSee('data-return', false)->assertSee($parcel->tracking_number);
        $this->actingAs($this->rider1->user)->post(route('courier.rider.returns.returned', $parcel->id))->assertSessionHas('success');
    }

    /** Regression: suspending the rider frees the return; it can be given to another rider. */
    public function test_rts_return_goes_back_to_the_queue_when_its_rider_is_suspended(): void
    {
        $parcel = $this->parcel('return_to_sender', ['failure_reason' => 'Refused']);
        $this->online($this->rider1, $this->rider2);
        $this->actingAs($this->logistics1)->from(route('sc.rts'))->post(route('sc.rts.assign', $parcel), ['rider_id' => $this->rider1->id])->assertSessionHas('success');
        $this->actingAs($this->logistics1)->from(route('sc.rts'))->post(route('sc.rts.assign', $parcel), ['rider_id' => $this->rider1->id])->assertSessionHas('error');

        $this->actingAs($this->logistics1)->post(route('sc.riders.toggle', $this->rider1), ['reason' => 'Repeated late deliveries this week.'])->assertSessionHas('success');
        $this->assertNull($parcel->fresh()->rts_rider_id);
        $this->actingAs($this->rider1->user)->post(route('courier.rider.returns.returned', $parcel->id))->assertNotFound();
        $this->assertSame('return_to_sender', $parcel->fresh()->status);

        $this->actingAs($this->logistics1)->from(route('sc.rts'))->post(route('sc.rts.assign', $parcel), ['rider_id' => $this->rider2->id])->assertSessionHas('success');
        $this->assertSame($this->rider2->id, (int) $parcel->fresh()->rts_rider_id);
    }

    /** LOG-RTS-001 */
    public function test_log_rts_001_rts_queue_after_three_failed_attempts(): void
    {
        $parcel = $this->failedThreeTimes();
        $this->assertSame('Return to Sender', $parcel->statusLabel());

        $this->actingAs($this->logistics1)->get(route('sc.rts'))->assertOk()
            ->assertSee('data-rts="' . $parcel->tracking_number . '"', false)
            ->assertSeeInOrder(['Attempt 1: Buyer not home', 'Attempt 2: Wrong address', 'Attempt 3: Buyer not home']);
    }

    /** LOG-RTS-002 */
    public function test_log_rts_002_deliver_back_to_the_seller(): void
    {
        $parcel = $this->failedThreeTimes();
        $this->handBack($parcel);

        $this->assertSame('Returned to Seller', $parcel->fresh()->statusLabel());
        $order = $parcel->order->fresh();
        $this->assertSame(Order::CANCELLED, $order->status);
        $this->assertStringContainsString('Parcel returned to seller', $order->cancellation_reason);

        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertOk()->assertSee('Order cancelled');
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertOk()->assertSee('Returned, awaiting confirmation');
        $this->actingAs($this->logistics1)->get(route('sc.rts'))->assertOk()->assertDontSee('data-rts="' . $parcel->tracking_number . '"', false)
            ->assertSee($parcel->tracking_number);   // in the awaiting-confirmation list
    }

    /** LOG-RTS-003 */
    public function test_log_rts_003_stock_returns_only_when_the_seller_confirms(): void
    {
        $parcel = $this->parcel('return_to_sender', ['failure_reason' => 'Refused']);
        $this->assertSame(19, $this->p02->fresh()->stock);

        $this->handBack($parcel);
        $this->assertSame(19, $this->p02->fresh()->stock);

        $this->actingAs($this->seller1)->post(route('seller.orders.returned-received', $parcel->order_id))->assertSessionHas('success');
        $this->assertSame(20, $this->p02->fresh()->stock);
        $this->assertNotNull($parcel->fresh()->returned_received_at);

        // Confirming again changes nothing.
        $this->actingAs($this->seller1)->post(route('seller.orders.returned-received', $parcel->order_id))->assertSessionHas('error');
        $this->assertSame(20, $this->p02->fresh()->stock);
    }

    /** LOG-RTS-004 */
    public function test_log_rts_004_prepaid_rts_is_refunded_and_earns_nothing(): void
    {
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $order->update(['payment_method' => 'gcash', 'payment_status' => 'Paid']);
        Payment::create(['user_id' => $this->buyer1->id, 'checkout_group' => $order->checkout_group, 'provider' => 'sandbox',
            'method' => 'gcash', 'amount' => $order->total_price, 'status' => 'paid', 'reference' => 'REF-RTS-004']);
        $parcel = $this->parcel('return_to_sender', ['failure_reason' => 'Refused'], $order);

        $this->handBack($parcel);
        $this->actingAs($this->seller1)->post(route('seller.orders.returned-received', $order->id))->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(Order::CANCELLED, $order->status);
        $this->assertSame('Refunded', $order->payment_status);
        $this->assertEquals((float) $order->total_price, (float) $order->refunded_amount);

        $balances = app(\App\Http\Controllers\Seller\EarningsController::class)->balances($this->seller1->id);
        $this->assertEquals(0.0, (float) $balances['pending']);
        $this->assertEquals(0.0, (float) $balances['completed']);
    }
}
