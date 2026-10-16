<?php

namespace Tests\Feature\Logistics;

use App\Models\DeliveryArea;
use App\Models\OrderStatusEvent;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\ParcelPickup;
use App\Models\RiderJobOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Logistics workbook L3 (Dispatch & Assignment) and L4 (Hub & Warehouse Operations). */
class L03DispatchAndHubTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
    }

    private function asL1()
    {
        return $this->actingAs($this->logistics1)->from(route('sc.dispatch'));
    }

    private function buyerPage(Parcel $parcel)
    {
        return $this->actingAs($this->buyer1)->get(route('profile.orders.show', $parcel->order_id))->assertOk();
    }

    /** A pickup the rider already collected from the seller (on the way to the hub). */
    private function pickedUpBy($rider): Parcel
    {
        $parcel = $this->parcel('pickup_approved');
        $this->pickupFor($rider, $parcel)->update(['status' => 'picked_up', 'picked_up_at' => now()]);
        $parcel->update(['status' => 'in_transit_to_hub']);
        return $parcel->fresh();
    }

    // ── L3 Dispatch & Assignment ────────────────────────────────

    /** LOG-DISP-001 */
    public function test_log_disp_001_auto_assign_offers_the_nearest_rider_first(): void
    {
        $commonwealth = DeliveryArea::create(['sorting_center_id' => $this->logistics1->id, 'name' => 'Commonwealth', 'code' => 'QC-COMMONWEALTH']);
        $this->rider1->update(['area_id' => $commonwealth->id]);   // near Seller 1's pickup address
        $this->online($this->rider1, $this->rider2);
        $parcel = $this->parcel('pickup_approved');

        $this->asL1()->post(route('sc.dispatch.auto', $parcel))->assertSessionHas('success', fn ($m) => str_contains($m, 'Pedro Jose Garcia'));
        $this->assertSame($this->rider1->id, (int) RiderJobOffer::where('parcel_id', $parcel->id)->sole()->rider_id);

        // Distance, not the order riders were added in, decides: with Rider 2 near instead, Rider 2 is first.
        $this->rider1->update(['area_id' => $this->central->id]);
        $this->rider2->update(['area_id' => $commonwealth->id]);
        $other = $this->parcel('pickup_approved');
        $this->asL1()->post(route('sc.dispatch.auto', $other))->assertSessionHas('success');
        $this->assertSame($this->rider2->id, (int) RiderJobOffer::where('parcel_id', $other->id)->sole()->rider_id);
    }

    /** LOG-DISP-002 */
    public function test_log_disp_002_manual_assign(): void
    {
        $this->online($this->rider2);
        $parcel = $this->parcel('pickup_approved');

        $this->asL1()->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider2->id])
            ->assertSessionHas('success', "{$parcel->tracking_number} assigned to Mark Anthony Bautista.");
        $pickup = ParcelPickup::where('parcel_id', $parcel->id)->sole();
        $this->assertSame([$this->rider2->id, 'Assigned'], [(int) $pickup->rider_id, $pickup->statusLabel()]);
        $this->assertTrue(\App\Models\UserNotification::where('user_id', $this->rider2->user_id)->where('title', 'New pickup job')->exists());
        $this->buyerPage($parcel)->assertSee('Rider Mark Anthony Bautista assigned for pickup');
    }

    /** LOG-DISP-003 */
    public function test_log_disp_003_offline_or_suspended_rider_is_not_assignable(): void
    {
        $this->online($this->rider2);
        $parcel = $this->parcel('pickup_approved');

        $eligible = $this->asL1()->get(route('sc.dispatch'))->assertOk()->viewData('eligible')->pluck('id')->all();
        $this->assertSame([$this->rider2->id], $eligible);   // Rider 1 is Offline, Rider 3 is Suspended

        $this->asL1()->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider3->id])->assertSessionHas('error', 'Ramon Cruz is suspended.');
        $this->asL1()->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider1->id])->assertSessionHas('error', 'Pedro Jose Garcia is offline.');
        $this->assertSame(0, ParcelPickup::where('parcel_id', $parcel->id)->count());
    }

    /** LOG-DISP-004 */
    public function test_log_disp_004_rider_at_the_job_limit(): void
    {
        $this->online($this->rider2);
        foreach (range(1, 3) as $i) {
            $this->deliveryFor($this->rider2);
        }
        $parcel = $this->parcel('pickup_approved');

        $this->asL1()->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider2->id])
            ->assertSessionHas('error', 'Mark Anthony Bautista already has 3 active jobs.');
        $this->assertSame(3, $this->rider2->fresh()->activeJobsCount());
        $this->assertSame(0, ParcelPickup::where('parcel_id', $parcel->id)->count());
    }

    /** LOG-DISP-005 */
    public function test_log_disp_005_reassign_before_pickup(): void
    {
        $this->online($this->rider1, $this->rider2);
        $parcel = $this->parcel('pickup_approved');
        $this->dispatcher()->assignManually($parcel, $this->rider1, $this->logistics1);

        $this->asL1()->get(route('sc.dispatch'))->assertSee('data-reassign="' . $parcel->tracking_number . '"', false);
        $this->asL1()->post(route('sc.dispatch.reassign', $parcel), ['rider_id' => $this->rider2->id])
            ->assertSessionHas('success', "{$parcel->tracking_number} reassigned to Mark Anthony Bautista.");

        $this->assertSame(0, $this->rider1->fresh()->activeJobsCount());
        $this->assertSame($this->rider2->id, (int) ParcelPickup::where('parcel_id', $parcel->id)->where('status', 'assigned')->sole()->rider_id);
        $this->assertTrue(\App\Models\UserNotification::where('user_id', $this->rider1->user_id)->where('title', 'Job reassigned')->exists());
        $this->buyerPage($parcel)->assertSeeInOrder(['Rider Pedro Jose Garcia assigned for pickup', 'Pickup taken from rider Pedro Jose Garcia', 'Rider Mark Anthony Bautista assigned for pickup']);
    }

    /** Regression: reassigning a delivery adds no extra "Sorted" step to the buyer's timeline. */
    public function test_reassigning_a_delivery_keeps_the_timeline_clean(): void
    {
        $this->online($this->rider1, $this->rider2);
        $parcel = $this->parcel('sorted');
        $this->dispatcher()->assignManually($parcel, $this->rider1, $this->logistics1);
        $sortedSteps = fn () => OrderStatusEvent::where('order_id', $parcel->order_id)->where('note', 'like', '%: Sorted')->count();
        $before = $sortedSteps();

        $this->asL1()->post(route('sc.dispatch.reassign', $parcel), ['rider_id' => $this->rider2->id])->assertSessionHas('success');
        $this->assertSame($before, $sortedSteps());
        $this->assertSame($this->rider2->id, (int) ParcelDelivery::where('parcel_id', $parcel->id)->sole()->rider_id);
        $this->assertSame(1, (int) ParcelDelivery::where('parcel_id', $parcel->id)->sole()->attempt);
    }

    /** LOG-DISP-006 */
    public function test_log_disp_006_no_reassign_after_pickup(): void
    {
        $this->online($this->rider2);
        $parcel = $this->pickedUpBy($this->rider1);

        $this->asL1()->get(route('sc.dispatch'))->assertOk()->assertDontSee('data-reassign="' . $parcel->tracking_number . '"', false);
        $this->asL1()->post(route('sc.dispatch.reassign', $parcel), ['rider_id' => $this->rider2->id])
            ->assertSessionHas('error', "{$parcel->tracking_number} was already picked up, so it can't be reassigned.");
        $this->assertSame($this->rider1->id, (int) ParcelPickup::where('parcel_id', $parcel->id)->sole()->rider_id);
        $this->assertSame('in_transit_to_hub', $parcel->fresh()->status);
    }

    /** LOG-DISP-007 */
    public function test_log_disp_007_bulk_assignment_over_the_limit(): void
    {
        $this->online($this->rider2);
        $parcels = collect(range(1, 5))->map(fn () => $this->parcel('pickup_approved'));

        $this->asL1()->post(route('sc.dispatch.bulk'), ['rider_id' => $this->rider2->id, 'parcel_ids' => $parcels->pluck('id')->all()])
            ->assertSessionHas('warning', fn ($m) => str_starts_with($m, '3 assigned to Mark Anthony Bautista. 2 skipped:') && substr_count($m, 'already has 3 active jobs') === 2);

        $this->assertSame(3, $this->rider2->fresh()->activeJobsCount());
        foreach ($parcels->slice(3) as $left) {
            $this->assertSame(0, ParcelPickup::where('parcel_id', $left->id)->count());
            $this->assertSame('pickup', $this->dispatcher()->jobKind($left->fresh()));   // still waiting in the queue
        }
    }

    /** LOG-DISP-008 */
    public function test_log_disp_008_parcel_that_is_not_ready(): void
    {
        $this->online($this->rider1);
        $packed = $this->parcel('pending_pickup');   // the order is only Packed: no pickup confirmed yet

        $board = $this->asL1()->get(route('sc.dispatch'))->assertOk();
        $this->assertFalse($board->viewData('jobs')->concat($board->viewData('unassigned'))->contains('id', $packed->id));
        $this->asL1()->post(route('sc.dispatch.assign', $packed), ['rider_id' => $this->rider1->id])
            ->assertSessionHas('error', "{$packed->tracking_number} is not ready for a rider (Ready for Pickup).");
        $this->assertSame(0, ParcelPickup::where('parcel_id', $packed->id)->count());
        $this->assertSame('pending_pickup', $packed->fresh()->status);
    }

    // ── L4 Hub & Warehouse Operations ───────────────────────────

    private function scan(string $tracking)
    {
        return $this->actingAs($this->logistics1)->from(route('sc.incoming-parcels'))->post(route('sc.parcels.scan'), ['tracking_number' => $tracking]);
    }

    /** LOG-HUB-001 */
    public function test_log_hub_001_inbound_receiving_by_scan(): void
    {
        $this->travelTo(now()->setTime(10, 15));
        $parcel = $this->pickedUpBy($this->rider1);

        $this->scan($parcel->tracking_number)->assertSessionHas('success');
        $parcel->refresh();
        $this->assertSame('picked_up', $parcel->status);
        $this->assertEquals(now()->startOfSecond(), $parcel->received_at);
        $this->assertSame('At Hub', ParcelPickup::where('parcel_id', $parcel->id)->sole()->statusLabel());

        $this->actingAs($this->logistics1)->get(route('sc.incoming-parcels'))->assertSee($parcel->tracking_number);
        $this->buyerPage($parcel)->assertSee('At Sorting Center — received ' . now()->format('M j, g:i A'));
    }

    /** LOG-HUB-002 */
    public function test_log_hub_002_sort_by_zone(): void
    {
        $north = DeliveryArea::create(['sorting_center_id' => $this->logistics1->id, 'name' => 'North', 'code' => 'QC-NORTH']);
        $parcel = $this->parcel('picked_up', ['received_at' => now()]);

        $this->actingAs($this->logistics1)->from(route('sc.parcel-sorting'))->post(route('sc.parcels.sort', $parcel), ['area_id' => $this->central->id])->assertSessionHas('success');
        $this->assertSame(['sorted', $this->central->id], [$parcel->fresh()->status, (int) $parcel->fresh()->area_id]);

        $this->actingAs($this->logistics1)->get(route('sc.delivery-assignment', ['area_id' => $this->central->id]))->assertSee($parcel->tracking_number);
        $this->actingAs($this->logistics1)->get(route('sc.delivery-assignment', ['area_id' => $north->id]))->assertDontSee($parcel->tracking_number);
    }

    /** LOG-HUB-003 */
    public function test_log_hub_003_outbound_dispatch_and_out_for_delivery(): void
    {
        $this->online($this->rider1);
        $parcel = $this->parcel('sorted', ['received_at' => now(), 'sorted_at' => now()]);

        $this->asL1()->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider1->id])->assertSessionHas('success');
        $delivery = ParcelDelivery::where('parcel_id', $parcel->id)->sole();
        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.start', $delivery->id))->assertSessionHas('success');

        $this->assertSame('Out for Delivery', $parcel->fresh()->statusLabel());
        $otp = $delivery->fresh()->otp_code;
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);
        $this->buyerPage($parcel)->assertSee('data-delivery-otp', false)->assertSee($otp);
    }

    /** LOG-HUB-004 */
    public function test_log_hub_004_duplicate_scan_warns_already_received(): void
    {
        $parcel = $this->pickedUpBy($this->rider1);
        $this->scan($parcel->tracking_number)->assertSessionHas('success');
        $this->scan($parcel->tracking_number)->assertSessionHas('error', fn ($m) => str_contains($m, 'was already received at'));

        $this->assertSame(1, OrderStatusEvent::where('order_id', $parcel->order_id)->where('note', 'like', '%At Sorting Center%')->count());
    }

    /** LOG-HUB-005 */
    public function test_log_hub_005_unknown_tracking_number(): void
    {
        $before = Parcel::count();
        $this->scan('TRK-0000000')->assertSessionHas('error', 'Parcel TRK-0000000 not found at your sorting center.');
        $this->assertSame($before, Parcel::count());
    }

    /** LOG-HUB-006 */
    public function test_log_hub_006_outbound_before_inbound_is_blocked(): void
    {
        $this->online($this->rider1);
        $parcel = $this->pickedUpBy($this->rider1);   // not scanned in yet

        $this->asL1()->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider1->id])->assertSessionHas('error');
        $this->actingAs($this->logistics1)->from(route('sc.delivery-assignment'))->post(route('sc.parcels.assign', $parcel), ['rider_id' => $this->rider1->id])
            ->assertSessionHas('error');
        $this->assertSame('in_transit_to_hub', $parcel->fresh()->status);
        $this->assertSame(0, ParcelDelivery::where('parcel_id', $parcel->id)->count());
    }

    /** LOG-HUB-007 */
    public function test_log_hub_007_another_companys_parcel(): void
    {
        $theirs = $this->parcel('in_transit_to_hub', [], null, $this->logistics2);

        $this->scan($theirs->tracking_number)->assertSessionHas('error', "Parcel {$theirs->tracking_number} not found at your sorting center.");
        $this->assertSame(['in_transit_to_hub', null], [$theirs->fresh()->status, $theirs->fresh()->received_at]);
        $this->actingAs($this->logistics1)->post(route('sc.parcels.advance', $theirs))->assertForbidden();
    }
}
