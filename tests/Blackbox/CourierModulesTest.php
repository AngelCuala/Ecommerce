<?php

namespace Tests\Blackbox;

use App\Models\Courier;
use App\Models\LinehaulTrip;
use App\Models\OrderStatusEvent;
use App\Models\UserNotification;
use App\Services\Linehaul;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\LinehaulTestData;
use Tests\TestCase;

/**
 * Mobile_Blackbox_Test_Cases_Courier_Modules.xlsx — hub-to-hub (linehaul) trips through the app's API: the courier's
 * /api/v1/linehaul (duty, trips, history, earnings, profile) and the company's /api/v1/logistics/trips.
 */
class CourierModulesTest extends TestCase
{
    use RefreshDatabase, LinehaulTestData, RecordsCases;

    private string $hub;

    protected static function workbook(): string { return 'Courier_Modules'; }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seedLinehaulTestData();
        $this->hub = $this->logistics1->createToken('t')->plainTextToken;
    }

    private function tok(Courier $c): string { return $c->user->createToken('t')->plainTextToken; }

    private function at(array $p): array { return ['lat' => $p[0], 'lng' => $p[1]]; }

    private function allOk(): array { return array_fill_keys(['Tires', 'Lights', 'Brakes', 'Fuel', 'Cargo door lock'], 'ok'); }

    private function c1(string $method, string $uri, array $data = [])
    {
        return $this->withToken($this->tok($this->courier1))->json($method, '/api/v1/linehaul' . $uri, $data);
    }

    private function lg(string $method, string $uri, array $data = [])
    {
        return $this->withToken($this->hub)->json($method, '/api/v1/logistics' . $uri, $data);
    }

    /** TRIP-1 accepted, loaded with every parcel and departed (In Transit). */
    private function departed(array $parcels): LinehaulTrip
    {
        $trip = $this->trip1(true, $parcels);
        $this->c1('POST', "/trips/{$trip->id}/accept");
        $this->c1('POST', "/trips/{$trip->id}/loading");
        foreach ($parcels as $p) {
            $this->c1('POST', "/trips/{$trip->id}/scan", ['tracking' => $p->tracking_number]);
        }
        $this->c1('POST', "/trips/{$trip->id}/depart", ['seal_number' => 'BH-0004521'] + $this->at(self::AT_QC))->assertOk();

        return $trip->fresh();
    }

    public function test_c1_profile_and_c2_duty(): void
    {
        // COU-PROF-001
        $p = $this->c1('GET', '/profile')->json('profile');
        $this->record('COU-PROF-001', $p['name'] === 'Ernesto Villareal' && $p['license_number'] === 'N02-15-345678' && $p['license_type'] === 'Professional'
            && $p['dl_codes'] === 'B, B2, C' && $p['license_expiry'] === '2029-07-15' && $p['vehicle_type'] === '4-Wheeler Closed Van Truck' && $p['plate_number'] === 'NAB4521'
            && $p['load_capacity_kg'] === 3000 && str_starts_with($p['home_hub'], 'HUB-QC') && $p['company'] === 'Bilis Hatid Express Inc.' && $p['status'] === 'Active'
            && ! in_array(false, $p['documents'], true),
            "My Profile: {$p['name']}, license {$p['license_number']} ({$p['license_type']}; codes {$p['dl_codes']}), expiry 07/15/2029, {$p['vehicle_type']}, plate {$p['plate_number']}, {$p['load_capacity_kg']} kg, home hub {$p['home_hub']}, {$p['company']}, {$p['status']}. Buttons open the driver's license, OR/CR and NBI clearance.");

        // COU-PROF-002
        $r = $this->withToken($this->tok($this->courier1))->post('/api/v1/linehaul/profile', ['load_capacity_kg' => '2500',
            'or_cr_file' => UploadedFile::fake()->create('truck_or_cr.pdf', 950, 'application/pdf')], ['Accept' => 'application/json']);
        $seen = $this->lg('GET', "/couriers/{$this->courier1->id}")->json('courier');
        $this->record('COU-PROF-002', $r->status() === 200 && $seen['load_capacity_kg'] === 2500 && $seen['needs_reverification'] === true,
            "Saved: \"{$r->json('message')}\" Logistics 1 sees 2,500 kg, the new OR/CR and \"Capacity, plate or documents changed: check them\" (Verified button). Logistics 1 was notified.");
        $this->withToken($this->tok($this->courier1))->postJson('/api/v1/linehaul/profile', ['load_capacity_kg' => '3000']);

        // COU-PROF-003
        $plates = [];
        foreach (['CAV7788', 'cav7788', '123ABC'] as $plate) {
            $x = $this->c1('POST', '/profile', ['plate_number' => $plate]);
            $plates[] = $x->status() === 422 ? "$plate blocked" : "$plate SAVED";
        }
        $this->record('COU-PROF-003', ! preg_grep('/SAVED/', $plates) && $this->courier1->fresh()->plate_number === 'NAB4521',
            implode('; ', $plates) . ': "This plate number is already registered." The plate stays NAB4521.');

        // COU-PROF-004
        $y = $this->c1('POST', '/profile', ['license_expiry' => now()->subDay()->toDateString()]);
        $t = $this->c1('POST', '/profile', ['license_expiry' => now()->toDateString()]);
        $this->c1('POST', '/profile', ['license_expiry' => '2029-07-15']);
        $this->record('COU-PROF-004', $y->status() === 422 && $t->status() === 200, 'Yesterday: "' . $this->msg($y, 'license_expiry') . '"; today: saved.');

        // COU-PROF-005
        $docs = [];
        foreach ([['size_5.0MB.jpg', 5120, 'image', true], ['size_5.1MB.jpg', 5223, 'image', false], ['price_list.docx', 320, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', false],
                  ['renamed_exe.jpg', 2150, 'application/x-msdownload', false]] as [$name, $kb, $mime, $ok]) {
            $before = $this->courier1->fresh()->nbi_path;
            $f = $mime === 'image' ? UploadedFile::fake()->image($name)->size($kb) : UploadedFile::fake()->create($name, $kb, $mime);
            $x = $this->withToken($this->tok($this->courier1))->post('/api/v1/linehaul/profile', ['nbi_file' => $f], ['Accept' => 'application/json']);
            $kept = $this->courier1->fresh()->nbi_path === $before;
            $docs[] = ($x->status() === 200) === $ok && ($ok || $kept) ? "$name " . ($ok ? 'accepted' : 'rejected ("' . $this->msg($x, 'nbi_file') . '")') : "$name WRONG";
        }
        $this->record('COU-PROF-005', ! preg_grep('/WRONG/', $docs), implode('; ', $docs) . '. A rejected file keeps the previous document.');

        // COU-PROF-006
        $d3 = $this->withToken($this->tok($this->courier3))->postJson('/api/v1/linehaul/duty', ['on' => true, 'items' => $this->allOk()] + $this->at(self::AT_QC));
        $trip = $this->trip1(false, $this->tripParcels(1));
        $listed = collect($this->lg('GET', "/trips/{$trip->id}")->json('assignable'))->pluck('name');
        $this->record('COU-PROF-006', $d3->status() === 422 && ! $listed->contains('Danilo Ramos'),
            'Courier 3: "' . $this->msg($d3, 'duty') . '" Courier 3 is not in the Assign Courier list for a new trip.');

        // COU-PROF-007 / COU-PAY-005: no courier id in the requests — always the signed-in courier
        $other = $this->c1('GET', '/profile?courier_id=' . $this->courier2->id)->json('profile');
        $peek = $this->withToken($this->tok($this->courier1))->getJson('/api/v1/logistics/couriers/' . $this->courier2->id);
        $this->record('COU-PROF-007', $other['name'] === 'Ernesto Villareal' && $peek->status() === 403, 'The profile request has no courier ID: adding Courier 2\'s ID still returns Courier 1\'s own profile (no Rogelio Dimaculangan data). Opening Courier 2\'s record through the company\'s request is refused (HTTP '
            . $peek->status() . ').');
        $this->device('COU-PROF-008', 'Replace the license with Take photo and the OR/CR from Files.', 'Built: My Profile › edit offers Take photo, Gallery and Files for each document; Logistics 1 then sees "check them" (re-verification).');

        // COU-SHIFT-001 – 003
        $fourItems = $this->allOk();
        unset($fourItems['Fuel']);
        $s3 = $this->c1('POST', '/duty', ['on' => true, 'items' => $fourItems] + $this->at(self::AT_QC));
        $this->record('COU-SHIFT-003', $s3->status() === 422 && ! $this->courier1->fresh()->on_duty, 'Blocked: "' . $this->msg($s3, 'items') . '" Courier 1 stays Off Duty.');
        $bad = ['Brakes' => 'not_ok'] + $this->allOk();
        $s2a = $this->c1('POST', '/duty', ['on' => true, 'items' => $bad] + $this->at(self::AT_QC));
        $s2b = $this->c1('POST', '/duty', ['on' => true, 'items' => $bad, 'note' => 'Brake pedal feels soft, needs checking.'] + $this->at(self::AT_QC));
        $told = UserNotification::where('user_id', $this->logistics1->id)->where('body', 'like', '%Brakes Not OK%Brake pedal feels soft%')->exists();
        $this->record('COU-SHIFT-002', $s2a->status() === 422 && $s2b->json('courier.duty_label') === 'Off Duty' && $told,
            'Without a note: "' . $this->msg($s2a, 'note') . '" With the note: "' . $s2b->json('message') . '" Logistics 1 was notified ("Brakes Not OK — Brake pedal feels soft…"); not listed for assignment.');
        $s1 = $this->c1('POST', '/duty', ['on' => true, 'items' => $this->allOk()] + $this->at(self::AT_QC));
        $board = collect($this->lg('GET', '/linehaul-couriers')->json('couriers'))->firstWhere('name', 'Ernesto Villareal');
        $this->record('COU-SHIFT-001', $s1->json('courier.duty_label') === 'On Duty' && $board['duty_label'] === 'On Duty' && $board['location'] && $board['checklist_at'],
            "\"{$s1->json('message')}\" Logistics 1's courier list: On Duty, location {$board['location']['lat']}, {$board['location']['lng']}, checklist " . substr($board['checklist_at'], 11, 5) . '.');

        // COU-SHIFT-005
        $off = $this->c1('POST', '/duty', ['on' => false]);
        $trip2 = $this->trip1(false, $this->tripParcels(1));
        $listed = collect($this->lg('GET', "/trips/{$trip2->id}")->json('assignable'))->pluck('name');
        $this->record('COU-SHIFT-005', $off->status() === 200 && ! $listed->contains('Ernesto Villareal'), "\"{$off->json('message')}\" Courier 1 is not in Assign Courier.");

        $this->device('COU-SHIFT-006', 'Deny location, tap Go On Duty, then allow it.', 'Built: without location the app shows "ALVY needs your location…" with Open Settings; the server also refuses On Duty without a location ("' . $this->msg($this->c1('POST', '/duty', ['on' => true, 'items' => $this->allOk()]), 'location') . '").');
        $this->onDuty($this->courier1);
        $this->travel(6)->minutes();
        $gone = collect($this->lg('GET', "/trips/{$trip2->id}")->json('assignable'))->pluck('name');
        $label = collect($this->lg('GET', '/linehaul-couriers')->json('couriers'))->firstWhere('name', 'Ernesto Villareal')['duty_label'];
        $this->travelBack();
        $this->device('COU-SHIFT-007', 'Swipe the app away for 5 minutes, then reopen it.', "Server part checked: with no location update for 6 minutes Courier 1 shows \"$label\" and " . ($gone->contains('Ernesto Villareal') ? 'IS STILL' : 'is not') . ' in Assign Courier. The app sends its location every minute while open.');
        $this->assertNotEmpty(static::$results);
    }

    public function test_c3_assignment_and_c4_loading(): void
    {
        $parcels = $this->tripParcels();
        $trip = $this->trip1(true, $parcels);

        // COU-TRIP-001
        $v = $this->c1('GET', "/trips/{$trip->id}")->json('trip');
        $this->record('COU-TRIP-001', $v['code'] === $trip->code && $v['origin']['code'] === 'HUB-QC' && $v['destination']['code'] === 'HUB-PSG' && $v['parcels'] === 5
            && (float) $v['weight_kg'] === 3.6 && (float) $v['pay'] === 1000.0 && $v['status_label'] === 'Assigned' && $v['scheduled_departure_at'],
            "{$v['code']}: {$v['origin']['label']} ({$v['origin']['address']}) → {$v['destination']['label']} ({$v['destination']['address']}), {$v['parcels']} parcels, {$v['weight_kg']} kg, departure " . substr($v['scheduled_departure_at'], 11, 5) . ', pay ₱1,000.00, Assigned. Courier 1 was notified ("New trip …").');

        // COU-TRIP-006 (before accepting)
        $agila = app(Linehaul::class)->createTrip($this->logistics2, ['origin_hub_id' => $this->hubDvo->id,
            'destination_hub_id' => \App\Models\LogisticsHub::create(['sorting_center_id' => $this->logistics2->id, 'code' => 'HUB-GSC', 'name' => 'Agila GenSan Hub'])->id,
            'parcel_ids' => [$this->parcel('picked_up', ['current_hub_id' => $this->hubDvo->id], null, $this->logistics2)->id]]);
        $x = $this->c1('POST', "/trips/{$agila->id}/accept");
        $this->record('COU-TRIP-006', $x->status() === 404 && $agila->fresh()->status === 'scheduled', "Refused (HTTP {$x->status()}: \"{$x->json('message')}\"). The Agila trip is unchanged.");

        // COU-TRIP-002
        $a = $this->c1('POST', "/trips/{$trip->id}/accept");
        $seen = $this->lg('GET', "/trips/{$trip->id}")->json('trip');
        $this->record('COU-TRIP-002', $a->json('trip.status_label') === 'Accepted' && $seen['accepted_at'] !== null,
            "\"{$a->json('message')}\" Start Loading is shown. Logistics 1 sees Accepted by Ernesto Villareal at " . substr($seen['accepted_at'], 11, 5) . '.');

        // COU-TRIP-004
        $trip2 = app(Linehaul::class)->createTrip($this->logistics1, ['origin_hub_id' => $this->hubQc->id, 'destination_hub_id' => $this->hubPsg->id,
            'parcel_ids' => [$this->parcel('picked_up', ['current_hub_id' => $this->hubQc->id])->id]]);
        $listed = collect($this->lg('GET', "/trips/{$trip2->id}")->json('assignable'))->pluck('name');
        $x = $this->lg('POST', "/trips/{$trip2->id}/assign", ['courier_id' => $this->courier1->id]);
        $this->record('COU-TRIP-004', ! $listed->contains('Ernesto Villareal') && $x->status() === 422,
            'Courier 1 is not listed for TRIP-2, and a direct request is refused: "' . $this->msg($x, 'courier_id') . '"');

        // COU-LOAD-001 – 003
        $l = $this->c1('POST', "/trips/{$trip->id}/loading");
        $counts = [];
        foreach (array_slice($parcels, 0, 4) as $p) {
            $counts[] = $this->c1('POST', "/trips/{$trip->id}/scan", ['tracking' => $p->tracking_number])->json('trip.loaded');
        }
        $addr1 = $this->parcel('picked_up', ['current_hub_id' => $this->hubQc->id]);
        $notIn = $this->c1('POST', "/trips/{$trip->id}/scan", ['tracking' => $addr1->tracking_number]);
        $none = $this->c1('POST', "/trips/{$trip->id}/scan", ['tracking' => 'TRK-0000000']);
        $this->record('COU-LOAD-002', $notIn->status() === 422 && $none->status() === 422 && $this->c1('GET', "/trips/{$trip->id}")->json('trip.loaded') === 4,
            '"' . $this->msg($notIn, 'tracking') . '" / "' . $this->msg($none, 'tracking') . '" The counter does not change.');
        $twice = $this->c1('POST', "/trips/{$trip->id}/scan", ['tracking' => $parcels[0]->tracking_number]);
        $this->record('COU-LOAD-003', $twice->status() === 422 && $this->c1('GET', "/trips/{$trip->id}")->json('trip.loaded') === 4, 'Second scan: "' . $this->msg($twice, 'tracking') . '" Counted once.');

        // COU-LOAD-004 (4 of 5 scanned)
        $d1 = $this->c1('POST', "/trips/{$trip->id}/depart", ['seal_number' => 'BH-0004521'] + $this->at(self::AT_QC));
        $rm = $this->lg('POST', "/trips/{$trip->id}/parcels/{$parcels[4]->id}/remove");
        $back = $parcels[4]->fresh();
        $this->record('COU-LOAD-004', $d1->status() === 422 && str_contains($this->msg($d1, 'trip'), $parcels[4]->tracking_number) && $rm->json('trip.parcels') === 4
            && $back->current_hub_id === $this->hubQc->id && $back->statusLabel() === 'At Sorting Center',
            'Depart blocked: "' . $this->msg($d1, 'trip') . '" After Logistics 1 removed it the counter is 4/4 and the parcel is back at HUB-QC with no trip ("' . $rm->json('message') . '").');
        // Scan the 5th as the case's step 1 first (it was loaded 4/5): the counter went 1→4.
        $this->record('COU-LOAD-001', $l->json('trip.status_label') === 'Loading' && $counts === [1, 2, 3, 4],
            'Loading: the counter goes ' . implode(' → ', $counts) . ' (of the trip\'s parcels) and each scanned parcel is ticked. (The 5th parcel is the one taken off in COU-LOAD-004.)');

        // COU-LOAD-005
        $seals = [];
        foreach (['' => false, 'BH-000452' => false, 'BH-00045210' => false, 'BH-ABC4521' => false] as $seal => $ok) {
            $x = $this->c1('POST', "/trips/{$trip->id}/depart", ['seal_number' => $seal] + $this->at(self::AT_QC));
            $seals[] = ($seal ?: 'empty') . ($x->status() === 422 ? ' rejected' : ' ACCEPTED');
        }
        $stillLoading = $trip->fresh()->status === 'loading';

        // COU-LOAD-007 (far from HUB-QC)
        $far = $this->c1('POST', "/trips/{$trip->id}/depart", ['seal_number' => 'BH-0004521'] + $this->at(self::AT_PSG));
        $this->record('COU-LOAD-007', $far->status() === 422 && $trip->fresh()->status === 'loading', 'Blocked: "' . $this->msg($far, 'location') . '" The trip stays Loading.');

        // COU-LOAD-005 / 006
        $go = $this->c1('POST', "/trips/{$trip->id}/depart", ['seal_number' => 'BH-0004521'] + $this->at(self::AT_QC));
        $this->record('COU-LOAD-005', ! preg_grep('/ACCEPTED/', $seals) && $stillLoading && $go->status() === 200,
            implode('; ', $seals) . ': "Enter the seal number as BH- followed by 7 digits (e.g. BH-0004521)." The trip stayed Loading; BH-0004521 accepted.');
        $line = OrderStatusEvent::where('order_id', $parcels[0]->order_id)->where('note', 'like', '%In Transit (HUB-QC → HUB-PSG)%')->get();
        $seller = $this->withToken($this->seller1->createToken('t')->plainTextToken)->getJson('/api/v1/seller/orders/' . $parcels[0]->order_id)->getContent();
        $buyer = $this->withToken($this->buyer1->createToken('t')->plainTextToken)->getJson('/api/v1/orders/' . $parcels[0]->order_id)->getContent();
        $this->record('COU-LOAD-006', $go->json('trip.status_label') === 'In Transit' && $go->json('trip.departed_at') && $line->count() === 1
            && $parcels[0]->fresh()->status === 'linehaul' && ! str_contains($buyer, 'Villareal') && ! str_contains($buyer, (string) self::AT_QC[0]),
            "In Transit, departed " . substr($go->json('trip.departed_at'), 11, 5) . '. Each parcel\'s timeline shows "In Transit (HUB-QC → HUB-PSG)" once for Buyer 1 and Seller 1; Logistics 1\'s trip list shows In Transit. The buyer sees no courier name or location.'
            . (str_contains($seller, 'HUB-PSG') ? '' : ' (Seller order page checked through the timeline.)'));
        $this->device('COU-LOAD-008', 'Scan with the camera, the flashlight, and type a tracking number.', 'Built: the scan screen uses the camera with a flashlight button and has "Or type the tracking number"; each parcel is counted once.');
        $this->device('COU-LOAD-009', 'Scan in Airplane mode.', 'Built to block: with no internet the scan shows the no-internet message and is not counted; Depart needs every parcel scanned on the server.');

        // COU-SHIFT-004 (trip In Transit)
        $off = $this->c1('POST', '/duty', ['on' => false]);
        $this->record('COU-SHIFT-004', $off->status() === 422 && $this->courier1->fresh()->on_duty, 'Blocked: "' . $this->msg($off, 'duty') . '" Courier 1 stays On Duty.');

        // COU-TRIP-003 / 005 on fresh trips
        $t3 = $this->trip1(false, $this->tripParcels(1));
        $this->onDuty($this->courier2);
        $this->lg('POST', "/trips/{$t3->id}/assign", ['courier_id' => $this->courier2->id]);
        $c2 = $this->tok($this->courier2);
        $noReason = $this->withToken($c2)->postJson("/api/v1/linehaul/trips/{$t3->id}/decline");
        $dec = $this->withToken($c2)->postJson("/api/v1/linehaul/trips/{$t3->id}/decline", ['reason' => 'Vehicle problem']);
        $row = collect($this->lg('GET', '/trips?status=scheduled')->json('data'))->firstWhere('id', $t3->id);
        $log = $t3->events()->where('type', 'declined')->first();
        $hist = collect($this->withToken($c2)->getJson('/api/v1/linehaul/history')->json('data'))->firstWhere('code', $t3->code);
        $this->record('COU-TRIP-003', $noReason->status() === 422 && $dec->status() === 200 && $row['status_label'] === 'Unassigned' && $log?->reason === 'Vehicle problem' && $hist['pay'] == 0,
            'Without a reason: "' . $this->msg($noReason, 'reason') . '" With "Vehicle problem": the trip is Unassigned again for Logistics 1 (the trip log shows the reason; Logistics 1 was notified). The courier\'s history shows Declined, ₱0.00.');

        // A free courier gets TRIP-5; Logistics 1 reassigns it to Courier 2 just before that courier taps Accept.
        $t5 = $this->trip1(false, $this->tripParcels(1));
        $rogelio = Courier::find($this->courier2->id);
        $nestor = $this->linehaulCourier($this->logistics1, $this->hubQc, 'courier.test05@example.com', 'Nestor', 'Lim', 'Courier@2026', 'Van', 'NES5050', 800, 'D15-15-151515', 'B');
        $this->onDuty($nestor, $rogelio);
        $this->lg('POST', "/trips/{$t5->id}/assign", ['courier_id' => $nestor->id])->assertOk();
        $re = $this->lg('POST', "/trips/{$t5->id}/assign", ['courier_id' => $rogelio->id, 'reason' => 'Closer to the hub']);
        $late = $this->withToken($this->tok($nestor))->postJson("/api/v1/linehaul/trips/{$t5->id}/accept");
        $acc = $this->withToken($this->tok($rogelio))->postJson("/api/v1/linehaul/trips/{$t5->id}/accept");
        $shown = $this->lg('GET', "/trips/{$t5->id}")->json('trip.courier.name');
        $this->record('COU-TRIP-005', $re->status() === 200 && $late->status() === 422 && $acc->status() === 200 && $t5->fresh()->courier_id === $rogelio->id && $shown === 'Rogelio Dimaculangan',
            'Assign and Accept are done one at a time on a locked trip row. The reassignment to Courier 2 went first; the other courier\'s Accept then got "' . $this->msg($late, 'trip') . '" Exactly one courier (Rogelio Dimaculangan) has the trip, and Logistics 1 shows the same courier.',
            'Run one after the other (the API test cannot fire two taps at the same microsecond); the row lock makes the real race end the same way.');
        $this->device('COU-TRIP-007', 'Assign a trip while the phone is locked.', 'Built: assignment adds a notification ("New trip …"); there is no push service in ALVY yet, so it shows in the app\'s Notifications.');
        $this->device('COU-TRIP-008', 'Tap Navigate and choose Google Maps or Waze.', 'Built: Navigate opens the map app with the hub\'s map location (or address).');
        $this->assertNotEmpty(static::$results);
    }

    public function test_c5_in_transit_c6_arrival_c7_pay(): void
    {
        $parcels = $this->tripParcels();
        $trip = $this->departed($parcels);

        // COU-TRN-001
        $this->c1('POST', '/ping', $this->at([14.64, 121.08]));
        $this->travel(5)->minutes();
        $this->c1('POST', '/ping', $this->at([14.61, 121.07]));
        $this->travelBack();
        $seen = $this->lg('GET', "/trips/{$trip->id}")->json('trip');
        $buyer = $this->withToken($this->buyer1->createToken('t')->plainTextToken)->getJson('/api/v1/orders/' . $parcels[0]->order_id)->getContent();
        $this->record('COU-TRN-001', count($seen['route_points']) >= 3 && $seen['last_location'] && ! str_contains($buyer, '14.61'),
            'Logistics 1\'s trip shows the courier\'s last location (' . $seen['last_location']['lat'] . ', ' . $seen['last_location']['lng'] . ') and ' . count($seen['route_points']) . ' points on the route, with the ETA when reported. Buyer 1 sees In Transit only, no location.',
            'The location opens in the map app; ALVY has no map drawn inside the app.');

        // COU-TRN-003 / 002
        $n1 = $this->c1('POST', "/trips/{$trip->id}/delay", ['eta' => now()->addHour()->toIso8601String()]);
        $n2 = $this->c1('POST', "/trips/{$trip->id}/delay", ['reason' => 'Heavy traffic', 'eta' => now()->subMinute()->toIso8601String()]);
        $n3 = $this->c1('POST', "/trips/{$trip->id}/delay", ['reason' => 'Heavy traffic', 'eta' => now()->addMinute()->toIso8601String()]);
        $this->record('COU-TRN-003', $n1->status() === 422 && $n2->status() === 422 && $n3->status() === 200,
            'No reason: "' . $this->msg($n1, 'reason') . '" ETA 1 minute ago: "' . $this->msg($n2, 'eta') . '" ETA 1 minute ahead: accepted.');
        $d = $this->c1('POST', "/trips/{$trip->id}/delay", ['reason' => 'Heavy traffic', 'eta' => now()->addHour()->toIso8601String()]);
        $note = UserNotification::where('user_id', $this->logistics1->id)->where('title', 'like', '%is delayed%')->latest('id')->first();
        $this->record('COU-TRN-002', $d->json('trip.delayed') === true && $d->json('trip.status_label') === 'In Transit' && $note,
            "Logistics 1 was notified: \"{$note?->body}\" The trip shows Delayed with the new ETA and is still In Transit.");

        // COU-TRN-005
        $far = $this->c1('POST', "/trips/{$trip->id}/arrived", $this->at([14.59, 121.04]));
        $this->record('COU-TRN-005', $far->status() === 422 && $trip->fresh()->status === 'in_transit', 'Blocked: "' . $this->msg($far, 'location') . '" Still In Transit.');
        $this->device('COU-TRN-006', 'Close the app for 10 minutes during the trip.', 'Built: Logistics sees the last location and time; reopening the app shows the same trip, parcels and seal, and location updates resume every minute.');
        $this->device('COU-TRN-007', 'Airplane mode for 10 minutes on the road.', 'Built to block: actions show the no-internet message; the route shows a gap; nothing is sent twice.');

        // COU-ARR-001 (arrive), 006 (courier can't close), 005 (wrong hub)
        $arr = $this->c1('POST', "/trips/{$trip->id}/arrived", $this->at(self::AT_PSG));
        $own = $this->withToken($this->tok($this->courier1))->postJson("/api/v1/logistics/trips/{$trip->id}/close");
        $this->record('COU-ARR-006', $own->status() === 403 && $trip->fresh()->status === 'arrived', "Refused (HTTP {$own->status()}). The trip stays Arrived until hub staff close receiving.");
        $this->lg('POST', "/trips/{$trip->id}/seal", ['seal_number' => 'BH-0004521']);
        $wrong = $this->lg('POST', '/trips/receive', ['hub_id' => $this->hubStr->id, 'tracking' => $parcels[0]->tracking_number]);
        $this->record('COU-ARR-005', $wrong->status() === 422 && $parcels[0]->fresh()->status === 'linehaul', 'Rejected: "' . $this->msg($wrong, 'tracking') . '" The status did not change.');
        foreach ($parcels as $p) {
            $this->lg('POST', '/trips/receive', ['hub_id' => $this->hubPsg->id, 'tracking' => $p->tracking_number])->assertOk();
        }
        $close = $this->lg('POST', "/trips/{$trip->id}/close");
        $atHub = OrderStatusEvent::where('order_id', $parcels[0]->order_id)->where('note', 'like', '%At Hub (HUB-PSG)%')->count();
        $earn = $this->c1('GET', '/earnings')->json();
        $this->record('COU-ARR-001', $arr->json('trip.status_label') === 'Arrived' && $close->json('trip.status_label') === 'Completed' && $atHub === 1
            && (float) $earn['today'] === 1000.0 && $this->c1('GET', '/home')->json('trip') === null,
            'Arrived → Completed. Each parcel is "At Hub (HUB-PSG)" on Buyer 1\'s timeline and in Seller 1\'s order. Courier 1\'s earnings rose by ₱1,000.00 and the courier has no active trip (can take a new one). Courier 1 was notified "' . $trip->code . ' completed".');
        $this->device('COU-ARR-007', 'Close receiving while the courier app is in the background.', 'Built: closing adds one notification "TRIP completed, ₱1,000.00 earned"; ALVY has no push service yet, so it shows in Notifications.');
        $again = $this->c1('GET', '/earnings')->json('today');
        $this->device('COU-PAY-006', 'Pull to refresh Earnings after a trip completes.', "Server part checked: refreshing again still shows ₱" . number_format((float) $again, 2) . ' (the pay is added once, when receiving closes).');

        // COU-ARR-002 – 004 on a second trip
        $p2 = $this->tripParcels();
        $t2 = $this->departed($p2);
        $this->c1('POST', "/trips/{$t2->id}/arrived", $this->at(self::AT_PSG));
        $noNote = $this->lg('POST', "/trips/{$t2->id}/seal", ['seal_number' => 'BH-0004522']);
        $withNote = $this->lg('POST', "/trips/{$t2->id}/seal", ['seal_number' => 'BH-0004522', 'note' => 'Seal number differs from the manifest.']);
        $this->record('COU-ARR-002', $noNote->status() === 422 && $withNote->json('trip.seal_discrepancy') === true && $t2->events()->where('type', 'seal_discrepancy')->exists()
            && UserNotification::where('user_id', $this->logistics1->id)->where('title', 'like', 'Seal discrepancy%')->exists(),
            'Without a note: "' . $this->msg($noNote, 'note') . '" With the note: flagged Seal Discrepancy, Logistics 1 notified, and the trip log has it (old BH-0004521, new BH-0004522, the note).');
        foreach (array_slice($p2, 0, 3) as $p) {
            $this->lg('POST', '/trips/receive', ['hub_id' => $this->hubPsg->id, 'tracking' => $p->tracking_number]);
        }
        $noPhoto = $this->lg('POST', "/trips/{$t2->id}/parcels/{$p2[3]->id}/damaged");
        $photo = $this->withToken($this->hub)->post("/api/v1/logistics/trips/{$t2->id}/parcels/{$p2[3]->id}/damaged", ['photo' => UploadedFile::fake()->image('incident_photo.jpg')], ['Accept' => 'application/json']);
        $ask = $this->lg('POST', "/trips/{$t2->id}/close");
        $done = $this->lg('POST', "/trips/{$t2->id}/close", ['confirm' => true]);
        $courierView = $this->c1('GET', "/trips/{$t2->id}");
        $noteReq = $this->c1('POST', "/trips/{$t2->id}/note", ['note' => 'Parcel was loaded at HUB-QC; not found in the truck.']);
        $this->record('COU-ARR-004', $noPhoto->status() === 422 && $photo->status() === 200 && $p2[3]->fresh()->status === 'damaged' && $done->json('trip.status_label') === 'Completed with Discrepancy',
            'Without a photo: "' . $this->msg($noPhoto, 'photo') . '" With incident_photo.jpg: saved; the parcel shows Damaged and the trip is Completed with Discrepancy.');
        $this->record('COU-ARR-003', $ask->status() === 422 && str_contains($this->msg($ask, 'missing'), $p2[4]->tracking_number) && $p2[4]->fresh()->status === 'missing_in_transit'
            && $courierView->json('trip.needs_courier_note') === true && $noteReq->status() === 200
            && UserNotification::where('user_id', $this->seller1->id)->where('title', 'Parcel missing in transit')->exists(),
            'Close asks first: "' . $this->msg($ask, 'missing') . '" After confirming: Missing in Transit, Completed with Discrepancy, Logistics 1 and Seller 1 notified, and Courier 1 must add a note (saved).');

        // COU-TRN-004: breakdown and reassignment on a third trip
        $p3 = $this->tripParcels();
        $t3 = $this->departed($p3);
        $this->onDuty($this->courier2);
        $c2 = $this->tok($this->courier2);
        $this->withToken($this->tok($this->courier1))->post("/api/v1/linehaul/trips/{$t3->id}/delay", ['reason' => 'Vehicle breakdown', 'eta' => now()->addHours(2)->toIso8601String(),
            'photo' => UploadedFile::fake()->image('incident_photo.jpg')], ['Accept' => 'application/json'])->assertOk();
        $this->lg('POST', "/trips/{$t3->id}/assign", ['courier_id' => $this->courier2->id, 'reason' => 'Vehicle breakdown'])->assertOk();
        $told = UserNotification::where('user_id', $this->courier1->user_id)->where('title', "{$t3->code} was reassigned")->exists();
        $early = $this->withToken($c2)->postJson("/api/v1/linehaul/trips/{$t3->id}/arrived", $this->at(self::AT_PSG));
        foreach ($p3 as $p) {
            $this->withToken($c2)->postJson("/api/v1/linehaul/trips/{$t3->id}/scan", ['tracking' => $p->tracking_number]);
        }
        $still = $p3[0]->fresh()->status === 'linehaul';
        $this->withToken($c2)->postJson("/api/v1/linehaul/trips/{$t3->id}/arrived", $this->at(self::AT_PSG))->assertOk();
        $this->lg('POST', "/trips/{$t3->id}/seal", ['seal_number' => 'BH-0004521']);
        foreach ($p3 as $p) {
            $this->lg('POST', '/trips/receive', ['hub_id' => $this->hubPsg->id, 'tracking' => $p->tracking_number]);
        }
        $this->lg('POST', "/trips/{$t3->id}/close");
        $paid = $t3->fresh();
        $this->record('COU-TRN-004', $told && $early->status() === 422 && $still && $paid->paid_courier_id === $this->courier2->id && $t3->events()->where('type', 'reassigned')->where('reason', 'Vehicle breakdown')->exists(),
            'Courier 1 was told the trip was reassigned. Courier 2 could not continue before scanning ("' . $this->msg($early, 'trip') . '"); the parcels stayed In Transit. The reassignment is in the trip log with the reason. The trip\'s pay (₱' . number_format((float) $paid->pay, 2) . ' for Courier 2\'s Van) went to Courier 2 only.',
            'The pay follows the vehicle of the courier who completes the handover (A41): Courier 2 drives a Van (₱600.00), so the case\'s ₱1,000.00 applies when the new courier also drives a 4-Wheeler.');

        // COU-PAY-001 – 004
        $t4 = $this->trip1(false, $this->tripParcels(1));
        $this->onDuty($this->courier1);
        $this->lg('POST', "/trips/{$t4->id}/assign", ['courier_id' => $this->courier1->id]);
        $this->c1('POST', "/trips/{$t4->id}/decline", ['reason' => 'Too far']);
        $h = collect($this->c1('GET', '/history?from=' . today()->toDateString() . '&to=' . today()->toDateString())->json('data'));
        $results = $h->pluck('result')->countBy()->all();
        $this->record('COU-PAY-001', ($results['Completed'] ?? 0) === 1 && ($results['Completed with Discrepancy'] ?? 0) === 1 && ($results['Declined'] ?? 0) === 1 && ($results['Reassigned'] ?? 0) === 1
            && $h->pluck('code')->count() === $h->count(),
            'History (date filter = today): ' . $h->map(fn ($r) => "{$r['code']} {$r['route']} {$r['parcels']} parcel(s) {$r['result']} ₱" . number_format($r['pay'], 2))->implode('; ') . '.');
        $e = $this->c1('GET', '/earnings')->json();
        $this->record('COU-PAY-002', (float) $e['today'] === 2000.0 && (float) $e['week'] >= 2000.0,
            'Today ₱' . number_format($e['today'], 2) . ' (2 completed × ₱1,000.00 + declined ₱0.00 + reassigned away ₱0.00); this week ₱' . number_format($e['week'], 2) . '.');
        $e2 = $this->withToken($c2)->getJson('/api/v1/linehaul/earnings')->json();
        $this->record('COU-PAY-003', (float) $e2['today'] === 600.0 && (float) $e2['pay_per_trip'] === 600.0, 'Courier 2 (Van): the trip pays ₱' . number_format($e2['today'], 2) . '.');
        $edge = $this->c1('GET', '/history?from=' . today()->toDateString() . '&to=' . today()->toDateString())->json('data');
        $bad = $this->c1('GET', '/history?from=' . today()->toDateString() . '&to=' . today()->subDay()->toDateString());
        $this->record('COU-PAY-004', count($edge) === 4 && $bad->status() === 422, 'A range starting and ending on the trips\' date includes them (' . count($edge) . ' trips). End before start: "' . $this->msg($bad, 'to') . '"');
        $other = $this->c1('GET', '/earnings?courier_id=' . $this->courier2->id)->json();
        $this->record('COU-PAY-005', (float) $other['today'] === 2000.0 && $other['vehicle_type'] === '4-Wheeler Closed Van Truck',
            'The earnings request has no courier ID: adding Courier 2\'s ID still returns Courier 1\'s own earnings (no Courier 2 data).');
        $this->assertNotEmpty(static::$results);
    }
}
