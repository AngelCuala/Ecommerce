<?php

namespace Tests\Feature\Logistics;

use App\Models\ParcelDelivery;
use App\Models\ParcelPickup;
use App\Models\PayoutAccount;
use App\Models\RiderRemittance;
use App\Models\RiderWalletEntry;
use App\Services\RiderWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** PAYMENTS_SPEC §6–7: the rider wallet is a ledger; COD pay waits for the hub's count; a short count holds it. */
class RiderLedgerTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['marketplace.rider_share_since' => now()->subDay()->toDateTimeString()]);
        $this->seedRiderTestData();
    }

    private function wallet(): RiderWallet
    {
        return app(RiderWallet::class);
    }

    /** A delivered job of rider1 on a parcel whose order's shipping fee is ₱100 (pay ₱30); COD when $cod > 0. */
    private function delivered(float $cod = 0): ParcelDelivery
    {
        $p = $this->parcel('delivered');
        $p->order->update(['shipping_fee' => 100]);
        \App\Models\Parcel::where('order_id', $p->order_id)->whereKeyNot($p->id)->delete();

        return ParcelDelivery::create(['parcel_id' => $p->id, 'rider_id' => $this->rider1->id, 'status' => 'delivered', 'attempt' => 1,
            'delivered_at' => now(), 'cod_amount' => $cod, 'cod_collected' => $cod]);
    }

    private function remit(): RiderRemittance
    {
        $this->actingAs($this->rider1->user)->post(route('courier.rider.cod.remit'), ['request_token' => (string) Str::uuid()]);

        return RiderRemittance::latest('id')->first();
    }

    private function confirm(RiderRemittance $r, ?float $counted = null)
    {
        return $this->actingAs($this->logistics1)->from(route('sc.remittances'))->post(route('sc.remittances.confirm', $r), $counted === null ? [] : ['counted' => $counted]);
    }

    public function test_pickups_and_prepaid_deliveries_go_in_by_themselves(): void
    {
        $p = $this->parcel('picked_up');
        $p->order->update(['shipping_fee' => 100]);
        \App\Models\Parcel::where('order_id', $p->order_id)->whereKeyNot($p->id)->delete();
        ParcelPickup::create(['parcel_id' => $p->id, 'rider_id' => $this->rider1->id, 'sorting_center_id' => $this->logistics1->id,
            'status' => 'received', 'picked_up_at' => now(), 'received_at' => now()]);
        $this->delivered();

        $b = $this->wallet()->balances($this->rider1);
        $this->assertSame([60.0, 0.0, 0.0], [$b['available'], $b['held'], $b['expected']]);
        $this->assertSame(['delivery_credit', 'pickup_credit'], RiderWalletEntry::where('rider_id', $this->rider1->id)->orderBy('type')->pluck('type')->all());

        // Each job once.
        $this->wallet()->syncCredits($this->rider1);
        $this->assertSame(2, RiderWalletEntry::where('rider_id', $this->rider1->id)->count());
    }

    public function test_cod_pay_waits_for_the_hubs_count(): void
    {
        $this->delivered(1100);
        $b = $this->wallet()->balances($this->rider1);
        $this->assertSame([0.0, 30.0, 1100.0], [$b['available'], $b['expected'], $b['cash_to_remit']]);
        $this->actingAs($this->rider1->user)->get(route('courier.rider.wallet'))->assertOk()->assertSee('data-expected', false);

        $r = $this->remit();
        $this->confirm($r, 1100)->assertSessionHas('success', fn ($m) => str_contains($m, '₱1,100.00 counted'));
        $r->refresh();
        $this->assertSame([1100.0, $this->logistics1->id], [(float) $r->counted_amount, (int) $r->confirmed_by]);   // who confirmed
        $b = $this->wallet()->balances($this->rider1);
        $this->assertSame([30.0, 0.0, 0.0], [$b['available'], $b['expected'], $b['cash_to_remit']]);
    }

    public function test_a_short_count_holds_the_pay_until_the_rider_pays_the_gap(): void
    {
        $this->delivered(1100);
        $r = $this->remit();
        $this->confirm($r, 1000)->assertSessionHas('success', fn ($m) => str_contains($m, 'Short by ₱100.00'));

        $b = $this->wallet()->balances($this->rider1);
        $this->assertSame([0.0, 30.0, 100.0], [$b['available'], $b['held'], $b['cash_to_remit']]);   // the gap is still owed

        // The rider hands over the ₱100 gap: counted in full, the pay is released.
        $gap = $this->remit();
        $this->assertSame(100.0, (float) $gap->amount);
        $this->confirm($gap, 100);
        $b = $this->wallet()->balances($this->rider1);
        $this->assertSame([30.0, 0.0, 0.0], [$b['available'], $b['held'], $b['cash_to_remit']]);
    }

    public function test_the_hub_can_take_a_shortage_from_earnings(): void
    {
        $this->delivered(1100);
        $this->delivered();                       // ₱30 prepaid already available
        $r = $this->remit();
        $this->confirm($r, 1080);

        $this->actingAs($this->logistics1)->get(route('sc.remittances'))->assertOk()->assertSee('data-deduct', false);
        $this->actingAs($this->logistics1)->from(route('sc.remittances'))->post(route('sc.remittances.deduct', $r))->assertSessionHas('success');

        $b = $this->wallet()->balances($this->rider1);
        $this->assertSame([40.0, 0.0, 0.0], [$b['available'], $b['held'], $b['cash_to_remit']]);   // 30 + 30 − 20
        $this->assertSame(-20.0, (float) RiderWalletEntry::where('type', 'shortage_adjustment')->sole()->amount);
        $this->actingAs($this->logistics1)->from(route('sc.remittances'))->post(route('sc.remittances.deduct', $r))->assertSessionHas('error');   // once
    }

    public function test_withdraw_any_amount_to_a_saved_bank_account(): void
    {
        $this->delivered();
        $this->delivered();                       // ₱60 available
        $cashout = fn (array $data) => $this->actingAs($this->rider1->user)->from(route('courier.rider.wallet'))->post(route('courier.rider.wallet.cashout'), $data);

        $cashout(['amount' => 61, 'method' => 'bank', 'bank_name' => 'BDO', 'account_number' => '001234567890', 'account_name' => 'Pedro Garcia'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Not enough available balance'));
        $cashout(['amount' => 5, 'method' => 'bank', 'bank_name' => 'BDO', 'account_number' => '001234567890', 'account_name' => 'Pedro Garcia'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'BDO 001234567890'));   // no minimum, no fee
        $this->assertSame(55.0, $this->wallet()->walletBalance($this->rider1));                   // locked at once
        $account = PayoutAccount::sole();

        // Failed: back in the wallet; then again with the saved account.
        $c = RiderWalletEntry::where('type', 'cashout')->sole();
        $this->actingAs($this->logistics1)->from(route('sc.rider-pay'))->post(route('sc.rider-pay.cashout.reject', $c), ['reason' => 'Account number not found'])->assertSessionHas('success');
        $this->assertSame(60.0, $this->wallet()->walletBalance($this->rider1));
        $cashout(['amount' => 60, 'payout_account_id' => $account->id])->assertSessionHas('success');
        $this->assertSame(0.0, $this->wallet()->walletBalance($this->rider1));
        $this->actingAs($this->logistics1)->get(route('sc.rider-pay'))->assertOk()->assertSee('BDO')->assertSee('001234567890');
    }
}
