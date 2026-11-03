<?php

namespace Tests\Feature\Logistics;

use App\Exceptions\InvalidShipmentTransition;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\ParcelPickup;
use App\Models\ParcelTransfer;
use App\Models\ShipmentEvent;
use App\Models\ShipmentException;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\ShipmentWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/**
 * The shipment lifecycle: seller → pickup rider → sorting center(s) → delivery rider → buyer, with
 * verified events only, transfers between hubs, delays, exceptions, failures and access rules.
 * Logistics 1 (Bilis Hatid) picks up; Logistics 2 (Agila) serves Quezon City, the buyer's city.
 */
class ShipmentLifecycleTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        $this->online($this->rider1, $this->rider2);
    }

    /** Two real hubs: L1 in Caloocan (not the buyer's city), L2 in Quezon City (the buyer's). */
    private function twoHubs(): void
    {
        $this->logistics1->update(['assigned_municipality' => 'Caloocan City', 'assigned_municipality_code' => '137501000']);
        $this->logistics2->update(['assigned_municipality' => 'Quezon City', 'assigned_municipality_code' => '137404000']);
    }

    private function as(User $sc) { return $this->actingAs($sc)->from(route('sc.tracking')); }

    private function receive(User $sc, Parcel $p, array $over = [])
    {
        return $this->as($sc)->post(route('sc.tracking.receive', $p), $over + ['confirm_received' => '1', 'condition' => 'good']);
    }

    private function types(Parcel $p): array
    {
        return ShipmentEvent::where('parcel_id', $p->id)->orderBy('occurred_at')->orderBy('id')->pluck('event_type')->all();
    }

    /** A parcel the pickup rider already collected (on its way to Logistics 1). */
    private function collected(): Parcel
    {
        $parcel = $this->parcel('pickup_approved');
        $pickup = $this->pickupFor($this->rider1, $parcel);
        $this->actingAs($this->rider1->user)->post(route('courier.rider.pickups.confirm', $pickup->id), ['tracking_number' => $parcel->tracking_number])->assertSessionHas('success');
        return $parcel->fresh();
    }

    // 1 ─────────────────────────────────────────────────────────

    public function test_1_normal_order_one_sorting_center_to_the_buyer(): void
    {
        $parcel = $this->parcel('pickup_approved');
        $order = $parcel->order;
        $this->assertSame($order->tracking_number, ShipmentEvent::where('order_id', $order->id)->where('event_type', 'order_placed')->sole()->order->tracking_number);

        $this->as($this->logistics1)->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider1->id])->assertSessionHas('success');
        $pickup = ParcelPickup::where('parcel_id', $parcel->id)->sole();
        // Assigned is not collected; "on my way" is not collected either.
        $this->actingAs($this->rider1->user)->post(route('courier.rider.pickups.arriving', $pickup->id))->assertSessionHas('success');
        $this->assertSame('pickup_approved', $parcel->fresh()->status);
        $this->actingAs($this->rider1->user)->post(route('courier.rider.pickups.confirm', $pickup->id), ['tracking_number' => $parcel->tracking_number])->assertSessionHas('success');
        $this->assertSame('in_transit_to_hub', $parcel->fresh()->status);

        // The hub scans (finds) it, then confirms receipt explicitly.
        $this->as($this->logistics1)->post(route('sc.parcels.scan'), ['tracking_number' => $parcel->waybill_number])->assertRedirect(route('sc.tracking.show', $parcel));
        $this->assertSame('in_transit_to_hub', $parcel->fresh()->status);
        $this->receive($this->logistics1, $parcel)->assertSessionHas('success');
        $this->assertSame([$this->logistics1->id, $this->logistics1->id], [$parcel->fresh()->current_sorting_center_id, $parcel->fresh()->received_by]);

        $this->as($this->logistics1)->post(route('sc.tracking.start-sorting', $parcel))->assertSessionHas('success');
        $this->as($this->logistics1)->post(route('sc.parcels.sort', $parcel), ['area_id' => $this->central->id])->assertSessionHas('success');
        $this->as($this->logistics1)->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider2->id])->assertSessionHas('success');
        $this->assertSame('assigned', $parcel->fresh()->status);   // assigned is not out for delivery

        $d = ParcelDelivery::where('parcel_id', $parcel->id)->sole();
        $this->actingAs($this->rider2->user)->post(route('courier.rider.jobs.acknowledge', ['kind' => 'delivery', 'job' => $d->id]))->assertSessionHas('success');
        $this->actingAs($this->rider2->user)->post(route('courier.rider.deliveries.start', $d->id))->assertSessionHas('success');
        $d->refresh();
        $this->actingAs($this->rider2->user)->post(route('courier.rider.deliveries.deliver', $d->id), [
            'pod_photo' => UploadedFile::fake()->image('pod.jpg')->size(300), 'otp' => $d->otp_code, 'cod_collected' => (string) $d->cod_amount,
        ])->assertSessionHas('success');

        $this->assertSame([
            'ready_for_pickup', 'pickup_rider_assigned', 'pickup_rider_arriving', 'picked_up_from_seller',
            'arrived_at_destination_center', 'sorting_started', 'sorted', 'delivery_rider_assigned', 'delivery_rider_accepted',
            'out_for_delivery', 'delivered',
        ], $this->types($parcel));

        // Buyer and seller see the same verified timeline; nothing is "expected" any more.
        $page = $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertOk();
        $page->assertSeeInOrder(['Order Placed', 'Picked Up from Seller', 'At Destination Sorting Center', 'Out for Delivery', 'Delivered'])
            ->assertDontSee('data-expected', false)->assertDontSee($this->rider2->phone);
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertOk()->assertSee('data-shipment-tracking', false)->assertSee('Delivered');
        $this->assertTrue(UserNotification::where('user_id', $this->buyer1->id)->where('title', 'Delivered')->exists());
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'Delivered')->exists());
        $this->actingAs($this->buyer1)->get(route('shipments.pod', $order->id))->assertOk();
    }

    // 2 ─────────────────────────────────────────────────────────

    public function test_2_shipment_through_two_sorting_centers(): void
    {
        $this->twoHubs();
        $parcel = $this->collected();
        $this->receive($this->logistics1, $parcel)->assertSessionHas('success');
        $this->assertSame('arrived_at_sorting_center', ShipmentEvent::where('parcel_id', $parcel->id)->latest('id')->first()->event_type);

        // L1 doesn't serve Quezon City: it can't sort for last mile, and suggests L2.
        $this->as($this->logistics1)->post(route('sc.parcels.sort', $parcel), ['area_id' => $this->central->id])->assertSessionHas('error');
        $next = app(ShipmentWorkflow::class)->nextDestination($parcel->fresh(), $this->logistics1);
        $this->assertSame(['transfer', $this->logistics2->id], [$next['type'], $next['center']->id]);

        $this->as($this->logistics1)->post(route('sc.transfers.initiate'), ['parcel_id' => $parcel->id, 'to_sorting_center_id' => $this->logistics2->id])
            ->assertSessionHas('success', fn ($m) => str_contains($m, ' to ' . \App\Support\SortingCenters::nameOf($this->logistics2) . '. Dispatch it')
                && ! str_contains($m, '{') && ! str_contains($m, '@'));   // a short confirmation, not the account's data
        $t = ParcelTransfer::sole();
        $this->assertMatchesRegularExpression('/^TRF-\d{6}-\d{6}$/', $t->reference);
        $this->assertSame('picked_up', $parcel->fresh()->status);   // created, not dispatched: still at L1
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $parcel->order_id))->assertDontSee('Arrived at Agila');

        $this->as($this->logistics1)->post(route('sc.transfers.dispatch', $t))->assertSessionHas('success');
        $this->assertSame(['in_transfer', 'in_transit'], [$parcel->fresh()->status, $t->fresh()->status]);
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $parcel->order_id))
            ->assertSee('In Transit to Sorting Center')->assertSee('In transit to');

        $this->as($this->logistics2)->post(route('sc.transfers.accept', $t), ['confirm_received' => '1'])->assertSessionHas('success');
        $p = $parcel->fresh();
        $this->assertSame([$this->logistics2->id, 'picked_up', 'received'], [$p->current_sorting_center_id, $p->status, $t->fresh()->status]);
        $this->assertSame(['arrived_at_sorting_center', 'transfer_created', 'transfer_dispatched', 'arrived_at_destination_center'],
            array_values(array_intersect($this->types($p), ['arrived_at_sorting_center', 'transfer_created', 'transfer_dispatched', 'arrived_at_destination_center'])));
        $dispatched = ShipmentEvent::where('event_type', 'transfer_dispatched')->sole();
        $this->assertSame([$this->logistics1->id, $this->logistics2->id, $t->id], [$dispatched->origin_id, $dispatched->destination_id, $dispatched->transfer_id]);
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'like', 'Dispatched from%')->exists());

        // Buyer: transfer is internal-free; "transfer_created" is not shown to them.
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $parcel->order_id))->assertSee('At Destination Sorting Center')->assertDontSee('transfer_created');
    }

    // 3 ─────────────────────────────────────────────────────────

    public function test_3_scanned_twice_by_accident_records_one_arrival(): void
    {
        $parcel = $this->collected();
        $this->receive($this->logistics1, $parcel)->assertSessionHas('success');
        $this->receive($this->logistics1, $parcel)->assertSessionHas('error', fn ($m) => str_contains($m, 'already received'));
        $this->as($this->logistics1)->post(route('sc.parcels.scan'), ['tracking_number' => $parcel->tracking_number])->assertSessionHas('error', fn ($m) => str_contains($m, 'already received'));
        $this->assertSame(1, ShipmentEvent::where('parcel_id', $parcel->id)->whereIn('event_type', ['arrived_at_sorting_center', 'arrived_at_destination_center'])->count());
    }

    // 4 ─────────────────────────────────────────────────────────

    public function test_4_destination_refuses_a_transfer_not_dispatched_or_not_addressed_to_it(): void
    {
        $this->twoHubs();
        $third = User::forceCreate(['name' => 'Third Hub', 'email' => 'third@e.com', 'password' => bcrypt('x'), 'role' => 'sorting_center',
            'approval_status' => 'approved', 'assigned_municipality' => 'Pasig City', 'assigned_municipality_code' => '137403000']);
        $parcel = $this->collected();
        $this->receive($this->logistics1, $parcel);
        $t = app(ShipmentWorkflow::class)->createTransfer($parcel, $this->logistics1, $this->logistics1, $this->logistics2->id);

        // Not dispatched yet: L2 can't receive it.
        $this->as($this->logistics2)->post(route('sc.transfers.accept', $t), ['confirm_received' => '1'])->assertSessionHas('error', fn ($m) => str_contains($m, 'not been dispatched'));
        $this->as($this->logistics2)->post(route('sc.tracking.receive', $parcel), ['confirm_received' => '1'])->assertSessionHas('error');
        $this->assertSame([$this->logistics1->id, 'picked_up'], [$parcel->fresh()->current_sorting_center_id, $parcel->fresh()->status]);

        app(ShipmentWorkflow::class)->dispatchTransfer($t, $this->logistics1, $this->logistics1);
        // Not addressed to the third hub.
        $this->as($third)->post(route('sc.transfers.accept', $t), ['confirm_received' => '1'])->assertForbidden();
        $this->as($third)->get(route('sc.tracking.show', $parcel))->assertForbidden();   // not scanned there
        $this->assertSame('in_transfer', $parcel->fresh()->status);
    }

    // 5 ─────────────────────────────────────────────────────────

    public function test_5_transfer_not_confirmed_in_time_is_flagged_once(): void
    {
        $this->twoHubs();
        $parcel = $this->collected();
        $this->receive($this->logistics1, $parcel);
        $flow = app(ShipmentWorkflow::class);
        $t = $flow->dispatchTransfer($flow->createTransfer($parcel, $this->logistics1, $this->logistics1, $this->logistics2->id), $this->logistics1, $this->logistics1);

        $this->travel(ShipmentWorkflow::transferHours() - 1)->hours();
        $this->as($this->logistics1)->get(route('sc.tracking', ['tab' => 'delayed']))->assertOk();
        $this->assertNull($t->fresh()->delayed_at);

        $this->travel(2)->hours();
        $this->as($this->logistics1)->get(route('sc.tracking', ['tab' => 'delayed']))->assertOk()->assertSee($parcel->tracking_number);
        $this->as($this->logistics2)->get(route('sc.transfers'))->assertOk();   // checked again: still one flag
        $this->assertNotNull($t->fresh()->delayed_at);
        $this->assertSame('Delayed', $t->fresh()->statusLabel());
        $this->assertSame(1, ShipmentEvent::where('event_type', 'transfer_delayed')->count());
        $this->assertSame(1, ShipmentException::where('type', 'delayed_transfer')->where('status', 'open')->count());
        $this->assertSame(1, UserNotification::where('user_id', $this->buyer1->id)->where('title', 'Transfer Delayed')->count());
        $this->assertSame('in_transfer', $parcel->fresh()->status);   // not marked received by anyone
    }

    // 6 ─────────────────────────────────────────────────────────

    public function test_6_damaged_on_arrival_and_wrong_sorting_center(): void
    {
        $this->twoHubs();
        $parcel = $this->collected();
        $this->receive($this->logistics1, $parcel, ['condition' => 'damaged'])->assertSessionHasErrors('notes');
        $this->receive($this->logistics1, $parcel, ['condition' => 'damaged', 'notes' => 'Box crushed on one corner'])->assertSessionHas('success');
        $this->assertSame('picked_up', $parcel->fresh()->status);   // received, condition recorded
        $this->assertSame(1, ShipmentException::where('parcel_id', $parcel->id)->where('type', 'damaged_on_arrival')->count());

        // Sent to L2, but it turns up at a third hub.
        $third = User::forceCreate(['name' => 'Third Hub', 'email' => 'third@e.com', 'password' => bcrypt('x'), 'role' => 'sorting_center',
            'approval_status' => 'approved', 'assigned_municipality' => 'Pasig City', 'assigned_municipality_code' => '137403000']);
        $flow = app(ShipmentWorkflow::class);
        $t = $flow->dispatchTransfer($flow->createTransfer($parcel->fresh(), $this->logistics1, $this->logistics1, $this->logistics2->id), $this->logistics1, $this->logistics1);

        $this->as($third)->post(route('sc.parcels.scan'), ['tracking_number' => $parcel->waybill_number])->assertRedirect(route('sc.tracking.show', $parcel));
        $this->as($third)->get(route('sc.tracking.show', $parcel))->assertOk()->assertSee('not your center');
        $this->receive($third, $parcel)->assertSessionHas('error', fn ($m) => str_contains($m, 'NOT received'));
        $p = $parcel->fresh();
        $this->assertSame(['in_transfer', $this->logistics1->id, 'exception'], [$p->status, $p->current_sorting_center_id, $t->fresh()->status]);
        $x = ShipmentException::where('type', 'wrong_center')->sole();

        // Only an administrator resolves it, with a reason; the history keeps everything.
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin@e.com', 'password' => bcrypt('x'), 'role' => 'admin', 'approval_status' => 'approved']);
        $this->actingAs($admin)->from(route('admin.logistics.shipments.show', $parcel))
            ->post(route('admin.logistics.shipments.exceptions.resolve', $x), ['action' => 'custody', 'center_id' => $third->id])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->from(route('admin.logistics.shipments.show', $parcel))
            ->post(route('admin.logistics.shipments.exceptions.resolve', $x), ['action' => 'custody', 'center_id' => $third->id, 'reason' => 'Verified on CCTV: the parcel is at Third Hub.'])
            ->assertSessionHas('success');
        $p = $parcel->fresh();
        $this->assertSame([$third->id, 'picked_up'], [$p->current_sorting_center_id, $p->status]);
        $this->assertSame('resolved', $x->fresh()->status);
        $this->assertContains('exception_resolved', $this->types($p));
        $this->actingAs($admin)->get(route('admin.logistics.shipments.show', $parcel))->assertOk()->assertSee('Verified on CCTV');
    }

    // 7 ─────────────────────────────────────────────────────────

    public function test_7_rider_declines_a_delivery_assignment(): void
    {
        $parcel = $this->parcel('sorted', ['received_at' => now()]);
        $this->as($this->logistics1)->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider2->id])->assertSessionHas('success');
        $d = ParcelDelivery::where('parcel_id', $parcel->id)->sole();

        $this->actingAs($this->rider2->user)->post(route('courier.rider.jobs.decline', ['kind' => 'delivery', 'job' => $d->id]))->assertSessionHasErrors('reason');
        $this->actingAs($this->rider2->user)->post(route('courier.rider.jobs.decline', ['kind' => 'delivery', 'job' => $d->id]), ['reason' => 'Vehicle problem'])->assertSessionHas('success');

        $this->assertSame('declined', $d->fresh()->status);   // kept in the history
        $this->assertSame('sorted', $parcel->fresh()->status);
        $this->assertSame('Vehicle problem', ShipmentEvent::where('event_type', 'delivery_rider_declined')->sole()->note);
        $this->assertTrue(UserNotification::where('user_id', $this->logistics1->id)->where('title', 'Delivery declined')->exists());

        // An authorized reassignment works.
        $this->as($this->logistics1)->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider1->id])->assertSessionHas('success');
        $this->assertSame(2, ParcelDelivery::where('parcel_id', $parcel->id)->count());
    }

    // 8 + 9 ─────────────────────────────────────────────────────

    public function test_8_and_9_failed_attempt_then_return_to_seller(): void
    {
        $d = $this->deliveryFor($this->rider1, 'out_for_delivery');
        $parcel = $d->parcel;

        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.fail', $d->id))->assertSessionHasErrors('reason');
        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.fail', $d->id), ['reason' => 'Buyer not home'])->assertSessionHas('success');
        $this->assertSame('failed', $parcel->fresh()->status);
        $this->assertSame('Buyer not home', ShipmentEvent::where('event_type', 'delivery_failed')->sole()->note);
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'Delivery Attempt Failed')->exists());
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $parcel->order_id))->assertSee('1 failed delivery attempt');

        // Not delivered without the delivery steps.
        $this->expectsTransitionError(fn () => $parcel->fresh()->update(['status' => 'delivered']));
        $this->assertSame('failed', $parcel->fresh()->status);

        // Next attempt refused by the buyer → the order is cancelled and the parcel stays at the hub (no Return to Sender).
        $this->as($this->logistics1)->post(route('sc.deliveries.reschedule', $d))->assertSessionHas('success');
        $d2 = $this->deliveryFor($this->rider1, 'out_for_delivery', $parcel->fresh());
        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.fail', $d2->id), ['reason' => 'Refused'])->assertSessionHas('success');
        $this->assertSame('cancelled', $parcel->fresh()->status);
        $this->assertNotContains('return_to_sender', $this->types($parcel));
        $this->assertSame(\App\Models\Order::CANCELLED, $parcel->order->fresh()->status);
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $parcel->order_id))->assertSee('Delivery failed (Refused')->assertDontSee('data-expected', false);
    }

    // 10 ────────────────────────────────────────────────────────

    public function test_10_two_employees_dispatch_or_receive_the_same_package(): void
    {
        $this->twoHubs();
        $parcel = $this->collected();
        $this->receive($this->logistics1, $parcel);
        $flow = app(ShipmentWorkflow::class);
        $t = $flow->createTransfer($parcel, $this->logistics1, $this->logistics1, $this->logistics2->id);

        // A second active transfer for the same parcel is refused.
        $this->expectRuntime(fn () => $flow->createTransfer($parcel->fresh(), $this->logistics1, $this->logistics1, $this->logistics2->id), 'already has an active transfer');

        $this->as($this->logistics1)->post(route('sc.transfers.dispatch', $t))->assertSessionHas('success');
        $this->as($this->logistics1)->post(route('sc.transfers.dispatch', $t))->assertSessionHas('error', fn ($m) => str_contains($m, 'already'));
        $this->as($this->logistics2)->post(route('sc.transfers.accept', $t), ['confirm_received' => '1'])->assertSessionHas('success');
        $this->as($this->logistics2)->post(route('sc.transfers.accept', $t), ['confirm_received' => '1'])->assertSessionHas('error');

        $this->assertSame(1, ShipmentEvent::where('event_type', 'transfer_dispatched')->count());
        $this->assertSame(1, ShipmentEvent::where('parcel_id', $parcel->id)->where('event_type', 'arrived_at_destination_center')->count());
        // A dispatched transfer can't be cancelled as if it never left.
        $this->expectRuntime(fn () => $flow->cancelTransfer($t->fresh(), $this->logistics1, $this->logistics1), 'already received');
    }

    // 11 + 12 ───────────────────────────────────────────────────

    public function test_11_and_12_sellers_and_buyers_see_only_their_own_shipments(): void
    {
        $parcel = $this->parcel('pickup_approved');
        $this->actingAs($this->seller2)->get(route('seller.orders.show', $parcel->order_id))->assertForbidden();
        $this->actingAs($this->seller2)->get(route('shipments.pod', $parcel->order_id))->assertForbidden();
        $this->actingAs($this->buyer2)->get(route('profile.orders.show', $parcel->order_id))->assertNotFound();
        $this->actingAs($this->buyer2)->get(route('shipments.pod', $parcel->order_id))->assertForbidden();
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $parcel->order_id))->assertOk()->assertSee($parcel->tracking_number);
    }

    // 13 ────────────────────────────────────────────────────────

    public function test_13_rider_cannot_update_a_package_not_assigned_to_them(): void
    {
        $d = $this->deliveryFor($this->rider1);
        foreach ([route('courier.rider.deliveries.start', $d->id), route('courier.rider.jobs.acknowledge', ['kind' => 'delivery', 'job' => $d->id])] as $url) {
            $this->actingAs($this->rider2->user)->post($url)->assertNotFound();
        }
        $this->actingAs($this->rider2->user)->post(route('courier.rider.jobs.decline', ['kind' => 'delivery', 'job' => $d->id]), ['reason' => 'Other'])->assertNotFound();
        $this->assertSame(['assigned', 'assigned'], [$d->fresh()->status, $d->parcel->fresh()->status]);
    }

    // 14 ────────────────────────────────────────────────────────

    public function test_14_unauthorized_or_impossible_status_changes_are_refused(): void
    {
        $parcel = $this->collected();
        foreach ([$this->buyer1, $this->seller1, $this->rider1->user] as $notStaff) {
            $status = $this->actingAs($notStaff)->post(route('sc.tracking.receive', $parcel), ['confirm_received' => '1'])->getStatusCode();
            $this->assertContains($status, [302, 403]);   // sent away by the sorting-center middleware
        }
        $this->as($this->logistics2)->post(route('sc.tracking.receive', $parcel), ['confirm_received' => '1'])->assertForbidden();
        $this->as($this->logistics2)->post(route('sc.parcels.advance', $parcel), ['confirm_received' => '1'])->assertForbidden();
        $this->assertSame('in_transit_to_hub', $parcel->fresh()->status);

        // Without the explicit confirmation nothing is received.
        $this->as($this->logistics1)->post(route('sc.tracking.receive', $parcel))->assertSessionHasErrors('confirm_received');
        $this->assertSame('in_transit_to_hub', $parcel->fresh()->status);

        // The model refuses jumps, whoever tries (e.g. at a hub straight to Out for Delivery / Delivered).
        $this->receive($this->logistics1, $parcel);
        $this->expectsTransitionError(fn () => $parcel->fresh()->update(['status' => 'in_transit']));
        $this->expectsTransitionError(fn () => $parcel->fresh()->update(['status' => 'delivered']));
        $this->assertSame('picked_up', $parcel->fresh()->status);

        // Tracking events are append-only.
        $event = ShipmentEvent::where('parcel_id', $parcel->id)->first();
        $this->expectRuntime(fn () => $event->update(['note' => 'changed']), 'append-only', \LogicException::class);
        $this->expectRuntime(fn () => $event->delete(), 'append-only', \LogicException::class);
    }

    // 15 ────────────────────────────────────────────────────────

    public function test_15_a_failing_notification_never_undoes_the_event(): void
    {
        $parcel = $this->collected();
        $before = UserNotification::count();
        UserNotification::creating(function (UserNotification $n) {
            if (str_starts_with((string) $n->dedupe_key, 'shipment-event:')) {
                throw new \RuntimeException('Mail/notification service down');
            }
        });

        $this->receive($this->logistics1, $parcel)->assertSessionHas('success');
        $this->assertSame('picked_up', $parcel->fresh()->status);
        $this->assertSame(1, ShipmentEvent::where('parcel_id', $parcel->id)->where('event_type', 'arrived_at_destination_center')->count());
        $this->assertSame($before, UserNotification::count());   // the buyer / seller notices failed; nothing else broke
    }

    public function test_admin_correction_keeps_the_original_and_sorting_center_board_shows_only_its_parcels(): void
    {
        $parcel = $this->collected();
        $this->receive($this->logistics1, $parcel);
        $event = ShipmentEvent::where('parcel_id', $parcel->id)->where('event_type', 'arrived_at_destination_center')->sole();
        $admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin@e.com', 'password' => bcrypt('x'), 'role' => 'admin', 'approval_status' => 'approved']);

        $this->actingAs($admin)->post(route('admin.logistics.shipments.events.correct', $event), ['reason' => 'short'])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post(route('admin.logistics.shipments.events.correct', $event), ['reason' => 'Arrival time was entered one hour late.'])->assertSessionHas('success');
        $this->assertNotNull($event->fresh());
        $this->assertSame($event->id, ShipmentEvent::where('event_type', 'correction')->sole()->corrects_event_id);
        $this->actingAs($admin)->get(route('admin.logistics.shipments.index', ['q' => $parcel->tracking_number]))->assertOk()->assertSee($parcel->tracking_number);

        $this->as($this->logistics1)->get(route('sc.tracking', ['tab' => 'awaiting_sorting']))->assertOk()->assertSee($parcel->tracking_number);
        $this->as($this->logistics2)->get(route('sc.tracking', ['tab' => 'awaiting_sorting']))->assertOk()->assertDontSee($parcel->tracking_number);
        $this->as($this->logistics2)->get(route('sc.tracking.show', $parcel))->assertForbidden();
    }

    public function test_transfers_only_to_hubs_in_the_service_area_and_transporters_need_permission(): void
    {
        $this->twoHubs();
        $parcel = $this->collected();
        $this->receive($this->logistics1, $parcel);
        config(['service_area.provinces' => ['043400000']]);   // Laguna only: Agila (Quezon City) doesn't qualify
        $flow = app(ShipmentWorkflow::class);
        $this->expectRuntime(fn () => $flow->createTransfer($parcel->fresh(), $this->logistics1, $this->logistics1, $this->logistics2->id), 'listed sorting centers');

        config(['service_area.provinces' => []]);
        $this->expectRuntime(fn () => $flow->createTransfer($parcel->fresh(), $this->logistics1, $this->logistics1, $this->logistics2->id, $this->rider1->id), 'allowed to transport');
        $this->as($this->logistics1)->post(route('sc.riders.transport', $this->rider1))->assertSessionHas('success');
        $t = $flow->createTransfer($parcel->fresh(), $this->logistics1, $this->logistics1, $this->logistics2->id, $this->rider1->id);
        $this->assertSame($this->rider1->id, $t->transporter_rider_id);
        $this->assertTrue(UserNotification::where('user_id', $this->rider1->user_id)->where('title', 'Transfer task: ' . $t->reference)->exists());
        $this->actingAs($this->rider1->user)->get(route('courier.rider'))->assertSee($t->reference);
        $this->as($this->logistics2)->post(route('sc.riders.transport', $this->rider1))->assertForbidden();
    }

    private function expectsTransitionError(callable $fn): void
    {
        $this->expectRuntime($fn, "can't go from", InvalidShipmentTransition::class);
    }

    private function expectRuntime(callable $fn, string $contains, string $class = \RuntimeException::class): void
    {
        try {
            $fn();
            $this->fail('Expected ' . $class);
        } catch (\Throwable $e) {
            $this->assertInstanceOf($class, $e);
            $this->assertStringContainsString($contains, $e->getMessage());
        }
    }
}
