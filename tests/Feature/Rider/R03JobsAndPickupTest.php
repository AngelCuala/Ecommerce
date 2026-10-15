<?php

namespace Tests\Feature\Rider;

use App\Models\Order;
use App\Models\ParcelPickup;
use App\Models\RiderJobOffer;
use App\Services\RiderDispatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Rider workbook R3 (Job Assignment) and R4 (Pickup). */
class R03JobsAndPickupTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
    }

    private function accept(RiderJobOffer $offer, $rider = null)
    {
        return $this->actingAs(($rider ?? $offer->rider)->user)->post(route('courier.rider.offers.accept', $offer->id));
    }

    // ── R3 Job Assignment ───────────────────────────────────────

    /** RID-JOB-001 (A10, A33) */
    public function test_rid_job_001_view_and_accept_a_job(): void
    {
        $this->online($this->rider1);
        $parcel = $this->parcel();
        $offer = $this->dispatcher()->offer($parcel);

        $this->actingAs($this->rider1->user)->get(route('courier.rider'))->assertOk()
            ->assertSee('Maria Clara Santos')->assertSee('Commonwealth, Quezon City')
            ->assertSee('Bilis Hatid Express Inc. hub')->assertSee('COD: ₱500.00', false)->assertSee('Pay: ₱30.00 pickup', false);

        $this->accept($offer)->assertSessionHas('success');
        $pickup = ParcelPickup::sole();
        $this->assertSame([$this->rider1->id, 'assigned'], [$pickup->rider_id, $pickup->status]);
        $this->assertSame('accepted', $offer->fresh()->status);
        $this->actingAs($this->logistics1)->get(route('sc.dispatch'))->assertOk()->assertSee('Pedro Jose Garcia');
    }

    /** RID-JOB-002 (A14) */
    public function test_rid_job_002_decline_a_job(): void
    {
        $this->online($this->rider1, $this->rider2);
        $parcel = $this->parcel();
        $offer = $this->dispatcher()->offer($parcel);
        $this->assertSame($this->rider1->id, $offer->rider_id);

        $this->actingAs($this->rider1->user)->post(route('courier.rider.offers.decline', $offer->id))->assertSessionHas('success');
        $this->assertSame('declined', $offer->fresh()->status);
        $next = $this->dispatcher()->pendingOffer($parcel);
        $this->assertSame($this->rider2->id, $next->rider_id);
        $this->actingAs($this->rider1->user)->get(route('courier.rider'))->assertDontSee($parcel->tracking_number);
        $this->assertSame(0, ParcelPickup::count());
    }

    /** RID-JOB-003 (A14) */
    public function test_rid_job_003_offer_timeout(): void
    {
        $this->online($this->rider1, $this->rider2);
        $first = $this->dispatcher()->offer($this->parcel());
        $this->travel(57)->seconds();
        $this->online($this->rider1, $this->rider2);
        $this->accept($first)->assertSessionHas('success');

        // Rider 1 now has a job, so the next offer goes to the less busy Rider 2.
        $second = $this->dispatcher()->offer($parcel = $this->parcel());
        $this->assertSame($this->rider2->id, $second->rider_id);
        $this->travel(61)->seconds();
        $this->online($this->rider1, $this->rider2);
        $this->accept($second)->assertSessionHas('error', fn ($m) => str_contains($m, 'expired'));
        $this->assertSame('expired', $second->fresh()->status);
        $this->assertSame($this->rider1->id, $this->dispatcher()->pendingOffer($parcel)->rider_id);   // the next rider
        $this->assertSame(1, ParcelPickup::count());
    }

    /** RID-JOB-004 (A14) */
    public function test_rid_job_004_unassigned_after_three_declines(): void
    {
        $extra = $this->riderAccountFor('third.rider@example.com', 'Third Rider', '777TRD');
        $this->online($this->rider1, $this->rider2, $extra);
        $parcel = $this->parcel();

        for ($i = 0; $i < 3; $i++) {
            $offer = $this->dispatcher()->offer($parcel);
            $this->actingAs($offer->rider->user)->post(route('courier.rider.offers.decline', $offer->id));
        }
        $this->assertTrue($this->dispatcher()->isUnassigned($parcel->fresh()));
        $this->assertNull($this->dispatcher()->pendingOffer($parcel));
        $this->assertNull($this->dispatcher()->offer($parcel));
        $this->actingAs($this->logistics1)->get(route('sc.dispatch'))->assertSee('Unassigned queue')->assertSee('data-job="' . $parcel->tracking_number . '"', false)->assertSee('Unassigned');
        $this->actingAs($this->logistics1)->post(route('sc.dispatch.auto', $parcel))->assertSessionHas('error', fn ($m) => str_contains($m, 'Unassigned queue'));
    }

    /** RID-JOB-005 (A37) */
    public function test_rid_job_005_accept_and_manual_assignment_at_the_same_moment(): void
    {
        $this->online($this->rider1, $this->rider2);
        $parcel = $this->parcel();
        $offer = $this->dispatcher()->offer($parcel);

        // Logistics wins the race: Rider 1's accept is refused.
        $this->actingAs($this->logistics1)->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider2->id])->assertSessionHas('success');
        $this->accept($offer)->assertSessionHas('error', 'This job was already assigned to another rider.');
        $this->assertSame(1, ParcelPickup::count());
        $this->assertSame($this->rider2->id, ParcelPickup::sole()->rider_id);

        // The other order: the rider accepts first, then logistics is told it's taken.
        $parcel2 = $this->parcel();
        $offer2 = $this->dispatcher()->offer($parcel2);
        $this->accept($offer2)->assertSessionHas('success');
        $this->actingAs($this->logistics1)->post(route('sc.dispatch.assign', $parcel2), ['rider_id' => $this->rider2->id])
            ->assertSessionHas('error', $parcel2->tracking_number . ' is already assigned to a rider.');
        $this->assertSame(1, ParcelPickup::where('parcel_id', $parcel2->id)->count());
        $this->actingAs($this->logistics1)->get(route('sc.dispatch'))->assertSee('Pedro Jose Garcia');
    }

    /** RID-JOB-006 (A14) */
    public function test_rid_job_006_concurrent_job_limit(): void
    {
        $this->online($this->rider1);
        $this->pickupFor($this->rider1);
        $this->pickupFor($this->rider1);
        $third = $this->dispatcher()->offer($this->parcel());
        $this->accept($third)->assertSessionHas('success');
        $this->assertSame(3, $this->rider1->activeJobsCount());

        $fourth = $this->parcel();
        $this->assertNull($this->dispatcher()->offer($fourth));
        $this->actingAs($this->logistics1)->post(route('sc.dispatch.assign', $fourth), ['rider_id' => $this->rider1->id])
            ->assertSessionHas('error', 'Pedro Jose Garcia already has ' . RiderDispatch::MAX_ACTIVE . ' active jobs.');
        $this->assertSame(3, $this->rider1->activeJobsCount());
    }

    /** RID-JOB-007 (A22) */
    public function test_rid_job_007_cannot_accept_another_companys_job(): void
    {
        $this->online($this->rider1, $this->agilaRider);
        $agilaParcel = $this->parcel('pickup_approved', [], null, $this->logistics2);
        $offer = $this->dispatcher()->offer($agilaParcel);
        $this->assertSame($this->agilaRider->id, $offer->rider_id);

        $this->accept($offer, $this->rider1)->assertNotFound();
        $this->assertSame('pending', $offer->fresh()->status);
        $this->assertSame(0, ParcelPickup::count());
        $this->actingAs($this->logistics1)->post(route('sc.dispatch.assign', $agilaParcel), ['rider_id' => $this->rider1->id])->assertForbidden();
    }

    // ── R4 Pickup ───────────────────────────────────────────────

    /** RID-PICK-001 */
    public function test_rid_pick_001_pickup_details(): void
    {
        $pickup = $this->pickupFor($this->rider1);
        $this->actingAs($this->rider1->user)->get(route('courier.rider.pickups.show', $pickup->id))->assertOk()
            ->assertSee('Maria Clara Handicrafts')->assertSee('12 Tandang Sora Ave., Commonwealth')->assertSee('09170000000')
            ->assertSee($pickup->parcel->tracking_number)->assertSee('1 parcel');
        $this->actingAs($this->rider2->user)->get(route('courier.rider.pickups.show', $pickup->id))->assertNotFound();
    }

    /** RID-PICK-002 (A6, A10, A33) */
    public function test_rid_pick_002_confirm_pickup_by_scanning(): void
    {
        $pickup = $this->pickupFor($this->rider1);
        $parcel = $pickup->parcel;
        $this->actingAs($this->rider1->user)->post(route('courier.rider.pickups.confirm', $pickup->id), ['tracking_number' => $parcel->tracking_number])
            ->assertSessionHas('success', fn ($m) => str_contains($m, '₱30.00'));

        $pickup->refresh();
        $this->assertSame('picked_up', $pickup->status);
        $this->assertNotNull($pickup->picked_up_at);
        $this->assertSame('in_transit_to_hub', $parcel->fresh()->status);
        $order = $parcel->order->fresh();
        $this->assertSame(Order::SHIPPED, $order->status);   // Buyer: To Receive
        $this->assertFalse($order->isCancellableBySeller());
        $this->assertEquals(30, app(\App\Services\RiderWallet::class)->pay($this->rider1, now()->startOfDay(), now()->endOfDay()));
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertOk()->assertSee('Picked up — on the way');
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertDontSee('Cancel order');

        // The hub checks it in; the pickup is done ("At Hub").
        $this->actingAs($this->logistics1)->post(route('sc.parcels.scan'), ['tracking_number' => $parcel->tracking_number])->assertSessionHas('success');
        $this->assertSame('received', $pickup->fresh()->status);
        $this->assertSame('picked_up', $parcel->fresh()->status);
    }

    /** RID-PICK-003 */
    public function test_rid_pick_003_confirm_by_typing_the_tracking_number(): void
    {
        $pickup = $this->pickupFor($this->rider1);
        $typed = '  ' . strtolower($pickup->parcel->tracking_number) . ' ';
        $this->actingAs($this->rider1->user)->post(route('courier.rider.pickups.confirm', $pickup->id), ['tracking_number' => $typed])->assertSessionHas('success');
        $this->assertSame('picked_up', $pickup->fresh()->status);
    }

    /** RID-PICK-004 */
    public function test_rid_pick_004_wrong_tracking_number(): void
    {
        $pickup = $this->pickupFor($this->rider1);
        $other = $this->parcel();
        foreach ([$other->tracking_number, 'TRK-0000000'] as $wrong) {
            $this->actingAs($this->rider1->user)->post(route('courier.rider.pickups.confirm', $pickup->id), ['tracking_number' => $wrong])
                ->assertSessionHasErrors(['tracking_number' => 'The tracking number does not match this job. Check the waybill on the parcel.']);
        }
        $this->assertSame('assigned', $pickup->fresh()->status);
    }

    /** RID-PICK-005 (A37) */
    public function test_rid_pick_005_double_scan(): void
    {
        $pickup = $this->pickupFor($this->rider1);
        $scan = fn () => $this->actingAs($this->rider1->user)->post(route('courier.rider.pickups.confirm', $pickup->id), ['tracking_number' => $pickup->parcel->tracking_number]);
        $scan()->assertSessionHas('success');
        $scan()->assertSessionHas('error', fn ($m) => str_starts_with($m, 'This parcel is already picked up'));
        $this->assertSame(1, ParcelPickup::where('status', 'picked_up')->count());
        $this->assertEquals(30, app(\App\Services\RiderWallet::class)->pay($this->rider1, now()->startOfDay(), now()->endOfDay()));
    }

    private function riderAccountFor(string $email, string $name, string $plate)
    {
        $user = $this->account('courier', $email, $name);
        return \App\Models\Rider::create(['user_id' => $user->id, 'sorting_center_id' => $this->logistics1->id, 'full_name' => $name,
            'vehicle_type' => 'Motorcycle', 'plate_number' => $plate, 'application_status' => 'approved', 'is_active' => true]);
    }
}
