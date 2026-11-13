<?php

namespace Tests\Feature\Api;

use App\Models\ContactStrike;
use App\Models\LinehaulTrip;
use App\Models\ParcelDelivery;
use App\Models\RiderWalletEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\LinehaulTestData;
use Tests\TestCase;

/** The app's rider wallet and GCash cash-out, the hub's Rider Pay, seller strikes and appeals, Trusted Seller and delivery steps. */
class WalletStrikesApiTest extends TestCase
{
    use RefreshDatabase, LinehaulTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        $this->seedLinehaulTestData();
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

    private function api(string $method, string $uri, array $data, $user)
    {
        return $this->withToken($user->createToken('t')->plainTextToken)->json($method, '/api/v1' . $uri, $data);
    }

    public function test_rider_pay_wallet_and_gcash_cash_out(): void
    {
        ParcelDelivery::create(['parcel_id' => $this->parcel('delivered')->id, 'rider_id' => $this->rider1->id, 'status' => 'delivered',
            'delivered_at' => now(), 'cod_amount' => 0, 'cod_collected' => 0]);

        $pay = $this->api('GET', '/logistics/rider-pay', [], $this->logistics1)->assertOk();
        $row = collect($pay->json('riders'))->firstWhere('id', $this->rider1->id);
        $this->assertSame(50.0, (float) $row['to_send']);
        $this->api('POST', "/logistics/rider-pay/{$this->rider1->id}/send", [], $this->logistics1)->assertOk();
        // Earlier pay already in the wallet.
        RiderWalletEntry::create(['rider_id' => $this->rider1->id, 'sorting_center_id' => $this->logistics1->id, 'type' => 'pay', 'amount' => 200, 'jobs' => 4]);

        $wallet = $this->api('GET', '/rider/wallet', [], $this->rider1->user)->assertOk();
        $this->assertSame([250.0, 0.0], [(float) $wallet->json('balance'), (float) $wallet->json('to_send')]);
        $this->api('POST', '/rider/wallet/cashout', ['amount' => 50, 'gcash_number' => '09171234567', 'gcash_name' => 'Pedro'], $this->rider1->user)
            ->assertStatus(422);
        $this->api('POST', '/rider/wallet/cashout', ['amount' => 150, 'gcash_number' => '09171234567', 'gcash_name' => 'Pedro Garcia'], $this->rider1->user)
            ->assertOk()->assertJsonPath('pending_cashout.gcash_number', '09171234567');

        $id = $this->api('GET', '/logistics/rider-pay', [], $this->logistics1)->json('cashouts.0.id');
        $this->api('POST', "/logistics/rider-pay/cashouts/{$id}/paid", ['reference' => '9876543210'], $this->logistics1)->assertOk();
        $this->assertSame('paid', RiderWalletEntry::find($id)->status);
        $this->assertSame(100.0, (float) $this->api('GET', '/rider/wallet', [], $this->rider1->user)->json('balance'));
    }

    public function test_seller_strikes_appeal_and_trusted_seller(): void
    {
        $strike = ContactStrike::create(['user_id' => $this->seller1->id, 'kinds' => 'phone']);
        $list = $this->api('GET', '/seller/strikes', [], $this->seller1)->assertOk();
        $this->assertSame([1, true], [$list->json('count'), $list->json('strikes.0.can_appeal')]);
        $this->api('POST', "/seller/strikes/{$strike->id}/appeal", ['reason' => 'It was a model number, not a phone.'], $this->seller1)
            ->assertOk()->assertJsonPath('strikes.0.appeal_status', 'pending');

        $this->api('GET', '/seller/dashboard', [], $this->seller1)->assertOk()->assertJsonPath('trusted.trusted', false);
        $this->getJson('/api/v1/stores/' . $this->seller1->id)->assertOk()->assertJsonPath('store.trusted', false);
    }

    public function test_delivery_steps_in_the_app(): void
    {
        $this->logistics1->update(['assigned_municipality' => 'Quezon City']);
        $this->logistics2->update(['assigned_municipality' => 'Davao City']);
        $this->parcel('sorted', ['next_center_id' => $this->logistics2->id, 'area_id' => null, 'current_hub_id' => $this->hubQc->id]);

        $made = $this->api('POST', '/logistics/trips', ['origin_hub_id' => $this->hubQc->id, 'destination_hub_id' => $this->hubDvo->id], $this->logistics1)
            ->assertCreated()->assertJsonPath('trip.stage', 1)->assertJsonPath('trip.stage_label', 'Delivery created');
        $id = $made->json('trip.id');
        $this->assertSame(0, LinehaulTrip::find($id)->onBoard()->count());
        $this->api('POST', "/logistics/trips/{$id}/auto", [], $this->logistics1)->assertOk();
        $this->assertSame(1, LinehaulTrip::find($id)->onBoard()->count());
    }
}
