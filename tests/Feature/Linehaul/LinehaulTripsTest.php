<?php

namespace Tests\Feature\Linehaul;

use App\Models\Courier;
use App\Models\LinehaulTrip;
use App\Models\OrderStatusEvent;
use App\Models\Parcel;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\LinehaulTestData;
use Tests\TestCase;

/** Hub-to-hub trips: duty and checklist, assignment, loading, seal and depart, delay and breakdown, receiving and pay. */
class LinehaulTripsTest extends TestCase
{
    use RefreshDatabase, LinehaulTestData;

    private string $hub;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seedLinehaulTestData();
        $this->hub = $this->logistics1->createToken('t')->plainTextToken;
    }

    private function as(string|Courier $who): static
    {
        $this->app['auth']->forgetGuards();
        $token = $who instanceof Courier ? $who->user->createToken('t')->plainTextToken : $who;

        return $this->withToken($token);
    }

    private function at(array $p): array { return ['lat' => $p[0], 'lng' => $p[1]]; }

    private function allOk(): array { return array_fill_keys(['Tires', 'Lights', 'Brakes', 'Fuel', 'Cargo door lock'], 'ok'); }

    public function test_login_opens_the_courier_home(): void
    {
        $login = $this->postJson('/api/v1/auth/login', ['email' => 'ernesto.villareal@example.com', 'password' => 'Courier@2026']);
        $login->assertOk()->assertJsonPath('user.home', 'linehaul');
        $this->as($login->json('token'))->getJson('/api/v1/linehaul/home')->assertOk()
            ->assertJsonPath('courier.name', 'Ernesto Villareal')->assertJsonPath('courier.company', 'Bilis Hatid Express Inc.')
            ->assertJsonPath('courier.home_hub', 'HUB-QC')->assertJsonPath('courier.plate_number', 'NAB4521')->assertJsonPath('courier.duty_label', 'Off Duty');
        // Riders and buyers can't open it.
        $this->as($this->rider1->user->createToken('t')->plainTextToken)->getJson('/api/v1/linehaul/home')->assertForbidden();
    }

    public function test_duty_needs_location_and_a_passed_checklist(): void
    {
        $c = $this->courier1;
        $this->as($c)->postJson('/api/v1/linehaul/duty', ['on' => true, 'items' => $this->allOk()])->assertJsonValidationErrors(['location' => 'location']);
        $four = $this->allOk();
        unset($four['Fuel']);
        $this->as($c)->postJson('/api/v1/linehaul/duty', ['on' => true, 'items' => $four] + $this->at(self::AT_QC))->assertJsonValidationErrors(['items' => 'every checklist item']);
        $bad = ['Brakes' => 'not_ok'] + $this->allOk();
        $this->as($c)->postJson('/api/v1/linehaul/duty', ['on' => true, 'items' => $bad] + $this->at(self::AT_QC))->assertJsonValidationErrors(['note' => 'Brakes']);
        $this->as($c)->postJson('/api/v1/linehaul/duty', ['on' => true, 'items' => $bad, 'note' => 'Brake pedal feels soft, needs checking.'] + $this->at(self::AT_QC))
            ->assertOk()->assertJsonPath('courier.duty_label', 'Off Duty');
        $this->assertTrue(UserNotification::where('user_id', $this->logistics1->id)->where('body', 'like', '%Brakes Not OK%soft%')->exists());

        $this->as($c)->postJson('/api/v1/linehaul/duty', ['on' => true, 'items' => $this->allOk()] + $this->at(self::AT_QC))->assertOk()->assertJsonPath('courier.duty_label', 'On Duty');
        $board = collect($this->as($this->hub)->getJson('/api/v1/logistics/linehaul-couriers')->json('couriers'))->firstWhere('name', 'Ernesto Villareal');
        $this->assertSame('On Duty', $board['duty_label']);
        $this->assertNotNull($board['checklist_at']);
        $this->assertEqualsWithDelta(self::AT_QC[0], $board['location']['lat'], 0.0001);

        // Suspended: no duty.
        $this->as($this->courier3)->postJson('/api/v1/linehaul/duty', ['on' => true, 'items' => $this->allOk()] + $this->at(self::AT_QC))
            ->assertJsonValidationErrors(['duty' => 'suspended']);

        // Off duty: not listed for assignment; a closed app goes Offline after 5 minutes.
        $trip = $this->trip1(false);
        $this->travel(6)->minutes();
        $this->assertSame([], $this->as($this->hub)->getJson("/api/v1/logistics/trips/{$trip->id}")->json('assignable'));
        $this->travelBack();
    }

    public function test_full_trip_from_assignment_to_payment(): void
    {
        $parcels = $this->tripParcels();
        $trip = $this->trip1(true, $parcels);
        $c = $this->courier1;

        $view = $this->as($c)->getJson("/api/v1/linehaul/trips/{$trip->id}")->assertOk();
        $view->assertJsonPath('trip.status_label', 'Assigned')->assertJsonPath('trip.parcels', 5)->assertJsonPath('trip.weight_kg', 3.6)
            ->assertJsonPath('trip.pay', 1000)->assertJsonPath('trip.origin.code', 'HUB-QC')->assertJsonPath('trip.destination.code', 'HUB-PSG');

        // Another company's trip can't be accepted.
        $this->as($this->courier4)->postJson("/api/v1/linehaul/trips/{$trip->id}/accept")->assertNotFound();

        $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/accept")->assertOk()->assertJsonPath('trip.status_label', 'Accepted');
        // One active trip per courier.
        $trip2 = app(\App\Services\Linehaul::class)->createTrip($this->logistics1, ['origin_hub_id' => $this->hubQc->id, 'destination_hub_id' => $this->hubPsg->id,
            'parcel_ids' => [$this->parcel('picked_up', ['current_hub_id' => $this->hubQc->id])->id]]);
        $this->as($this->hub)->postJson("/api/v1/logistics/trips/{$trip2->id}/assign", ['courier_id' => $c->id])->assertJsonValidationErrors(['courier_id' => 'already has a trip']);

        $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/loading")->assertOk()->assertJsonPath('trip.status_label', 'Loading');
        foreach (array_slice($parcels, 0, 4) as $p) {
            $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/scan", ['tracking' => $p->tracking_number])->assertOk();
        }
        $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/scan", ['tracking' => $parcels[0]->tracking_number])->assertJsonValidationErrors(['tracking' => 'already loaded']);
        $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/scan", ['tracking' => 'TRK-0000000'])->assertJsonValidationErrors(['tracking' => 'not found']);
        $other = $this->parcel('picked_up', ['current_hub_id' => $this->hubQc->id]);
        $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/scan", ['tracking' => $other->tracking_number])->assertJsonValidationErrors(['tracking' => 'not in this trip']);

        $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/depart", ['seal_number' => 'BH-0004521'] + $this->at(self::AT_QC))
            ->assertJsonValidationErrors(['trip' => $parcels[4]->tracking_number]);
        $this->as($this->hub)->postJson("/api/v1/logistics/trips/{$trip->id}/parcels/{$parcels[4]->id}/remove")->assertOk()->assertJsonPath('trip.parcels', 4);
        $this->assertSame($this->hubQc->id, $parcels[4]->fresh()->current_hub_id);

        foreach (['', 'BH-000452', 'BH-00045210', 'BH-ABC4521'] as $seal) {
            $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/depart", ['seal_number' => $seal] + $this->at(self::AT_QC))->assertJsonValidationErrors(['seal_number' => 'BH-']);
        }
        $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/depart", ['seal_number' => 'BH-0004521'] + $this->at(self::AT_PSG))->assertJsonValidationErrors(['location' => 'HUB-QC']);
        $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/depart", ['seal_number' => 'BH-0004521'] + $this->at(self::AT_QC))
            ->assertOk()->assertJsonPath('trip.status_label', 'In Transit');
        $this->assertSame('linehaul', $parcels[0]->fresh()->status);
        $this->assertSame(1, OrderStatusEvent::where('order_id', $parcels[0]->order_id)->where('note', 'like', '%In Transit (HUB-QC → HUB-PSG)%')->count());

        // Off Duty is blocked; delay rules.
        $this->as($c)->postJson('/api/v1/linehaul/duty', ['on' => false])->assertJsonValidationErrors(['duty' => 'Hand over']);
        $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/delay", ['eta' => now()->addHour()->toIso8601String()])->assertJsonValidationErrors('reason');
        $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/delay", ['reason' => 'Heavy traffic', 'eta' => now()->subMinute()->toIso8601String()])->assertJsonValidationErrors('eta');
        $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/delay", ['reason' => 'Heavy traffic', 'eta' => now()->addHour()->toIso8601String()])
            ->assertOk()->assertJsonPath('trip.delayed', true)->assertJsonPath('trip.status_label', 'In Transit');

        $this->as($c)->postJson('/api/v1/linehaul/ping', $this->at([14.62, 121.07]))->assertOk();
        $this->assertNotNull($this->as($this->hub)->getJson("/api/v1/logistics/trips/{$trip->id}")->json('trip.last_location'));

        // Arrived only at HUB-PSG; receiving by hub staff only.
        $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/arrived", $this->at(self::AT_QC))->assertJsonValidationErrors(['location' => 'HUB-PSG']);
        $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/arrived", $this->at(self::AT_PSG))->assertOk()->assertJsonPath('trip.status_label', 'Arrived');
        $this->as($c)->postJson("/api/v1/logistics/trips/{$trip->id}/close")->assertForbidden();

        $this->as($this->hub)->postJson('/api/v1/logistics/trips/receive', ['hub_id' => $this->hubPsg->id, 'tracking' => $parcels[0]->tracking_number])
            ->assertJsonValidationErrors(['tracking' => 'seal']);
        $this->as($this->hub)->postJson("/api/v1/logistics/trips/{$trip->id}/seal", ['seal_number' => 'BH-0004521'])->assertOk();
        $this->as($this->hub)->postJson('/api/v1/logistics/trips/receive', ['hub_id' => $this->hubStr->id, 'tracking' => $parcels[0]->tracking_number])
            ->assertJsonValidationErrors(['tracking' => 'belongs to HUB-PSG']);
        foreach (array_slice($parcels, 0, 4) as $p) {
            $this->as($this->hub)->postJson('/api/v1/logistics/trips/receive', ['hub_id' => $this->hubPsg->id, 'tracking' => $p->tracking_number])->assertOk();
        }
        $this->as($this->hub)->postJson("/api/v1/logistics/trips/{$trip->id}/close")->assertOk()->assertJsonPath('trip.status_label', 'Completed');
        $this->assertSame($this->hubPsg->id, $parcels[0]->fresh()->current_hub_id);
        $this->assertSame(1, OrderStatusEvent::where('order_id', $parcels[0]->order_id)->where('note', 'like', '%At Hub (HUB-PSG)%')->count());

        $this->as($c)->getJson('/api/v1/linehaul/earnings')->assertOk()->assertJsonPath('today', 1000)->assertJsonPath('week', 1000);
        $this->as($c)->getJson('/api/v1/linehaul/home')->assertJsonPath('trip', null);
        $this->as($c)->getJson('/api/v1/linehaul/history?from=' . today()->toDateString() . '&to=' . today()->toDateString())->assertJsonPath('data.0.result', 'Completed');
        $this->as($c)->getJson('/api/v1/linehaul/history?from=2026-10-10&to=2026-10-01')->assertJsonValidationErrors('to');
    }

    public function test_decline_needs_a_reason_and_pays_nothing(): void
    {
        $trip = $this->trip1();
        $this->as($this->courier1)->postJson("/api/v1/linehaul/trips/{$trip->id}/decline")->assertJsonValidationErrors('reason');
        $this->as($this->courier1)->postJson("/api/v1/linehaul/trips/{$trip->id}/decline", ['reason' => 'Vehicle problem'])->assertOk();
        $this->assertSame('scheduled', $trip->fresh()->status);
        $this->assertNull($trip->fresh()->courier_id);
        $this->as($this->courier1)->getJson('/api/v1/linehaul/history')->assertJsonPath('data.0.result', 'Declined')->assertJsonPath('data.0.pay', 0);
    }

    public function test_reassign_during_accept_leaves_one_courier(): void
    {
        $trip = $this->trip1();
        $this->onDuty($this->courier2);
        $this->as($this->hub)->postJson("/api/v1/logistics/trips/{$trip->id}/assign", ['courier_id' => $this->courier2->id, 'reason' => 'Faster route'])->assertOk()
            ->assertJsonPath('message', "Reassigned {$trip->code} to Rogelio Dimaculangan.")->assertJsonPath('trip.courier.name', 'Rogelio Dimaculangan');
        $this->as($this->courier1)->postJson("/api/v1/linehaul/trips/{$trip->id}/accept")->assertJsonValidationErrors(['trip' => 'already assigned to another courier']);
        $this->assertSame($this->courier2->id, $trip->fresh()->courier_id);
    }

    public function test_breakdown_reassignment_requires_a_rescan_and_pays_the_new_courier(): void
    {
        $parcels = $this->tripParcels();
        $trip = $this->trip1(true, $parcels);
        $c1 = $this->courier1;
        $this->as($c1)->postJson("/api/v1/linehaul/trips/{$trip->id}/accept");
        $this->as($c1)->postJson("/api/v1/linehaul/trips/{$trip->id}/loading");
        foreach ($parcels as $p) {
            $this->as($c1)->postJson("/api/v1/linehaul/trips/{$trip->id}/scan", ['tracking' => $p->tracking_number]);
        }
        $this->as($c1)->postJson("/api/v1/linehaul/trips/{$trip->id}/depart", ['seal_number' => 'BH-0004521'] + $this->at(self::AT_QC))->assertOk();

        // Reassigning In Transit needs a breakdown report first.
        $this->onDuty($this->courier2);
        $this->as($this->hub)->postJson("/api/v1/logistics/trips/{$trip->id}/assign", ['courier_id' => $this->courier2->id])->assertJsonValidationErrors(['courier_id' => 'breakdown']);
        $this->as($c1)->post("/api/v1/linehaul/trips/{$trip->id}/delay", ['reason' => 'Vehicle breakdown', 'eta' => now()->addHours(2)->toIso8601String(),
            'photo' => UploadedFile::fake()->image('incident_photo.jpg')], ['Accept' => 'application/json'])->assertOk();
        // Courier 2's Van carries 800 kg: TRIP-1 weighs 3.6 kg.
        $this->as($this->hub)->postJson("/api/v1/logistics/trips/{$trip->id}/assign", ['courier_id' => $this->courier2->id, 'reason' => 'Vehicle breakdown'])->assertOk();
        $this->assertTrue(UserNotification::where('user_id', $c1->user_id)->where('title', 'like', '%reassigned%')->exists());

        $c2 = $this->courier2;
        $this->as($c2)->postJson("/api/v1/linehaul/trips/{$trip->id}/arrived", $this->at(self::AT_PSG))->assertJsonValidationErrors(['trip' => 'again']);
        foreach ($parcels as $p) {
            $this->as($c2)->postJson("/api/v1/linehaul/trips/{$trip->id}/scan", ['tracking' => $p->tracking_number])->assertOk();
        }
        $this->assertSame('linehaul', $parcels[0]->fresh()->status);
        $this->as($c2)->postJson("/api/v1/linehaul/trips/{$trip->id}/arrived", $this->at(self::AT_PSG))->assertOk();
        $this->as($this->hub)->postJson("/api/v1/logistics/trips/{$trip->id}/seal", ['seal_number' => 'BH-0004521']);
        foreach ($parcels as $p) {
            $this->as($this->hub)->postJson('/api/v1/logistics/trips/receive', ['hub_id' => $this->hubPsg->id, 'tracking' => $p->tracking_number]);
        }
        $this->as($this->hub)->postJson("/api/v1/logistics/trips/{$trip->id}/close")->assertOk();
        $this->as($c2)->getJson('/api/v1/linehaul/earnings')->assertJsonPath('today', 600);   // Van
        $this->as($c1)->getJson('/api/v1/linehaul/earnings')->assertJsonPath('today', 0);
        $this->as($c1)->getJson('/api/v1/linehaul/history')->assertJsonPath('data.0.result', 'Reassigned');
        $this->assertTrue($trip->events()->where('type', 'reassigned')->where('reason', 'Vehicle breakdown')->exists());
    }

    public function test_seal_missing_and_damaged_discrepancies(): void
    {
        $parcels = $this->tripParcels();
        $trip = $this->trip1(true, $parcels);
        $c = $this->courier1;
        $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/accept");
        $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/loading");
        foreach ($parcels as $p) {
            $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/scan", ['tracking' => $p->tracking_number]);
        }
        $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/depart", ['seal_number' => 'BH-0004521'] + $this->at(self::AT_QC));
        $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/arrived", $this->at(self::AT_PSG));

        $this->as($this->hub)->postJson("/api/v1/logistics/trips/{$trip->id}/seal", ['seal_number' => 'BH-0004522'])->assertJsonValidationErrors(['note' => 'does not match']);
        $this->as($this->hub)->postJson("/api/v1/logistics/trips/{$trip->id}/seal", ['seal_number' => 'BH-0004522', 'note' => 'Seal number differs from the manifest.'])
            ->assertOk()->assertJsonPath('trip.seal_discrepancy', true);

        foreach (array_slice($parcels, 0, 3) as $p) {
            $this->as($this->hub)->postJson('/api/v1/logistics/trips/receive', ['hub_id' => $this->hubPsg->id, 'tracking' => $p->tracking_number])->assertOk();
        }
        $this->as($this->hub)->postJson("/api/v1/logistics/trips/{$trip->id}/parcels/{$parcels[3]->id}/damaged")->assertJsonValidationErrors(['photo']);
        $this->as($this->hub)->post("/api/v1/logistics/trips/{$trip->id}/parcels/{$parcels[3]->id}/damaged", ['photo' => UploadedFile::fake()->image('incident_photo.jpg')],
            ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('damaged', $parcels[3]->fresh()->status);

        $this->as($this->hub)->postJson("/api/v1/logistics/trips/{$trip->id}/close")->assertJsonValidationErrors(['missing' => $parcels[4]->tracking_number]);
        $this->as($this->hub)->postJson("/api/v1/logistics/trips/{$trip->id}/close", ['confirm' => true])->assertOk()
            ->assertJsonPath('trip.status_label', 'Completed with Discrepancy')->assertJsonPath('trip.needs_courier_note', true);
        $this->assertSame('missing_in_transit', $parcels[4]->fresh()->status);
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'Parcel missing in transit')->exists());

        $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/note", ['note' => 'short'])->assertJsonValidationErrors('note');
        $this->as($c)->postJson("/api/v1/linehaul/trips/{$trip->id}/note", ['note' => 'Parcel was loaded at HUB-QC; not found in the truck.'])->assertOk();
        $this->assertTrue($trip->events()->where('type', 'seal_discrepancy')->exists() && $trip->events()->where('type', 'missing')->exists());
    }

    public function test_courier_profile_changes_are_flagged_for_reverification(): void
    {
        $c = $this->courier1;
        $this->as($c)->getJson('/api/v1/linehaul/profile')->assertOk()->assertJsonPath('profile.license_number', 'N02-15-345678')
            ->assertJsonPath('profile.load_capacity_kg', 3000)->assertJsonPath('profile.home_hub', 'HUB-QC (Bilis Hatid QC First-Mile Hub)')->assertJsonPath('profile.status', 'Active');

        $this->as($c)->post('/api/v1/linehaul/profile', ['load_capacity_kg' => '2500', 'or_cr_file' => UploadedFile::fake()->create('truck_or_cr.pdf', 950, 'application/pdf')],
            ['Accept' => 'application/json'])->assertOk()->assertJsonPath('profile.load_capacity_kg', 2500);
        $this->assertTrue($c->fresh()->needs_reverification);
        $this->as($this->hub)->getJson("/api/v1/logistics/couriers/{$c->id}")->assertJsonPath('courier.load_capacity_kg', 2500)->assertJsonPath('courier.needs_reverification', true);

        foreach (['CAV7788', 'cav7788', '123ABC'] as $plate) {
            $this->as($c)->postJson('/api/v1/linehaul/profile', ['plate_number' => $plate])->assertJsonValidationErrors(['plate_number' => 'already registered']);
        }
        $this->as($c)->postJson('/api/v1/linehaul/profile', ['license_expiry' => now()->subDay()->toDateString()])->assertJsonValidationErrors(['license_expiry' => 'expired']);
        $this->as($c)->postJson('/api/v1/linehaul/profile', ['license_expiry' => now()->toDateString()])->assertOk();
        $this->as($c)->post('/api/v1/linehaul/profile', ['nbi_file' => UploadedFile::fake()->create('renamed_exe.jpg', 2100, 'application/x-msdownload')],
            ['Accept' => 'application/json'])->assertJsonValidationErrors('nbi_file');
        $this->assertSame('NAB4521', $c->fresh()->plate_number);
    }
}
