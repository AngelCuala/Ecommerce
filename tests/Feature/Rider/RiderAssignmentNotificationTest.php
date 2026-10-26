<?php

namespace Tests\Feature\Rider;

use App\Models\Order;
use App\Models\ParcelPickup;
use App\Models\RiderJobOffer;
use App\Models\UserNotification;
use App\Services\RiderNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/**
 * Riders get an in-app notification when a job is assigned, reassigned away or cancelled, with
 * the parcel reference, time, pickup and destination; once per event. They can open it (marked
 * read), mark all read, and acknowledge the job. The hub is told "notified" only when it was.
 */
class RiderAssignmentNotificationTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        $this->online($this->rider1, $this->rider2);
    }

    private function asHub()
    {
        return $this->actingAs($this->logistics1)->from(route('sc.dispatch'));
    }

    private function notes($rider)
    {
        return UserNotification::where('user_id', $rider->user_id)->orderBy('id')->get();
    }

    public function test_manual_assignment_notifies_the_rider_with_reference_time_pickup_and_destination(): void
    {
        $parcel = $this->parcel('pickup_approved');
        $this->asHub()->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider2->id])
            ->assertSessionHas('success', "{$parcel->tracking_number} assigned to Mark Anthony Bautista. The rider was notified.");

        $note = $this->notes($this->rider2)->sole();
        $this->assertStringContainsString($parcel->waybill_number, $note->title);
        $this->assertStringContainsString($parcel->tracking_number, $note->title);
        $this->assertStringContainsString('Assigned ' . now()->format('M j'), $note->body);
        $this->assertStringContainsString('Pickup: 12 Tandang Sora Ave., Commonwealth, Quezon City.', $note->body);
        $this->assertStringContainsString('Destination: ' . $parcel->dropoff_address, $note->body);
        $this->assertSame(route('courier.rider'), $note->link);
        $this->assertNull($note->read_at);
        $this->assertSame(0, $this->notes($this->rider1)->count());           // nobody else is told
    }

    public function test_the_same_event_never_notifies_twice(): void
    {
        $parcel = $this->parcel('pickup_approved');
        $this->asHub()->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider2->id]);
        $job = ParcelPickup::where('parcel_id', $parcel->id)->sole();

        // A retry of the same event (e.g. a resent request) finds the existing notification.
        $again = app(RiderNotifier::class)->assigned($parcel, $this->rider2, RiderJobOffer::PICKUP, $job->id);
        $this->assertSame($this->notes($this->rider2)->sole()->id, $again->id);

        // A second assign of an already-assigned parcel is refused and sends nothing.
        $this->asHub()->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider2->id])->assertSessionHas('error');
        $this->assertCount(1, $this->notes($this->rider2));
    }

    public function test_reassignment_tells_the_old_rider_and_the_new_one(): void
    {
        $parcel = $this->parcel('pickup_approved');
        $this->asHub()->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider1->id]);
        $this->asHub()->post(route('sc.dispatch.reassign', $parcel), ['rider_id' => $this->rider2->id])
            ->assertSessionHas('success', "{$parcel->tracking_number} reassigned to Mark Anthony Bautista. The rider was notified.");

        $this->assertSame(['New pickup job: ', 'Job reassigned: '], $this->notes($this->rider1)->map(fn ($n) => substr($n->title, 0, strpos($n->title, ':') + 2))->all());
        $this->assertStringContainsString('You no longer need to do it', $this->notes($this->rider1)->last()->body);
        $this->assertCount(1, $this->notes($this->rider2));
        $this->assertStringStartsWith('New pickup job: ', $this->notes($this->rider2)->first()->title);
    }

    public function test_cancelling_the_order_tells_the_rider_not_to_pick_it_up(): void
    {
        $parcel = $this->parcel('pickup_approved');
        $this->asHub()->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider2->id]);

        $parcel->order->update(['status' => Order::CANCELLED]);
        $parcel->order->closeOpenPickups();

        $last = $this->notes($this->rider2)->last();
        $this->assertStringStartsWith('Job cancelled: ', $last->title);
        $this->assertStringContainsString('Do not pick it up', $last->body);
        $this->assertSame('cancelled', ParcelPickup::where('parcel_id', $parcel->id)->value('status'));
        $this->assertCount(2, $this->notes($this->rider2));
    }

    public function test_auto_assign_offer_carries_the_details_too(): void
    {
        $parcel = $this->parcel('pickup_approved');
        $this->asHub()->post(route('sc.dispatch.auto', $parcel))->assertSessionHas('success');
        $offer = RiderJobOffer::where('parcel_id', $parcel->id)->sole();
        $note = UserNotification::where('dedupe_key', 'rider-offer:' . $offer->id)->sole();
        $this->assertStringContainsString('Accept within', $note->body);
        $this->assertStringContainsString('Destination:', $note->body);

        // Accepting it does not send a second "assigned" notice.
        $rider = $offer->rider;
        $this->actingAs($rider->user)->post(route('courier.rider.offers.accept', $offer->id));
        $this->assertSame(0, UserNotification::where('user_id', $rider->user_id)->where('dedupe_key', 'like', 'rider-job:%:assigned')->count());
    }

    public function test_rider_acknowledges_only_their_own_job_and_the_notice_is_marked_read(): void
    {
        $parcel = $this->parcel('pickup_approved');
        $this->asHub()->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider2->id]);
        $job = ParcelPickup::where('parcel_id', $parcel->id)->sole();

        $this->actingAs($this->rider2->user)->get(route('courier.rider'))->assertOk()->assertSee('data-acknowledge="pickup-' . $job->id . '"', false);

        $this->actingAs($this->rider1->user)->post(route('courier.rider.jobs.acknowledge', ['kind' => 'pickup', 'job' => $job->id]))->assertNotFound();
        $this->assertNull($job->fresh()->acknowledged_at);

        $this->actingAs($this->rider2->user)->post(route('courier.rider.jobs.acknowledge', ['kind' => 'pickup', 'job' => $job->id]))->assertSessionHas('success');
        $this->assertNotNull($first = $job->fresh()->acknowledged_at);
        $this->assertNotNull($this->notes($this->rider2)->sole()->read_at);

        $this->travel(5)->minutes();
        $this->actingAs($this->rider2->user)->post(route('courier.rider.jobs.acknowledge', ['kind' => 'pickup', 'job' => $job->id]));
        $this->assertEquals($first, $job->fresh()->acknowledged_at);             // twice changes nothing
        $this->actingAs($this->rider2->user)->get(route('courier.rider'))->assertSee('data-acknowledged="pickup-' . $job->id . '"', false);
    }

    public function test_notification_center_marks_read_on_open_or_all_at_once_and_only_own(): void
    {
        $p1 = $this->parcel('pickup_approved');
        $p2 = $this->parcel('pickup_approved');
        $this->asHub()->post(route('sc.dispatch.assign', $p1), ['rider_id' => $this->rider2->id]);
        $this->asHub()->post(route('sc.dispatch.assign', $p2), ['rider_id' => $this->rider2->id]);
        [$n1, $n2] = $this->notes($this->rider2)->all();

        // Viewing the list no longer marks everything read.
        $this->actingAs($this->rider2->user)->get(route('courier.notifications'))->assertOk()->assertSee('2 unread.')->assertSee('Mark all as read');
        $this->assertNull($n1->fresh()->read_at);

        $this->actingAs($this->rider2->user)->get(route('courier.notifications.open', $n1->id))->assertRedirect(route('courier.rider'));
        $this->assertNotNull($n1->fresh()->read_at);
        $this->assertNull($n2->fresh()->read_at);

        $this->actingAs($this->rider1->user)->get(route('courier.notifications.open', $n2->id))->assertNotFound();
        $this->assertNull($n2->fresh()->read_at);

        $this->actingAs($this->rider2->user)->from(route('courier.notifications'))->post(route('courier.notifications.read-all'))->assertSessionHas('success');
        $this->assertNotNull($n2->fresh()->read_at);
    }

    public function test_hub_is_not_told_the_rider_was_notified_when_it_failed(): void
    {
        $parcel = $this->parcel('pickup_approved');
        // Saving the notification fails (e.g. the database refused it).
        $this->app->instance(RiderNotifier::class, new class extends RiderNotifier {
            public function assigned(\App\Models\Parcel $parcel, \App\Models\Rider $rider, string $kind, int $jobId): ?UserNotification
            {
                return null;
            }
        });

        $this->asHub()->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider2->id])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'could not be notified') && ! str_contains($m, 'The rider was notified'));
    }
}
