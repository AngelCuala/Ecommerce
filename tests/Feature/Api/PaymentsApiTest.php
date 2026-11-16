<?php

namespace Tests\Feature\Api;

use App\Models\ParcelDelivery;
use App\Models\RiderRemittance;
use App\Models\RiderWalletEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** The app's copies of the PAYMENTS_SPEC pages: rider wallet ledger and withdrawal, counted remittance and deduct, ALVY Remittance. */
class PaymentsApiTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['marketplace.rider_share_since' => now()->subDay()->toDateTimeString()]);
        $this->seedRiderTestData();
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

    private function as($user)
    {
        return $this->withToken($user->createToken('t')->plainTextToken);
    }

    private function delivered(float $cod = 0): ParcelDelivery
    {
        $p = $this->parcel('delivered');
        $p->order->update(['shipping_fee' => 100]);
        \App\Models\Parcel::where('order_id', $p->order_id)->whereKeyNot($p->id)->delete();

        return ParcelDelivery::create(['parcel_id' => $p->id, 'rider_id' => $this->rider1->id, 'status' => 'delivered', 'attempt' => 1,
            'delivered_at' => now(), 'cod_amount' => $cod, 'cod_collected' => $cod]);
    }

    public function test_the_rider_wallet_and_a_bank_withdrawal(): void
    {
        $this->delivered();
        $this->delivered(1100);
        $w = $this->as($this->rider1->user)->getJson('/api/v1/rider/wallet')->assertOk();
        $this->assertSame([30.0, 30.0, 1100.0], [(float) $w->json('available'), (float) $w->json('expected'), (float) $w->json('cash_to_remit')]);
        $this->assertSame('delivery_credit', $w->json('entries.0.type'));

        $this->as($this->rider1->user)->postJson('/api/v1/rider/wallet/cashout', ['amount' => 10, 'method' => 'bank', 'bank_name' => 'BDO',
            'account_number' => '001234567890', 'account_name' => 'Pedro Garcia'])->assertOk()->assertJsonPath('pending_cashout.destination', 'BDO 001234567890 (Pedro Garcia)');
        $this->assertSame('BDO 001234567890', $this->as($this->rider1->user)->getJson('/api/v1/rider/wallet')->json('accounts.0.label'));
    }

    public function test_reject_and_the_riders_own_history(): void
    {
        $this->delivered(500);
        $this->actingAs($this->rider1->user)->post(route('courier.rider.cod.remit'), ['request_token' => (string) Str::uuid()]);
        $r = RiderRemittance::sole();
        $this->as($this->logistics1)->postJson("/api/v1/logistics/remittances/{$r->id}/reject", ['reason' => 'Cash not handed over'])->assertOk();
        $this->as($this->logistics1)->getJson('/api/v1/logistics/remittances?status=rejected')->assertOk()
            ->assertJsonPath('data.0.status_label', 'Rejected')->assertJsonPath('data.0.reject_reason', 'Cash not handed over');

        $this->as($this->rider1->user)->getJson('/api/v1/rider/cod/history')->assertOk()
            ->assertJsonPath('data.0.receipt', $r->receiptNumber())->assertJsonPath('data.0.status', 'rejected');
        $this->assertSame([], $this->as($this->rider2->user)->getJson('/api/v1/rider/cod/history')->assertOk()->json('data'));   // only theirs
    }

    public function test_counted_remittance_deduct_and_alvy_remittance(): void
    {
        $this->delivered(1100);
        $this->actingAs($this->rider1->user)->post(route('courier.rider.cod.remit'), ['request_token' => (string) Str::uuid()]);
        $r = RiderRemittance::sole();

        $this->as($this->logistics1)->postJson("/api/v1/logistics/remittances/{$r->id}/confirm", ['counted' => 1000])->assertOk();
        $list = $this->as($this->logistics1)->getJson('/api/v1/logistics/remittances')->assertOk();
        $this->assertSame([1000.0, 100.0, true], [(float) $list->json('data.0.counted'), (float) $list->json('data.0.shortfall'), $list->json('data.0.can_deduct')]);
        $this->as($this->logistics1)->postJson("/api/v1/logistics/remittances/{$r->id}/deduct")->assertOk();
        $this->assertSame(-100.0, (float) RiderWalletEntry::where('type', 'shortage_adjustment')->sole()->amount);

        $a = $this->as($this->logistics1)->getJson('/api/v1/logistics/alvy-remittances')->assertOk();
        $this->assertSame(1000.0, (float) $a->json('total'));
        $this->as($this->logistics1)->postJson('/api/v1/logistics/alvy-remittances', [])->assertStatus(422);   // a deposit reference is needed
        $sent = $this->as($this->logistics1)->postJson('/api/v1/logistics/alvy-remittances', ['deposit_reference' => 'BDO-1234'])->assertOk();
        $this->assertSame([[], 'submitted'], [$sent->json('ready'), $sent->json('remittance.status')]);
        $this->as($this->logistics1)->getJson('/api/v1/logistics/alvy-remittances/' . $sent->json('remittance.id'))->assertOk()
            ->assertJsonPath('remittance.items.0.amount_due', 1000);
    }
}
