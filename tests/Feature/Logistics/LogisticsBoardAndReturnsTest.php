<?php

namespace Tests\Feature\Logistics;

use App\Models\Order;
use App\Models\ParcelDelivery;
use App\Models\ReturnShipment;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/**
 * The logistics panel by job: the Shipment Board is the inventory list (each status opens its page); Parcel Sorting
 * sorts out-of-area parcels for a hub-to-hub trip; Failed Deliveries and Flagged Parcels list problems; Return Items
 * collects a buyer's return like a pickup and brings it back to the seller.
 */
class LogisticsBoardAndReturnsTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
    }

    public function test_the_board_lists_parcels_and_each_status_opens_its_page(): void
    {
        $pickup = $this->parcel('pending_pickup');
        $sorted = $this->parcel('sorted');

        $page = $this->actingAs($this->logistics1)->get(route('sc.tracking'))->assertOk()
            ->assertSee($pickup->tracking_number)->assertSee($sorted->tracking_number)
            ->assertSee(route('sc.pickup-requests', ['tab' => 'pending']), false)
            ->assertSee(route('sc.delivery-assignment', ['area_id' => $this->central->id]), false);
        $page->assertSee('Failed Deliveries')->assertSee('Flagged Parcels')->assertSee('Return Items')->assertDontSee('>Shipments<', false);

        // The old Shipments page is part of the board now.
        $this->actingAs($this->logistics1)->get(route('sc.shipments', ['q' => $pickup->tracking_number]))
            ->assertRedirect(route('sc.tracking', ['q' => $pickup->tracking_number]));
        $this->actingAs($this->logistics1)->get(route('sc.tracking', ['q' => $pickup->order->full_name]))->assertOk()->assertSee($pickup->tracking_number);
    }

    public function test_failed_and_flagged_pages_list_the_problems(): void
    {
        $this->online($this->rider1);
        $d = $this->deliveryFor($this->rider1, 'out_for_delivery');
        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.fail', $d->id), ['reason' => 'Buyer not home'])->assertSessionHas('success');
        $this->actingAs($this->logistics1)->get(route('sc.failed-deliveries'))->assertOk()
            ->assertSee($d->parcel->waybill_number)->assertSee('Buyer not home')->assertSee('Rescheduled');

        $flagged = $this->parcel('sorted');
        $this->actingAs($this->logistics1)->post(route('sc.tracking.exception', $flagged), ['type' => 'missing', 'details' => 'Not on the shelf.'])->assertSessionHas('success');
        $this->actingAs($this->logistics1)->get(route('sc.flagged'))->assertOk()->assertSee($flagged->waybill_number)->assertSee('Not on the shelf.');
        $this->actingAs($this->logistics1)->get(route('sc.tracking'))->assertSee('Flagged · on hold');
    }

    public function test_an_out_of_area_parcel_is_sorted_for_a_hub_to_hub_trip(): void
    {
        $this->logistics1->update(['assigned_municipality' => 'Quezon City']);
        $this->logistics2->update(['assigned_municipality' => 'Davao City']);
        $parcel = $this->parcel('picked_up', ['destination_municipality' => 'Davao City', 'destination_province' => 'Davao del Sur', 'area_id' => null, 'received_at' => now()]);

        $this->actingAs($this->logistics1)->get(route('sc.parcel-sorting'))->assertOk()->assertSee('Sort for hub-to-hub');
        $this->actingAs($this->logistics1)->post(route('sc.parcels.sort-transfer', $parcel), ['center_id' => $this->logistics2->id])->assertSessionHas('success');

        $parcel->refresh();
        $this->assertSame(['sorted', $this->logistics2->id, null], [$parcel->status, $parcel->next_center_id, $parcel->area_id]);
        $this->assertNull($this->dispatcher()->jobKind($parcel));   // a trip, not a rider
        $this->actingAs($this->logistics1)->get(route('sc.parcel-sorting'))->assertSee('Ready for hub-to-hub transfer');
    }

    public function test_a_buyer_return_is_collected_like_a_pickup_and_brought_back_to_the_seller(): void
    {
        $this->online($this->rider1, $this->rider2);
        $parcel = $this->parcel('delivered');
        $order = $parcel->order;
        $order->update(['status' => Order::DELIVERED, 'delivered_at' => now()]);

        // The buyer asks: the hub that delivered it gets the pickup job at once.
        $this->actingAs($this->buyer1)->post(route('orders.return', $order->id), [
            'reason' => 'Damaged item', 'details' => 'The box arrived crushed and wet.', 'evidence' => UploadedFile::fake()->image('damage.jpg'),
        ])->assertSessionHas('success');
        $rs = ReturnShipment::firstOrFail();
        $this->assertSame(['requested', $this->logistics1->id], [$rs->status, $rs->sorting_center_id]);
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'Item coming back')->exists());

        $this->actingAs($this->logistics1)->get(route('sc.returns'))->assertOk()->assertSee($order->number());
        $this->actingAs($this->logistics1)->post(route('sc.returns.assign', $rs), ['rider_id' => $this->rider1->id])->assertSessionHas('success');

        // The rider accepts, then at the buyer: photo + waybill scan (a wrong code is refused).
        $this->actingAs($this->rider1->user)->get(route('courier.rider'))->assertSee('Collect return');
        $this->actingAs($this->rider1->user)->post(route('courier.rider.return-pickups.accept', $rs->id))->assertSessionHas('success');
        $this->actingAs($this->rider1->user)->post(route('courier.rider.return-pickups.confirm', $rs->id), ['tracking_number' => 'WRONG123', 'proof_photo' => UploadedFile::fake()->image('item.jpg')])
            ->assertSessionHasErrors('tracking_number');
        $this->actingAs($this->rider1->user)->post(route('courier.rider.return-pickups.confirm', $rs->id), ['tracking_number' => $parcel->waybill_number, 'proof_photo' => UploadedFile::fake()->image('item.jpg')])
            ->assertSessionHas('success');
        $this->assertSame('picked_up', $rs->fresh()->status);
        $this->assertNotNull($rs->fresh()->pickup_proof_path);

        // The hub receives it, then a rider brings it to the seller.
        $this->actingAs($this->logistics1)->post(route('sc.returns.to-seller', $rs), ['rider_id' => $this->rider2->id])->assertSessionHas('error');   // not received yet
        $this->actingAs($this->logistics1)->post(route('sc.returns.receive', $rs))->assertSessionHas('success');
        $this->actingAs($this->logistics1)->post(route('sc.returns.to-seller', $rs), ['rider_id' => $this->rider2->id])->assertSessionHas('success');
        $this->actingAs($this->rider2->user)->post(route('courier.rider.return-pickups.handed', $rs->id))->assertSessionHas('success');

        $this->assertSame('returned', $rs->fresh()->status);
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'Returned item delivered')->exists());
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertSee('Returned to the seller');

        // The seller hears when the buyer is refunded (here: approved automatically, not by the seller).
        $this->app['auth']->forgetGuards();
        $rs->returnRequest->update(['status' => 'approved']);
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'Return refunded')->exists());
    }

    public function test_a_return_rejected_before_pickup_cancels_the_trip(): void
    {
        $parcel = $this->parcel('delivered');
        $parcel->order->update(['status' => Order::DELIVERED, 'delivered_at' => now()]);
        $this->actingAs($this->buyer1)->post(route('orders.return', $parcel->order_id), [
            'reason' => 'Changed my mind', 'details' => 'I no longer need this item.', 'evidence' => UploadedFile::fake()->image('item.jpg'),
        ])->assertSessionHas('success');
        ReturnShipment::firstOrFail()->returnRequest->update(['status' => 'rejected']);
        $this->assertSame('cancelled', ReturnShipment::firstOrFail()->status);
    }
}
