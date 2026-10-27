<?php

namespace Tests\Feature\Api;

use App\Models\Courier;
use App\Models\Parcel;
use App\Models\ParcelDelivery;
use App\Models\RiderRemittance;
use App\Models\User;
use App\Services\PsgcDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Phase 5 of the app conversion: the logistics company (sorting center) through /api/v1, with the web's rules. */
class LogisticsApiTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    private const LUISIANA = '043412000';
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
        $this->seedRiderTestData();
        $this->token = $this->logistics1->createToken('test')->plainTextToken;
    }

    /** Each app request starts fresh: in a test the guards and default headers would carry over. */
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        $app = str_contains($uri, '/api/v1');
        if ($app) {
            $this->app['auth']->forgetGuards();
        }
        $response = parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
        if ($app) {
            $this->defaultHeaders = [];
        }
        return $response;
    }

    private function api(string $method, string $uri, array $data = [], ?string $token = null)
    {
        return $this->withToken($token ?? $this->token)->json($method, '/api/v1/logistics' . $uri, $data);
    }

    public function test_dashboard_and_shipments(): void
    {
        $this->parcel('pending_pickup');
        $this->parcel('delivered');
        $d = $this->api('GET', '/dashboard')->assertOk();
        $this->assertSame(['pending', 1], [$d->json('shipment_counts.0.key'), $d->json('shipment_counts.0.count')]);
        $this->assertSame(1, $d->json('stats.pending_pickup'));
        $this->api('GET', '/dashboard?from=2026-10-10&to=2026-10-01')->assertStatus(422)
            ->assertJsonValidationErrors(['to' => 'The end date must be on or after the start date.']);

        $s = $this->api('GET', '/shipments?status=delivered')->assertOk();
        $this->assertSame(['Delivered', 'Delivered'], [$s->json('data.0.status_label'), $s->json('tabs.2.label')]);
        $this->assertSame(1, $s->json('meta.total'));
        $this->assertSame('Delivered', $this->api('GET', '/parcels/' . $s->json('data.0.id'))->assertOk()->json('parcel.status_label'));
    }

    public function test_pickup_receive_sort_assign_and_monitor(): void
    {
        $parcel = $this->parcel('pending_pickup');
        $this->assertSame($parcel->tracking_number, $this->api('GET', '/pickup-requests')->assertOk()->json('data.0.tracking'));
        // A request not routed to any company yet can be opened too (any company may confirm it, like on the website).
        $unrouted = $this->parcel('pending_pickup', ['current_sorting_center_id' => null]);
        $this->api('GET', "/parcels/{$unrouted->id}")->assertOk()->assertJsonPath('parcel.status_label', 'Ready for Pickup');
        $this->api('POST', "/pickup-requests/{$parcel->id}/approve")->assertOk()
            ->assertJsonPath('message', "Pickup for {$parcel->tracking_number} confirmed.");
        $this->api('POST', "/pickup-requests/{$parcel->id}/approve")->assertStatus(422)
            ->assertJsonPath('message', "{$parcel->tracking_number} is already Pickup Confirmed.");

        $this->api('POST', '/incoming/scan', ['tracking_number' => 'ALVY-NOPE'])->assertStatus(422)
            ->assertJsonPath('message', 'Parcel ALVY-NOPE not found at your sorting center.');
        $this->api('POST', '/incoming/scan', ['tracking_number' => $parcel->tracking_number])->assertOk()
            ->assertJsonPath('message', "{$parcel->tracking_number} received at the sorting center. Ready for sorting.");
        $this->assertSame(1, $this->api('GET', '/incoming')->assertOk()->json('stats.for_sorting'));

        $sorting = $this->api('GET', '/sorting')->assertOk();
        $this->assertSame($parcel->id, $sorting->json('pending.0.id'));
        $this->api('POST', "/sorting/{$parcel->id}", ['area_id' => 99999])->assertStatus(422)
            ->assertJsonValidationErrors(['area_id' => 'That barangay is outside your municipality coverage.']);
        $this->api('POST', "/sorting/{$parcel->id}", ['area_id' => $this->central->id])->assertOk()
            ->assertJsonPath('message', "{$parcel->tracking_number} sorted to Central.")->assertJsonCount(0, 'pending');

        $a = $this->api('GET', '/assignment')->assertOk();
        $this->assertSame($parcel->id, $a->json('data.0.id'));
        $this->assertContains('Pedro Jose Garcia', collect($a->json('riders'))->pluck('full_name')->all());
        $this->api('POST', "/assignment/{$parcel->id}", ['rider_id' => $this->agilaRider->id])->assertStatus(422)
            ->assertJsonPath('message', 'You can only assign riders that belong to your sorting center.');
        $this->api('POST', "/assignment/{$parcel->id}", ['rider_id' => $this->rider1->id])->assertOk()
            ->assertJsonPath('message', "{$parcel->tracking_number} assigned to Pedro Jose Garcia.");

        // Monitoring, a logistics override (with a reason), then reschedule a failed attempt.
        $m = $this->api('GET', '/monitoring')->assertOk();
        $delivery = $m->json('data.0');
        $this->assertSame(['assigned', ['out_for_delivery', 'failed']], [$delivery['status'], $delivery['actions']['override']]);
        $this->api('POST', "/deliveries/{$delivery['id']}/status", ['status' => 'failed', 'remarks' => 'short'])->assertStatus(422)
            ->assertJsonValidationErrors(['remarks' => 'The reason must be 10 to 300 characters.']);
        $this->api('POST', "/deliveries/{$delivery['id']}/status", ['status' => 'failed', 'remarks' => 'Rider app offline, buyer not home'])->assertOk();
        $this->assertTrue($this->api('GET', '/monitoring?status=failed')->json('data.0.actions.reschedule'));
        $this->api('POST', "/deliveries/{$delivery['id']}/reschedule")->assertOk()
            ->assertJsonPath('message', "{$parcel->tracking_number} rescheduled. Assign a rider for the next attempt.");
        $this->assertSame('sorted', $parcel->fresh()->status);
    }

    public function test_dispatch_rts_exception_and_transfers(): void
    {
        $this->online($this->rider1, $this->rider2);
        $parcel = $this->parcel('pickup_approved');
        $board = $this->api('GET', '/dispatch')->assertOk();
        $this->assertSame([$parcel->id, 'pickup'], [$board->json('jobs.0.id'), $board->json('jobs.0.job_kind')]);

        $this->api('POST', "/dispatch/{$parcel->id}/auto")->assertOk()->assertJsonPath('jobs.0.offer.seconds_left', fn ($s) => $s > 0);
        $other = $this->parcel('pickup_approved');
        $this->api('POST', "/dispatch/{$other->id}/assign", ['rider_id' => $this->rider3->id])->assertStatus(422)
            ->assertJsonPath('message', 'Ramon Cruz is suspended.');
        $this->api('POST', "/dispatch/{$other->id}/assign", ['rider_id' => $this->rider2->id])->assertOk()
            ->assertJsonPath('message', "{$other->tracking_number} assigned to Mark Anthony Bautista.");
        $this->api('POST', "/dispatch/{$other->id}/reassign", ['rider_id' => $this->rider1->id])->assertOk()
            ->assertJsonPath('message', "{$other->tracking_number} reassigned to Pedro Jose Garcia.");

        // Return to Sender: give the return trip to a rider.
        $rts = $this->parcel('return_to_sender');
        $this->assertSame($rts->id, $this->api('GET', '/rts')->assertOk()->json('parcels.0.id'));
        $this->api('POST', "/rts/{$rts->id}", ['rider_id' => $this->rider2->id])->assertOk()
            ->assertJsonPath('message', "{$rts->tracking_number} will be returned to the seller by Mark Anthony Bautista.")
            ->assertJsonPath('parcels.0.rts_rider', 'Mark Anthony Bautista');

        // Damaged needs a photo (multipart, like the website's form).
        $broken = $this->parcel('sorted');
        $this->withToken($this->token)->post("/api/v1/logistics/parcels/{$broken->id}/exception", ['type' => 'damaged', 'reason' => 'Crushed in the truck'], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['photo' => 'Add a photo of the damage.']);
        $this->withToken($this->token)->post("/api/v1/logistics/parcels/{$broken->id}/exception", ['type' => 'damaged', 'reason' => 'Crushed in the truck',
            'photo' => UploadedFile::fake()->image('dent.jpg')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('parcel.status', 'damaged')->assertJsonPath('parcel.can_mark_exception', false);

        // Transfers: to another hub, which accepts it.
        $atHub = $this->parcel('picked_up');
        $this->logistics2->forceFill(['assigned_municipality' => 'Luisiana', 'assigned_municipality_code' => self::LUISIANA])->save();
        $this->assertContains($atHub->id, collect($this->api('GET', '/transfers')->json('parcels'))->pluck('id')->all());
        $this->api('POST', '/transfers', ['parcel_id' => $atHub->id, 'to_sorting_center_id' => $this->logistics2->id, 'reason' => 'Closer hub'])->assertOk()
            ->assertJsonPath('outgoing.0.tracking', $atHub->tracking_number);
        $t2 = $this->logistics2->createToken('t')->plainTextToken;
        $incoming = $this->api('GET', '/transfers', [], $t2)->assertOk()->json('incoming.0');
        $this->api('POST', "/transfers/{$incoming['id']}/accept", [], $t2)->assertOk()->assertJsonCount(0, 'incoming');
        $this->assertSame($this->logistics2->id, (int) $atHub->fresh()->current_sorting_center_id);
        // Another company's parcel: forbidden.
        $this->api('GET', "/parcels/{$atHub->id}")->assertNotFound();
    }

    public function test_riders_remittances_and_courier_applications(): void
    {
        $r = $this->api('GET', '/riders?tab=inactive')->assertOk();
        $this->assertSame('Ramon Cruz', $r->json('data.0.full_name'));
        $this->assertSame(1, collect($r->json('tabs'))->firstWhere('key', 'inactive')['count']);

        $this->api('POST', "/riders/{$this->rider2->id}/toggle", ['reason' => 'late'])->assertStatus(422)
            ->assertJsonValidationErrors(['reason' => 'The reason must be 10 to 300 characters.']);
        $this->api('POST', "/riders/{$this->rider2->id}/toggle", ['reason' => 'Three late deliveries this week'])->assertOk()
            ->assertJsonPath('message', 'Mark Anthony Bautista suspended. They are Offline and get no jobs.')->assertJsonPath('rider.suspended', true);
        $this->api('POST', "/riders/{$this->rider2->id}/toggle")->assertOk()->assertJsonPath('rider.suspended', false);

        $this->rider1->update(['needs_reverification' => true]);
        $this->api('GET', "/riders/{$this->rider1->id}")->assertOk()->assertJsonPath('rider.needs_reverification', true)->assertJsonPath('rider.cod_balance', 0);
        $this->api('POST', "/riders/{$this->rider1->id}/verify")->assertOk()->assertJsonPath('rider.needs_reverification', false);
        $this->withToken($this->token)->get("/api/v1/logistics/riders/{$this->rider1->id}/documents/license", ['Accept' => 'application/json'])->assertOk();
        $this->api('GET', "/riders/{$this->agilaRider->id}")->assertForbidden();

        // COD remittance confirmed by the company (a shortfall is flagged).
        $remit = RiderRemittance::create(['rider_id' => $this->rider1->id, 'sorting_center_id' => $this->logistics1->id, 'amount' => 450,
            'expected_amount' => 500, 'status' => 'pending', 'request_token' => (string) \Illuminate\Support\Str::uuid()]);
        $this->assertSame('pending', $this->api('GET', '/remittances')->assertOk()->json('data.0.status'));
        $this->api('POST', "/remittances/{$remit->id}/confirm")->assertOk()
            ->assertJsonPath('message', 'Remittance confirmed. Shortfall of ₱50.00 flagged.')->assertJsonPath('data.0.status', 'confirmed');

        // A courier who applied to this company.
        $u = User::create(['name' => 'Mario Santos', 'email' => 'mario@example.com', 'password' => bcrypt('x'), 'role' => 'courier_pending', 'approval_status' => 'pending']);
        $c = Courier::create(['user_id' => $u->id, 'sorting_center_id' => $this->logistics1->id, 'last_name' => 'Santos', 'first_name' => 'Mario', 'sex' => 'Male',
            'contact_no' => '09181234568', 'birthday' => '1995-05-20', 'age' => 31, 'province' => 'Laguna', 'municipality' => 'Magdalena', 'barangay' => 'Poblacion',
            'vehicle_type' => 'Motorcycle', 'plate_number' => 'ABC1234', 'or_cr_path' => 'a', 'id_license_path' => 'b', 'status' => 'pending', 'submitted_at' => now()]);
        $this->assertSame('Mario Santos', $this->api('GET', '/couriers')->assertOk()->json('data.0.name'));
        $this->assertSame('Magdalena', collect(explode(', ', $this->api('GET', "/couriers/{$c->id}")->assertOk()->json('courier.address')))->filter(fn ($x) => $x === 'Magdalena')->first());
        $this->api('POST', "/couriers/{$c->id}/reject", [])->assertStatus(422)
            ->assertJsonValidationErrors(['rejection_reason' => 'Please tell the applicant why the application was rejected.']);
        $this->api('POST', "/couriers/{$c->id}/approve")->assertOk()
            ->assertJsonPath('message', 'Mario Santos is now an approved courier for your sorting center.');
    }

    public function test_coverage_and_adding_a_rider(): void
    {
        $this->api('POST', '/riders', ['full_name' => 'New Rider', 'barangay_psgc' => '0434120001'])->assertStatus(422)
            ->assertJsonPath('message', 'Assign a municipality to your account before adding riders.');

        $this->logistics1->forceFill(['assigned_municipality' => 'Luisiana', 'assigned_municipality_code' => self::LUISIANA,
            'assigned_province' => 'Laguna', 'assigned_province_code' => '043400000'])->save();
        $areas = $this->api('GET', '/areas')->assertOk();
        $this->assertSame([true, 23], [$areas->json('has_municipality'), $areas->json('barangay_total')]);
        $b = collect(app(PsgcDirectory::class)->barangaysByMunicipality(self::LUISIANA))->first();

        $this->api('POST', '/areas', ['barangay_psgc' => $b['psgc'], 'barangay_code' => $b['code']])->assertOk()
            ->assertJsonPath('message', $b['name'] . ' added to your coverage.');
        $this->api('POST', '/areas', ['barangay_psgc' => $b['psgc'], 'barangay_code' => $b['code']])->assertStatus(422)
            ->assertJsonPath('message', $b['name'] . ' is already in your coverage list.');

        $this->api('POST', '/riders', ['full_name' => 'Rosa Dela Pena', 'phone' => '09171230000', 'vehicle_type' => 'Motorcycle',
            'barangay_psgc' => $b['psgc'], 'barangay_code' => $b['code']])->assertOk()
            ->assertJsonPath('message', "Rosa Dela Pena added as a rider for {$b['name']}.");

        $area = collect($this->api('GET', '/areas')->json('areas'))->firstWhere('name', $b['name']);
        $this->api('DELETE', "/areas/{$area['id']}")->assertStatus(422)
            ->assertJsonPath('message', 'Cannot remove ' . $b['name'] . ' — it still has riders or parcels linked to it.');
    }

    public function test_rates_reports_activity_chat_and_account(): void
    {
        $rates = $this->api('GET', '/rates')->assertOk();
        $this->assertSame('NCR → NCR', $rates->json('rates.0.label'));
        $this->api('POST', '/rates', ['route' => 'NCR-NCR', 'first_kg' => '0', 'additional_kg' => '15'])->assertStatus(422)
            ->assertJsonValidationErrors(['first_kg' => 'The first-kg rate must be an amount of at least ₱0.01.']);
        $this->api('POST', '/rates', ['route' => 'NCR-NCR', 'first_kg' => '55.00', 'additional_kg' => '15'])->assertOk()
            ->assertJsonPath('message', 'Rate for NCR → NCR saved. It applies to checkouts started from now on.')->assertJsonPath('rates.0.first_kg', 55);
        $uncovered = $rates->json('uncovered.0');
        $this->api('POST', '/zones', ['province_code' => $uncovered['code'], 'first_kg' => '120', 'additional_kg' => '40'])->assertOk()
            ->assertJsonPath('message', "{$uncovered['name']} is now covered. Buyers there can check out.");

        $report = $this->api('GET', '/reports?from=2026-10-01&to=2026-10-03')->assertOk();
        $this->assertCount(3, $report->json('daily'));
        $csv = $this->withToken($this->token)->get('/api/v1/logistics/reports/export?format=csv&tab=rider&from=2026-10-01&to=2026-10-03', ['Accept' => 'application/json']);
        $csv->assertOk();
        $this->assertStringContainsString('Rider,Area,Assigned,Delivered,Failed,Returned,"Success Rate %",Active', $csv->streamedContent());
        $pdf = $this->withToken($this->token)->get('/api/v1/logistics/reports/export?format=pdf&tab=rider&from=2026-10-01&to=2026-10-03', ['Accept' => 'application/json']);
        $pdf->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->assertNotEmpty($this->api('GET', '/activity')->assertOk()->json('data'));

        $rider = $this->rider1->user;
        $sent = $this->api('POST', '/chat', ['receiver_id' => $rider->id, 'body' => 'Pakidala sa hub bago mag-5.'])->assertOk();
        $this->assertSame(['Pakidala sa hub bago mag-5.', true], [$sent->json('messages.0.body'), $sent->json('messages.0.mine')]);
        $this->assertCount(1, $this->api('GET', "/chat/poll?contact_id={$rider->id}")->assertOk()->json('messages'));
        // The rider reads it in the app's Messages (the web's chat widget on the courier pages).
        $riderToken = $rider->createToken('t')->plainTextToken;
        $threads = $this->withToken($riderToken)->getJson('/api/v1/messages')->assertOk();
        $this->assertStringContainsString('Pakidala sa hub', json_encode($threads->json()));

        $this->api('PUT', '/account', ['name' => 'Bilis Hatid Ops', 'email' => 'not-an-email'])->assertStatus(422)->assertJsonValidationErrors('email');
        $this->api('PUT', '/account', ['name' => 'Bilis Hatid Ops', 'email' => $this->logistics1->email])->assertOk()
            ->assertJsonPath('company.name', 'Bilis Hatid Ops')->assertJsonPath('company.business_name', 'Bilis Hatid Express Inc.');
    }

    public function test_only_logistics_accounts(): void
    {
        $this->api('GET', '/dashboard', [], $this->buyer1->createToken('t')->plainTextToken)->assertForbidden()
            ->assertJsonPath('message', 'This part of the app is for logistics companies.');
        $this->api('GET', '/dashboard', [], $this->rider1->user->createToken('t')->plainTextToken)->assertForbidden();
        $this->withToken($this->token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.home', 'logistics');
    }
}
