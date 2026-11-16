<?php

namespace Tests\Feature\Logistics;

use App\Models\HubRemittance;
use App\Models\ParcelDelivery;
use App\Models\RiderRemittance;
use App\Services\RiderWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** The hub accepts or rejects a rider's COD remittance; everyone who remits has a history only they see. */
class RemittanceHistoryTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['marketplace.rider_share_since' => now()->subDay()->toDateTimeString()]);
        $this->seedRiderTestData();
    }

    private function delivered(float $cod): void
    {
        $p = $this->parcel('delivered');
        $p->order->update(['shipping_fee' => 100]);
        \App\Models\Parcel::where('order_id', $p->order_id)->whereKeyNot($p->id)->delete();
        ParcelDelivery::create(['parcel_id' => $p->id, 'rider_id' => $this->rider1->id, 'status' => 'delivered', 'attempt' => 1,
            'delivered_at' => now(), 'cod_amount' => $cod, 'cod_collected' => $cod]);
    }

    private function remit(): RiderRemittance
    {
        $this->actingAs($this->rider1->user)->post(route('courier.rider.cod.remit'), ['request_token' => (string) Str::uuid()]);

        return RiderRemittance::latest('id')->first();
    }

    public function test_the_hub_rejects_then_accepts(): void
    {
        $this->delivered(1100);
        $first = $this->remit();
        $this->actingAs($this->logistics1)->get(route('sc.remittances'))->assertOk()->assertSee('data-reject-remittance', false);

        // Rejected: it doesn't count; the COD stays on the rider, who remits again.
        $this->actingAs($this->logistics1)->from(route('sc.remittances'))->post(route('sc.remittances.reject', $first), ['reason' => 'Cash was not handed over'])
            ->assertSessionHas('success');
        $this->assertSame(['rejected', 'Cash was not handed over'], [$first->fresh()->status, $first->fresh()->reject_reason]);
        $this->assertEquals(1100, app(RiderWallet::class)->codBalance($this->rider1));
        $this->actingAs($this->logistics1)->from(route('sc.remittances'))->post(route('sc.remittances.confirm', $first), ['counted' => 1100])
            ->assertSessionHas('error');                       // already decided

        $second = $this->remit();
        $this->assertNotSame($first->id, $second->id);
        $this->actingAs($this->logistics1)->from(route('sc.remittances'))->post(route('sc.remittances.confirm', $second), ['counted' => 1100])
            ->assertSessionHas('success');
        $this->assertEquals(0, app(RiderWallet::class)->codBalance($this->rider1));
        $this->assertSame(30.0, app(RiderWallet::class)->balances($this->rider1)['available']);   // the pay is in
    }

    /** Remitting is a record of the cash for the hub: the rider's wallet doesn't move (only an accepted COD remittance adds their pay). */
    public function test_remitting_does_not_touch_the_wallet(): void
    {
        // Money already in the wallet: older pay the hub sent, and a prepaid delivery.
        \App\Models\RiderWalletEntry::create(['rider_id' => $this->rider1->id, 'sorting_center_id' => $this->logistics1->id, 'type' => 'pay', 'amount' => 250, 'jobs' => 5]);
        $p = $this->parcel('delivered');
        $p->order->update(['shipping_fee' => 100]);
        \App\Models\Parcel::where('order_id', $p->order_id)->whereKeyNot($p->id)->delete();
        ParcelDelivery::create(['parcel_id' => $p->id, 'rider_id' => $this->rider1->id, 'status' => 'delivered', 'attempt' => 1, 'delivered_at' => now(), 'cod_amount' => 0, 'cod_collected' => 0]);
        $this->delivered(1100);
        $wallet = app(RiderWallet::class);
        $before = $wallet->balances($this->rider1);
        $this->assertSame([280.0, 30.0, 1100.0], [$before['available'], $before['expected'], $before['cash_to_remit']]);

        $r = $this->remit();                                   // pending: the wallet is the same
        $after = $wallet->balances($this->rider1);
        $this->assertSame([280.0, 30.0], [$after['available'], $after['expected']]);
        $this->actingAs($this->rider1->user)->get(route('courier.rider.wallet'))->assertOk()->assertSee('₱280.00')
            ->assertSee('data-money="mine"', false)->assertSee('data-money="hub"', false);
        // The COD page shows the same two cards: the wallet untouched, the cash waiting for the hub.
        $this->actingAs($this->rider1->user)->get(route('courier.rider.cod'))->assertOk()->assertSee('data-money-cards', false)
            ->assertSeeInOrder(['Your money', '₱280.00', 'Not yours', '₱1,100.00', 'waiting for the hub to accept', '₱1,100.00']);

        $this->actingAs($this->logistics1)->from(route('sc.remittances'))->post(route('sc.remittances.reject', $r), ['reason' => 'Cash not handed over']);
        $this->assertSame(280.0, $wallet->balances($this->rider1)['available']);   // rejected: still the same

        $second = $this->remit();
        $this->actingAs($this->logistics1)->from(route('sc.remittances'))->post(route('sc.remittances.confirm', $second), ['counted' => 1100])->assertSessionHas('success');
        $this->assertSame([310.0, 0.0], [$wallet->balances($this->rider1)['available'], $wallet->balances($this->rider1)['expected']]);   // accepted: + the COD pay
    }

    public function test_each_sees_only_their_own_history(): void
    {
        $this->delivered(500);
        $r = $this->remit();
        $this->actingAs($this->logistics1)->from(route('sc.remittances'))->post(route('sc.remittances.reject', $r), ['reason' => 'Wrong amount handed']);
        $this->remit();

        // The rider: all their remittances, with filters.
        $this->actingAs($this->rider1->user)->get(route('courier.rider.cod.history'))->assertOk()
            ->assertSee($r->receiptNumber())->assertSee('Wrong amount handed');
        $this->actingAs($this->rider1->user)->get(route('courier.rider.cod.history', ['status' => 'pending']))->assertOk()->assertDontSee($r->receiptNumber());
        // Another rider sees none of them.
        $this->actingAs($this->rider2->user)->get(route('courier.rider.cod.history'))->assertOk()->assertDontSee($r->receiptNumber());

        // The hub: its riders' remittances, filtered; another company sees none.
        $this->actingAs($this->logistics1)->get(route('sc.remittances', ['tab' => 'remittances', 'status' => 'rejected']))->assertOk()
            ->assertSee('data-remit-filter', false)->assertSee('Wrong amount handed');
        $this->actingAs($this->logistics2)->get(route('sc.remittances', ['tab' => 'remittances']))->assertOk()->assertDontSee('Wrong amount handed');

        // The hub's own remittances to ALVY: only its own, filtered.
        $mine = HubRemittance::create(['sorting_center_id' => $this->logistics1->id, 'amount' => 100, 'status' => 'flagged', 'note' => 'Short ₱5']);
        HubRemittance::create(['sorting_center_id' => $this->logistics2->id, 'amount' => 777, 'status' => 'submitted']);
        $this->actingAs($this->logistics1)->get(route('sc.alvy-remittances', ['status' => 'flagged']))->assertOk()
            ->assertSee($mine->reference())->assertDontSee('₱777.00');
    }
}
