<?php

namespace Tests\Feature\Api;

use App\Models\Parcel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** The app's logistics side, kept 1:1 with the website: Shipment Tracking board, scan history, transporters, Commissions, waybills. */
class LogisticsWebParityTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('public');
        $this->seedRiderTestData();
        $this->token = $this->logistics1->createToken('test')->plainTextToken;
    }

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

    private function api(string $method, string $uri, array $data = [])
    {
        return $this->withToken($this->token)->json($method, '/api/v1/logistics' . $uri, $data);
    }

    public function test_assignment_and_sorting_have_the_websites_counts(): void
    {
        $a = $this->api('GET', '/assignment')->assertOk();
        $this->assertArrayHasKey('waiting_total', $a->json());
        foreach ($a->json('areas') as $area) {
            $this->assertArrayHasKey('waiting', $area);   // parcels waiting in each barangay
        }
        foreach ($a->json('riders') as $rider) {
            $this->assertArrayHasKey('active_jobs', $rider);   // each rider's open deliveries
        }
        $this->assertSame(array_sum(array_column($a->json('areas'), 'waiting')), $a->json('waiting_total'));

        $s = $this->api('GET', '/sorting')->assertOk();   // everything the app's sorting stats are made from
        $this->assertArrayHasKey('sorted_today', $s->json());
        $this->assertArrayHasKey('sorted', $s->json('areas.0'));
    }

    public function test_tracking_board_receive_with_condition_and_exception(): void
    {
        $parcel = $this->parcel('in_transit_to_hub');
        $board = $this->api('GET', '/tracking?tab=incoming')->assertOk();
        $this->assertSame([$parcel->id, 'incoming'], [$board->json('data.0.id'), $board->json('tab')]);
        $this->assertSame('Incoming Packages', $board->json('tabs.0.label'));
        $this->assertSame(1, $board->json('tabs.0.count'));

        // Scanning finds it and hands back the parcel to open; the scan is in "Recent scans".
        $this->api('POST', '/incoming/scan', ['tracking_number' => $parcel->tracking_number])->assertOk()->assertJsonPath('parcel_id', $parcel->id);
        $this->assertSame(['found', 'Found'], [$this->api('GET', '/incoming')->json('recent_scans.0.result'), $this->api('GET', '/incoming')->json('recent_scans.0.result_label')]);

        $show = $this->api('GET', "/tracking/{$parcel->id}")->assertOk();
        $this->assertTrue($show->json('parcel.actions.receive'));
        $this->assertArrayHasKey('damaged_on_arrival', $show->json('exception_types'));

        $this->api('POST', "/tracking/{$parcel->id}/receive", ['condition' => 'damaged'])->assertStatus(422)
            ->assertJsonValidationErrors(['notes' => 'Describe the damage.']);
        $done = $this->api('POST', "/tracking/{$parcel->id}/receive", ['condition' => 'good'])->assertOk();
        $this->assertFalse($done->json('parcel.actions.receive'));
        $this->assertSame('picked_up', $parcel->fresh()->status);
        $this->assertNotEmpty($done->json('parcel.events'));

        // Another company's parcel: forbidden.
        $other = $this->parcel('picked_up', ['current_sorting_center_id' => $this->logistics2->id]);
        $this->api('GET', "/tracking/{$other->id}")->assertForbidden();
    }

    public function test_transporters_rider_choices_and_transfers(): void
    {
        $this->online($this->rider1);
        $this->assertFalse($this->api('GET', '/riders')->json('data.0.can_transport_transfers'));
        $this->api('POST', "/riders/{$this->rider1->id}/transport")->assertOk()->assertJsonPath('can_transport_transfers', true);
        $this->api('POST', "/riders/{$this->agilaRider->id}/transport")->assertForbidden();

        $this->parcel('pickup_approved');
        $choices = collect($this->api('GET', '/dispatch')->assertOk()->json('rider_choices'));
        $this->assertTrue($choices->firstWhere('id', $this->rider1->id)['available']);
        $this->assertSame('suspended', $choices->firstWhere('id', $this->rider3->id)['why']);

        $atHub = $this->parcel('picked_up', ['received_at' => now()]);
        $t = $this->api('GET', '/transfers')->assertOk();
        $this->assertContains('Pedro Jose Garcia', collect($t->json('transporters'))->pluck('name')->all());
        $this->assertArrayHasKey('next', collect($t->json('parcels'))->firstWhere('id', $atHub->id));
    }

    public function test_commissions_and_waybills(): void
    {
        $p = $this->parcel('delivered');
        $c = $this->api('GET', '/commissions?period=month')->assertOk();
        $this->assertSame('month', $c->json('period'));
        $this->assertArrayHasKey('net', $c->json('summary'));
        $this->assertArrayHasKey('earned', $c->json('statuses'));
        $this->api('GET', '/commissions?status=nope')->assertStatus(422);
        $this->withToken($this->token)->get('/api/v1/logistics/commissions/export?format=csv')->assertOk()
            ->assertHeader('content-disposition');
        $this->api('GET', '/commissions/export?format=pdf')->assertStatus(422);

        $w = $this->api('GET', '/waybills?ids[]=' . $p->id)->assertOk();
        $this->assertMatchesRegularExpression('/^PH\d+$/', $w->json('waybills.0.waybill'));
        $this->assertNotNull(Parcel::find($p->id)->waybill_number);
        $this->api('GET', '/waybills?ids[]=999999')->assertNotFound();
    }
}
