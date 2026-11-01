<?php

namespace Tests\Feature\Logistics;

use App\Models\ActivityLog;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\Rider;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Logistics workbook L1 (Operations Dashboard) and L2 (Rider Management). */
class L01DashboardAndRidersTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
    }

    private function dashboard(array $query = [])
    {
        return $this->actingAs($this->logistics1)->get(route('sc.dashboard', $query));
    }

    private function listed(string $bucket, array $query = []): int
    {
        return $this->actingAs($this->logistics1)->get(route('sc.shipments', $query + ['status' => $bucket]))->assertOk()->viewData('parcels')->total();
    }

    // ── L1 Operations Dashboard ─────────────────────────────────

    /** LOG-DASH-001 */
    public function test_log_dash_001_counts_match_the_filtered_lists(): void
    {
        $this->parcel('pickup_approved');
        $this->parcel('pickup_approved');
        $this->parcel('picked_up');
        $this->parcel('in_transit');
        $this->parcel('delivered');
        $this->parcel('failed');

        $counts = $this->dashboard()->assertOk()->assertSee('Pending pickup')->viewData('shipmentCounts');
        $this->assertSame(['pending' => 2, 'in_transit' => 2, 'delivered' => 1, 'failed' => 1, 'returned' => 0], $counts);
        foreach ($counts as $bucket => $n) {
            $this->assertSame($n, $this->listed($bucket), "The {$bucket} list must have exactly the dashboard count.");
        }
    }

    /** LOG-DASH-002 */
    public function test_log_dash_002_counts_update_after_a_delivery(): void
    {
        $this->parcel('pickup_approved');
        $delivery = $this->deliveryFor($this->rider1, 'out_for_delivery');
        $before = $this->dashboard()->viewData('shipmentCounts');
        $this->assertSame(1, $before['in_transit']);

        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.deliver', $delivery->id), [
            'pod_photo' => UploadedFile::fake()->image('pod_photo.jpg'), 'otp' => $delivery->otp_code, 'cod_collected' => $delivery->cod_amount,
        ])->assertSessionHas('success');

        $after = $this->dashboard()->viewData('shipmentCounts');
        $this->assertSame($before['in_transit'] - 1, $after['in_transit']);
        $this->assertSame($before['delivered'] + 1, $after['delivered']);
        $this->assertSame($before['pending'], $after['pending']);
        $this->assertSame($before['failed'], $after['failed']);
    }

    /** LOG-DASH-003 */
    public function test_log_dash_003_date_filter_includes_both_end_days(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        $this->parcel('delivered')->forceFill(['created_at' => now()->subDays(3)->setTime(0, 0)])->saveQuietly();   // start day, midnight
        $this->parcel('delivered')->forceFill(['created_at' => now()->subDay()->setTime(23, 59, 59)])->saveQuietly(); // end day, last second
        $this->parcel('delivered')->forceFill(['created_at' => now()->subDays(4)])->saveQuietly();                    // before the range
        $this->parcel('delivered');                                                                                   // today

        $range = ['from' => now()->subDays(3)->toDateString(), 'to' => now()->subDay()->toDateString()];
        $this->assertSame(2, $this->dashboard($range)->assertOk()->viewData('shipmentCounts')['delivered']);
        $this->assertSame(2, $this->listed('delivered', $range));
        $this->assertSame(1, $this->dashboard(['from' => today()->toDateString(), 'to' => today()->toDateString()])->viewData('shipmentCounts')['delivered']);

        // End before start: a clear message, not a 500.
        $this->actingAs($this->logistics1)->from(route('sc.dashboard'))
            ->get(route('sc.dashboard', ['from' => today()->toDateString(), 'to' => today()->subDay()->toDateString()]))
            ->assertSessionHasErrors(['to' => 'The end date must be on or after the start date.']);
    }

    /** LOG-DASH-004 */
    public function test_log_dash_004_only_own_company_is_counted(): void
    {
        $mine = $this->parcel('in_transit');
        $theirs = $this->parcel('in_transit', [], null, $this->logistics2);
        $this->parcel('delivered', [], null, $this->logistics2);

        $this->assertSame(1, $this->dashboard()->viewData('shipmentCounts')['in_transit']);
        $this->assertSame(0, $this->dashboard()->viewData('shipmentCounts')['delivered']);
        $this->actingAs($this->logistics1)->get(route('sc.shipments'))->assertOk()
            ->assertSee($mine->tracking_number)->assertDontSee($theirs->tracking_number);
        $search = $this->actingAs($this->logistics1)->get(route('sc.shipments', ['q' => $theirs->tracking_number]))->assertOk();
        $this->assertSame(0, $search->viewData('parcels')->total());
        $search->assertDontSee('data-shipment="' . $theirs->tracking_number . '"', false);
    }

    // ── L2 Rider Management ─────────────────────────────────────

    /** LOG-RIDER-001 */
    public function test_log_rider_001_approve_a_rider(): void
    {
        $pending = $this->riderAccount($this->logistics1, 'rider.test02@example.com', 'Rina Test', 'Rider@2026', 'Motorcycle', '789GHI', [
            'application_status' => 'pending', 'approved_at' => null, 'is_active' => false,
            'license_path' => 'rider-documents/rina-license.jpg', 'or_cr_path' => 'rider-documents/rina-orcr.pdf']);
        \Illuminate\Support\Facades\Storage::disk('local')->put('rider-documents/rina-license.jpg', 'license');
        \Illuminate\Support\Facades\Storage::disk('local')->put('rider-documents/rina-orcr.pdf', '%PDF-1.4');

        $this->actingAs($this->logistics1)->get(route('sc.riders.show', $pending))->assertOk()->assertSee('Rina Test');
        $this->actingAs($this->logistics1)->get(route('sc.riders.document', [$pending, 'license']))->assertOk();
        $this->actingAs($this->logistics1)->get(route('sc.riders.document', [$pending, 'or-cr']))->assertOk();

        $this->actingAs($this->logistics1)->post(route('sc.riders.approve', $pending))->assertSessionHas('success');
        $pending->refresh();
        $this->assertSame('approved', $pending->application_status);
        $this->assertTrue((bool) $pending->is_active);

        $log = ActivityLog::where('subject_id', $pending->id)->where('description', 'Rina Test approved')->sole();
        $this->assertSame('Active', $log->new_value);
        $this->assertSame($this->logistics1->id, $log->admin_id);

        $this->actingAs($pending->user)->post(route('courier.rider.online'), ['online' => 1])->assertSessionHas('success');
        $this->assertTrue((bool) $pending->fresh()->is_online);
    }

    /** LOG-RIDER-002 */
    public function test_log_rider_002_reject_needs_a_reason_and_tells_the_rider(): void
    {
        $pending = $this->riderAccount($this->logistics1, 'rider.test02@example.com', 'Rina Test', 'Rider@2026', 'Motorcycle', '789GHI',
            ['application_status' => 'pending', 'approved_at' => null, 'is_active' => false]);

        $this->actingAs($this->logistics1)->from(route('sc.riders'))->post(route('sc.riders.reject', $pending), ['reason' => ''])
            ->assertSessionHasErrors(['reason' => 'Give the reason for rejecting this rider (10 to 300 characters).']);
        $this->assertSame('pending', $pending->fresh()->application_status);

        $reason = 'OR/CR photo is unreadable, please re-upload.';
        $this->actingAs($this->logistics1)->post(route('sc.riders.reject', $pending), ['reason' => $reason])->assertSessionHas('success');
        $this->assertSame('rejected', $pending->fresh()->application_status);
        $this->assertTrue(UserNotification::where('user_id', $pending->user_id)->where('body', 'like', '%' . $reason)->exists());

        $this->actingAs($pending->user)->post(route('courier.rider.online'), ['online' => 1])->assertForbidden();
        $this->assertFalse((bool) $pending->fresh()->is_online);
    }

    /** LOG-RIDER-003 */
    public function test_log_rider_003_suspend_returns_unstarted_jobs_and_keeps_picked_up_ones(): void
    {
        $this->online($this->rider2);
        $assigned = $this->deliveryFor($this->rider2);                         // not picked up yet
        $pickedUp = $this->deliveryFor($this->rider2, 'out_for_delivery');     // already with the rider

        $reason = 'Repeated late deliveries this week.';
        $this->actingAs($this->logistics1)->post(route('sc.riders.toggle', $this->rider2), ['reason' => $reason])->assertSessionHas('success');

        $this->rider2->refresh();
        $this->assertFalse((bool) $this->rider2->is_active);
        $this->assertFalse((bool) $this->rider2->is_online);
        $this->assertNull(ParcelDelivery::find($assigned->id));
        $this->assertSame('sorted', $assigned->parcel->fresh()->status);
        $this->assertNotNull($assigned->parcel->fresh()->unassigned_at);
        $this->assertSame('out_for_delivery', $pickedUp->fresh()->status);
        $this->assertSame($this->rider2->id, (int) $pickedUp->fresh()->rider_id);

        $this->actingAs($this->logistics1)->get(route('sc.dispatch'))->assertOk()->assertSee($assigned->parcel->tracking_number);
        $this->actingAs($this->rider2->user)->post(route('courier.rider.online'), ['online' => 1])->assertSessionHas('error');
        $this->assertFalse((bool) $this->rider2->fresh()->is_online);

        $log = ActivityLog::where('description', 'Mark Anthony Bautista suspended')->sole();
        $this->assertSame(['Active', 'Suspended', $reason], [$log->old_value, $log->new_value, $log->reason]);
    }

    /** LOG-RIDER-004 */
    public function test_log_rider_004_suspend_without_a_valid_reason_is_blocked(): void
    {
        foreach (['', str_repeat('x', 9)] as $reason) {
            $this->actingAs($this->logistics1)->from(route('sc.riders'))->post(route('sc.riders.toggle', $this->rider2), ['reason' => $reason])
                ->assertSessionHasErrors('reason');
            $this->assertTrue((bool) $this->rider2->fresh()->is_active);
        }
    }

    /** LOG-RIDER-005 */
    public function test_log_rider_005_reactivate_a_suspended_rider(): void
    {
        $this->actingAs($this->rider3->user)->post(route('courier.rider.online'), ['online' => 1])->assertSessionHas('error');

        $this->actingAs($this->logistics1)->post(route('sc.riders.toggle', $this->rider3))->assertSessionHas('success');
        $this->assertTrue((bool) $this->rider3->fresh()->is_active);
        $this->assertSame('Active', ActivityLog::where('description', 'Ramon Cruz reactivated')->sole()->new_value);

        $this->actingAs($this->rider3->user)->post(route('courier.rider.online'), ['online' => 1])->assertSessionHas('success');
        $this->assertTrue((bool) $this->rider3->fresh()->is_online);
    }

    /** LOG-RIDER-006 */
    public function test_log_rider_006_rider_list_shows_status_and_workload(): void
    {
        $this->online($this->rider1);
        $this->deliveryFor($this->rider1);
        $this->deliveryFor($this->rider1);
        $done = $this->deliveryFor($this->rider2);
        $done->update(['status' => 'delivered', 'delivered_at' => now()]);

        $html = $this->actingAs($this->logistics1)->get(route('sc.riders'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/Pedro Jose Garcia.*?data-online.*?data-active-jobs="2".*?data-today="0"/s', $html);
        $this->assertMatchesRegularExpression('/Mark Anthony Bautista.*?Offline.*?data-active-jobs="0".*?data-today="1"/s', $html);

        // Same numbers as the riders' own apps.
        $this->assertSame(2, $this->rider1->fresh()->activeJobsCount());
        $this->assertSame(1, $this->rider2->parcelDeliveries()->where('status', 'delivered')->whereDate('updated_at', today())->count());
    }

    /** LOG-RIDER-007 */
    public function test_log_rider_007_cannot_manage_another_companys_rider(): void
    {
        $this->actingAs($this->logistics2)->get(route('sc.riders.show', $this->rider1))->assertForbidden()->assertDontSee('123ABC');
        $this->actingAs($this->logistics2)->post(route('sc.riders.toggle', $this->rider1), ['reason' => 'Repeated late deliveries this week.'])->assertForbidden();
        $this->assertTrue((bool) $this->rider1->fresh()->is_active);
        $this->actingAs($this->logistics2)->get(route('sc.riders'))->assertOk()->assertDontSee('Pedro Jose Garcia');
    }
}
