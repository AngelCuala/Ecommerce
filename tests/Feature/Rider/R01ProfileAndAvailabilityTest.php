<?php

namespace Tests\Feature\Rider;

use App\Models\Rider;
use App\Models\RiderJobOffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Rider workbook R1 (Profile, Vehicle & Documents) and R2 (Availability). */
class R01ProfileAndAvailabilityTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
    }

    private function profileForm(array $over = []): array
    {
        return array_merge(['vehicle_type' => 'Motorcycle', 'plate_number' => '123ABC', 'license_number' => 'N01-23-456789',
            'license_expiry' => '2030-03-22'], $over);
    }

    private function saveProfile(array $over = [])
    {
        return $this->actingAs($this->rider1->user)->from(route('courier.rider.profile'))->put(route('courier.rider.profile.update'), $this->profileForm($over));
    }

    // ── R1 Profile, Vehicle & Documents ─────────────────────────

    /** RID-PROF-001 (A1) */
    public function test_rid_prof_001_view_profile_vehicle_and_status(): void
    {
        $this->actingAs($this->rider1->user)->get(route('courier.rider.profile'))->assertOk()
            ->assertSee('Pedro Jose Garcia')->assertSee('N01-23-456789')->assertSee('03/22/2030')
            ->assertSee('Motorcycle')->assertSee('123ABC')->assertSee('Bilis Hatid Express Inc.')->assertSee('Active');

        $this->actingAs($this->rider1->user)->get(route('courier.rider.document', [$this->rider1->id, 'license']))->assertOk();
        $this->actingAs($this->rider1->user)->get(route('courier.rider.document', [$this->rider1->id, 'or-cr']))->assertOk();
    }

    /** RID-PROF-002 */
    public function test_rid_prof_002_update_vehicle_details(): void
    {
        $this->saveProfile(['plate_number' => '789GHI', 'or_cr_file' => UploadedFile::fake()->create('or_cr.pdf', 900, 'application/pdf')])
            ->assertSessionHasNoErrors()->assertSessionHas('success', fn ($m) => str_contains($m, 're-verification'));

        $rider = $this->rider1->fresh();
        $this->assertSame('789GHI', $rider->plate_number);
        $this->assertTrue($rider->needs_reverification);
        $this->assertNotSame('rider-documents/pedro-orcr.pdf', $rider->or_cr_path);

        $this->actingAs($this->logistics1)->get(route('sc.riders.show', $rider))->assertOk()
            ->assertSee('789GHI')->assertDontSee('123ABC')->assertSee('re-verify the OR/CR')->assertSee('OR/CR');
        $this->actingAs($this->logistics1)->get(route('sc.riders.document', [$rider, 'or-cr']))->assertOk();
        $this->actingAs($this->logistics1)->post(route('sc.riders.verify', $rider))->assertSessionHas('success');
        $this->assertFalse($rider->fresh()->needs_reverification);
    }

    /** RID-PROF-003 */
    public function test_rid_prof_003_plate_number_already_used(): void
    {
        foreach (['NBC1234', 'nbc1234'] as $plate) {
            $this->saveProfile(['plate_number' => $plate])->assertSessionHasErrors(['plate_number' => 'Plate number NBC1234 is already registered to another rider.']);
        }
        $this->assertSame('123ABC', $this->rider1->fresh()->plate_number);
    }

    /** RID-PROF-004 */
    public function test_rid_prof_004_license_expiry_boundary(): void
    {
        $this->saveProfile(['license_expiry' => now()->subDay()->toDateString()])
            ->assertSessionHasErrors(['license_expiry' => 'Your driver\'s license has expired. Enter an expiry date of today or later.']);
        $this->saveProfile(['license_expiry' => now()->toDateString()])->assertSessionHasNoErrors();
        $this->assertTrue($this->rider1->fresh()->license_expiry->isToday());
    }

    /** RID-PROF-005 (A16) */
    public function test_rid_prof_005_document_upload_rules(): void
    {
        $this->saveProfile(['license_file' => UploadedFile::fake()->image('size_5.0MB.jpg')->size(5120)])->assertSessionHasNoErrors();
        $kept = $this->rider1->fresh()->license_path;
        $this->assertNotSame('rider-documents/pedro-license.jpg', $kept);

        $this->saveProfile(['license_file' => UploadedFile::fake()->image('size_5.1MB.jpg')->size(5222)])
            ->assertSessionHasErrors(['license_file' => 'The driver\'s license photo must be 5 MB or smaller.']);
        $this->saveProfile(['license_file' => UploadedFile::fake()->create('price_list.docx', 320, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')])
            ->assertSessionHasErrors('license_file');
        $this->saveProfile(['license_file' => UploadedFile::fake()->create('renamed_exe.jpg', 2100, 'application/x-msdownload')])
            ->assertSessionHasErrors('license_file');
        $this->assertSame($kept, $this->rider1->fresh()->license_path);
    }

    /** RID-PROF-006 (A14) */
    public function test_rid_prof_006_suspended_rider_cannot_work(): void
    {
        $this->actingAs($this->rider3->user)->post(route('courier.rider.online'), ['online' => 1])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'suspended'));
        $this->assertFalse($this->rider3->fresh()->is_online);

        // Even if it were flagged online, a suspended rider gets no offer and can't be picked manually.
        $this->rider3->forceFill(['is_online' => true, 'last_seen_at' => now()])->save();
        $parcel = $this->parcel();
        $this->assertNull($this->dispatcher()->offer($parcel));
        $this->assertSame(0, RiderJobOffer::where('rider_id', $this->rider3->id)->count());
        $this->assertNotContains($this->rider3->id, $this->dispatcher()->eligibleRiders($this->logistics1->id)->pluck('id')->all());
        $this->actingAs($this->logistics1)->post(route('sc.dispatch.assign', $parcel), ['rider_id' => $this->rider3->id])
            ->assertSessionHas('error', 'Ramon Cruz is suspended.');
        $this->actingAs($this->logistics1)->get(route('sc.dispatch'))->assertOk()->assertDontSee('<option value="' . $this->rider3->id . '"', false);
    }

    /** RID-PROF-007 (A22) */
    public function test_rid_prof_007_cannot_see_another_riders_profile(): void
    {
        $this->actingAs($this->rider1->user)->get(route('courier.rider.profile', $this->rider2->id))->assertForbidden()->assertDontSee('NBC1234');
        $this->actingAs($this->rider1->user)->get(route('courier.rider.document', [$this->rider2->id, 'license']))->assertForbidden();
        $this->actingAs($this->rider1->user)->get(route('sc.riders.show', $this->rider2))->assertForbidden();
    }

    // ── R2 Availability ─────────────────────────────────────────

    /** RID-AVAIL-001 (A14) */
    public function test_rid_avail_001_go_online(): void
    {
        $this->actingAs($this->rider1->user)->post(route('courier.rider.online'), ['online' => 1])->assertSessionHas('success');
        $this->assertTrue($this->rider1->fresh()->isOnlineNow());
        $this->actingAs($this->logistics1)->get(route('sc.riders'))->assertSee('data-online', false);

        $parcel = $this->parcel();   // COD ₱500.00, Ready for Pickup
        $offer = $this->dispatcher()->offer($parcel);
        $this->assertSame($this->rider1->id, $offer->rider_id);
        $this->actingAs($this->rider1->user)->get(route('courier.rider'))->assertSee($parcel->tracking_number)->assertSee('₱500.00');
    }

    /** RID-AVAIL-002 (A14) */
    public function test_rid_avail_002_go_offline(): void
    {
        $this->online($this->rider1, $this->rider2);
        $this->actingAs($this->rider1->user)->post(route('courier.rider.online'), ['online' => 0])->assertSessionHas('success');

        $parcel = $this->parcel();
        $this->assertSame($this->rider2->id, $this->dispatcher()->offer($parcel)->rider_id);
        $this->assertSame(0, RiderJobOffer::where('rider_id', $this->rider1->id)->count());
        $this->actingAs($this->logistics1)->get(route('sc.dispatch'))
            ->assertDontSee('<option value="' . $this->rider1->id . '"', false)->assertSee('<option value="' . $this->rider2->id . '"', false);
    }

    /** RID-AVAIL-003 (A14) */
    public function test_rid_avail_003_go_offline_with_an_active_job(): void
    {
        $this->online($this->rider1);
        $delivery = $this->deliveryFor($this->rider1, 'out_for_delivery');

        $this->actingAs($this->rider1->user)->post(route('courier.rider.online'), ['online' => 0])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'active job stay with you'));
        $this->actingAs($this->rider1->user)->get(route('courier.rider'))->assertSee($delivery->parcel->tracking_number);

        $this->assertNull($this->dispatcher()->offer($this->parcel()));   // no new offers while offline
        $this->actingAs($this->rider1->user)->post(route('courier.rider.deliveries.deliver', $delivery->id), [
            'pod_photo' => UploadedFile::fake()->image('pod_photo.jpg'), 'otp' => $delivery->otp_code, 'cod_collected' => '500.00',
        ])->assertSessionHas('success');
        $this->assertSame('delivered', $delivery->fresh()->status);
    }

    /** RID-AVAIL-004 (A22) */
    public function test_rid_avail_004_offline_after_logout_or_timeout(): void
    {
        $this->online($this->rider1);
        $this->actingAs($this->rider1->user)->post(route('logout'));
        $this->assertFalse($this->rider1->fresh()->isOnlineNow());
        $this->assertNull($this->dispatcher()->offer($this->parcel()));

        // A session left idle past 30 minutes counts as Offline too.
        $this->rider2->forceFill(['is_online' => true, 'last_seen_at' => now()->subMinutes(Rider::ONLINE_MINUTES + 1)])->save();
        $this->assertFalse($this->rider2->fresh()->isOnlineNow());
        $this->assertNull($this->dispatcher()->offer($this->parcel()));
        $this->actingAs($this->logistics1)->get(route('sc.riders'))->assertDontSee('data-online', false);
    }
}
