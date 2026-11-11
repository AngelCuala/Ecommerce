<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\ReturnShipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** The app's Shipment Board page keys, Failed / Flagged lists, hub-to-hub sorting and Return Items (hub and rider). */
class LogisticsReturnsApiTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
    }

    /** Each app call signs in with its own token (as the app does), not the web session of an earlier step. */
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

    private function api(string $method, string $uri, array $data = [], $user = null)
    {
        $token = ($user ?? $this->logistics1)->createToken('t')->plainTextToken;
        return $this->withToken($token)->json($method, '/api/v1' . $uri, $data);
    }

    public function test_board_page_keys_failed_deliveries_and_hub_to_hub_sorting(): void
    {
        $pickup = $this->parcel('pending_pickup');
        $board = $this->api('GET', '/logistics/tracking?tab=all')->assertOk();
        $this->assertSame('pickup_requests', collect($board->json('data'))->firstWhere('id', $pickup->id)['page']);

        $this->api('GET', '/logistics/failed-deliveries')->assertOk()->assertJsonStructure(['data', 'counts', 'reasons']);

        $this->logistics1->update(['assigned_municipality' => 'Quezon City']);
        $this->logistics2->update(['assigned_municipality' => 'Davao City']);
        $far = $this->parcel('picked_up', ['destination_municipality' => 'Davao City', 'area_id' => null, 'received_at' => now()]);
        $sorting = $this->api('GET', '/logistics/sorting')->assertOk();
        $this->assertNotEmpty($sorting->json('centers'));
        $this->api('POST', "/logistics/sorting/{$far->id}/transfer", ['center_id' => $this->logistics2->id])->assertOk();
        $this->assertSame($this->logistics2->id, $far->fresh()->next_center_id);
    }

    public function test_return_items_through_the_app(): void
    {
        $this->online($this->rider1);
        $parcel = $this->parcel('delivered');
        $parcel->order->update(['status' => Order::DELIVERED, 'delivered_at' => now()]);
        $this->actingAs($this->buyer1)->post(route('orders.return', $parcel->order_id), [
            'reason' => 'Damaged item', 'details' => 'The box arrived crushed and wet.', 'evidence' => UploadedFile::fake()->image('d.jpg'),
        ])->assertSessionHas('success');
        $rs = ReturnShipment::firstOrFail();

        $list = $this->api('GET', '/logistics/returns')->assertOk();
        $this->assertSame([$rs->id, 'assign_pickup'], [$list->json('data.0.id'), $list->json('data.0.action')]);
        $this->api('POST', "/logistics/returns/{$rs->id}/assign", ['rider_id' => $this->rider1->id])->assertOk();

        $jobs = $this->api('GET', '/rider/jobs', [], $this->rider1->user)->assertOk();
        $this->assertSame($rs->id, $jobs->json('return_jobs.0.id'));
        $this->api('POST', "/rider/return-jobs/{$rs->id}/accept", [], $this->rider1->user)->assertOk();
        $this->api('POST', "/rider/return-jobs/{$rs->id}/confirm", ['tracking_number' => $parcel->waybill_number, 'proof_photo' => UploadedFile::fake()->image('i.jpg')], $this->rider1->user)->assertOk();
        $this->assertSame('picked_up', $rs->fresh()->status);

        $this->api('POST', "/logistics/returns/{$rs->id}/receive")->assertOk();
        $this->api('POST', "/logistics/returns/{$rs->id}/to-seller", ['rider_id' => $this->rider1->id])->assertOk();
        $this->api('POST', "/rider/return-jobs/{$rs->id}/handed", [], $this->rider1->user)->assertOk();
        $this->assertSame('returned', $rs->fresh()->status);
    }
}
