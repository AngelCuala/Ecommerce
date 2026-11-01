<?php

namespace Tests\Feature\Api;

use App\Models\ParcelPickup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** The app's rider side, kept 1:1 with the website: acknowledge / decline / on my way, Rider Pay periods, the remittance receipt, Account. */
class RiderWebParityTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seedRiderTestData();
        $this->token = $this->rider1->user->createToken('t')->plainTextToken;
    }

    private function api(string $method, string $uri, array $data = [])
    {
        $this->app['auth']->forgetGuards();
        return $this->withToken($this->token)->json($method, '/api/v1/rider' . $uri, $data);
    }

    public function test_acknowledge_on_my_way_and_decline_an_assigned_job(): void
    {
        $pu = $this->pickupFor($this->rider1);
        $jobs = $this->api('GET', '/jobs')->assertOk();
        $this->assertFalse($jobs->json('pickups.0.acknowledged'));
        $this->assertContains('Vehicle problem', $jobs->json('decline_reasons'));

        $this->api('POST', "/jobs/pickup/{$pu->id}/acknowledge")->assertOk()->assertJsonPath('pickups.0.acknowledged', true);
        $this->api('POST', "/pickups/{$pu->id}/arriving")->assertOk()->assertJsonPath('pickups.0.on_the_way', true);
        $this->api('POST', "/jobs/pickup/{$pu->id}/decline")->assertStatus(422);
        $this->api('POST', "/jobs/pickup/{$pu->id}/decline", ['reason' => 'Vehicle problem'])->assertOk();
        $this->assertSame('declined', ParcelPickup::find($pu->id)->status);

        // Another rider's job: not found.
        $other = $this->pickupFor($this->rider2);
        $this->api('POST', "/jobs/pickup/{$other->id}/acknowledge")->assertNotFound();
    }

    public function test_rider_pay_by_period_and_the_remittance_receipt(): void
    {
        $d = $this->deliveryFor($this->rider1, 'out_for_delivery');
        $this->api('POST', "/deliveries/{$d->id}/deliver", [])->assertStatus(422);   // photo, OTP and COD needed
        $this->app['auth']->forgetGuards();
        $this->withToken($this->token)->post("/api/v1/rider/deliveries/{$d->id}/deliver", [
            'pod_photo' => UploadedFile::fake()->image('pod.jpg'), 'otp' => $d->fresh()->otp_code ?? '000000', 'cod_collected' => (string) $d->cod_amount,
        ], ['Accept' => 'application/json']);

        $pay = $this->api('GET', '/earnings?period=month')->assertOk();
        $this->assertSame('month', $pay->json('period'));
        $this->assertArrayHasKey('last_week', $pay->json('periods'));
        $this->assertArrayHasKey('deliveries', $pay->json('breakdown'));
        $this->assertArrayHasKey('today', $pay->json('cards'));
        $this->api('GET', '/earnings?period=year')->assertStatus(422);

        $cod = $this->api('GET', '/cod')->assertOk();
        if ((float) $cod->json('balance') > 0) {
            $sent = $this->api('POST', '/cod/remit', ['amount' => $cod->json('balance'), 'request_token' => $cod->json('request_token')])->assertOk();
            $id = $sent->json('remittance_id');
            $this->assertNotNull($id);
            $this->api('GET', "/cod/remittances/{$id}")->assertOk()->assertJsonPath('receipt.status', 'pending');
        }
        $this->api('GET', '/cod/remittances/999999')->assertNotFound();
    }

    public function test_account_has_the_rider_profile_and_numbers(): void
    {
        $this->app['auth']->forgetGuards();
        $a = $this->withToken($this->token)->getJson('/api/v1/courier/account')->assertOk();
        $this->assertSame('Pedro Jose Garcia', $a->json('rider.full_name'));
        $this->assertArrayHasKey('earned', $a->json('stats'));
        $this->assertContains('Motorcycle', $a->json('vehicles'));
    }
}
