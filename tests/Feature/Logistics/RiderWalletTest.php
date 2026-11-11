<?php

namespace Tests\Feature\Logistics;

use App\Models\ParcelDelivery;
use App\Models\ParcelPickup;
use App\Models\RiderWalletEntry;
use App\Services\RiderWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** The hub sends riders' pay (their share of the shipping fee) to their e-wallet; COD is remitted in full. */
class RiderWalletTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
    }

    public function test_the_hub_sends_rider_pay_to_the_wallet_and_cod_is_remitted_in_full(): void
    {
        $wallet = app(RiderWallet::class);
        ParcelPickup::create(['parcel_id' => $this->parcel('picked_up')->id, 'rider_id' => $this->rider1->id, 'status' => 'received', 'picked_up_at' => now(), 'received_at' => now()]);
        ParcelDelivery::create(['parcel_id' => $this->parcel('delivered')->id, 'rider_id' => $this->rider1->id, 'status' => 'delivered',
            'delivered_at' => now(), 'cod_amount' => 490, 'cod_collected' => 490]);

        $this->assertSame(80.0, $wallet->toSend($this->rider1));
        // Pay isn't kept out of the COD any more: all ₱490 is handed over.
        $this->assertSame(['cod' => 490.0, 'pay' => 0.0, 'remit' => 490.0, 'owed' => 0.0], $wallet->toRemit($this->rider1));

        $this->actingAs($this->logistics1)->get(route('sc.rider-pay'))->assertOk()->assertSee('Send pay')->assertSee('₱80.00');
        $this->actingAs($this->logistics1)->from(route('sc.rider-pay'))->post(route('sc.rider-pay.send', $this->rider1))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '₱80.00'));
        $this->assertSame(80.0, $wallet->walletBalance($this->rider1));
        $this->assertSame(0.0, $wallet->toSend($this->rider1));
        $this->assertSame(2, RiderWalletEntry::firstOrFail()->jobs);

        // Nothing left: a second press sends nothing; another hub can't pay this rider.
        $this->actingAs($this->logistics1)->from(route('sc.rider-pay'))->post(route('sc.rider-pay.send', $this->rider1))->assertSessionHas('error');
        $this->actingAs($this->logistics2)->post(route('sc.rider-pay.send', $this->rider1))->assertForbidden();
        $this->assertSame(1, RiderWalletEntry::count());

        $this->actingAs($this->rider1->user)->get(route('courier.rider.wallet'))->assertOk()
            ->assertSee('data-wallet-balance', false)->assertSee('₱80.00')->assertSee('Pay from the hub');
    }

    /** Wallet with ₱250 sent in by the hub. */
    private function wallet250(): void
    {
        RiderWalletEntry::create(['rider_id' => $this->rider1->id, 'sorting_center_id' => $this->logistics1->id, 'type' => 'pay', 'amount' => 250, 'jobs' => 5]);
    }

    private function cashout(array $data = [])
    {
        return $this->actingAs($this->rider1->user)->from(route('courier.rider.wallet'))
            ->post(route('courier.rider.wallet.cashout'), $data + ['amount' => 200, 'gcash_number' => '09171234567', 'gcash_name' => 'Pedro Jose Garcia']);
    }

    public function test_a_rider_cashes_out_to_gcash_and_the_hub_sends_it(): void
    {
        $wallet = app(RiderWallet::class);
        $this->wallet250();

        // Checks first: too little, more than the wallet, a bad GCash number.
        $this->cashout(['amount' => 50])->assertSessionHasErrors('amount');
        $this->cashout(['amount' => 300])->assertSessionHas('error', fn ($m) => str_contains($m, '₱250.00'));
        $this->cashout(['gcash_number' => '12345'])->assertSessionHasErrors('gcash_number');

        $this->cashout()->assertSessionHas('success', fn ($m) => str_contains($m, '₱200.00'));
        $this->assertSame(50.0, $wallet->walletBalance($this->rider1));   // taken out at once
        $this->cashout(['amount' => 100])->assertSessionHas('error');      // one request at a time

        $c = RiderWalletEntry::where('type', 'cashout')->sole();
        $this->actingAs($this->logistics1)->get(route('sc.rider-pay'))->assertOk()->assertSee('09171234567')->assertSee('Mark sent');
        // Another hub can't touch it; a reference is needed.
        $this->actingAs($this->logistics2)->from(route('sc.rider-pay'))->post(route('sc.rider-pay.cashout.paid', $c), ['reference' => '1234567890'])->assertSessionHas('error');
        $this->actingAs($this->logistics1)->from(route('sc.rider-pay'))->post(route('sc.rider-pay.cashout.paid', $c), ['reference' => ''])->assertSessionHasErrors('reference');

        $this->actingAs($this->logistics1)->from(route('sc.rider-pay'))->post(route('sc.rider-pay.cashout.paid', $c), ['reference' => '1234567890'])
            ->assertSessionHas('success');
        $this->assertSame(['paid', '1234567890'], [$c->fresh()->status, $c->fresh()->reference]);
        $this->assertSame(50.0, $wallet->walletBalance($this->rider1));
        $this->actingAs($this->logistics1)->from(route('sc.rider-pay'))->post(route('sc.rider-pay.cashout.reject', $c), ['reason' => 'Changed my mind'])
            ->assertSessionHas('error');   // already paid
        $this->actingAs($this->rider1->user)->get(route('courier.rider.wallet'))->assertOk()->assertSee('Sent to GCash')->assertSee('1234567890');
    }

    public function test_a_rejected_cash_out_goes_back_into_the_wallet(): void
    {
        $wallet = app(RiderWallet::class);
        $this->wallet250();
        $this->cashout()->assertSessionHas('success');
        $c = RiderWalletEntry::where('type', 'cashout')->sole();

        $this->actingAs($this->logistics1)->from(route('sc.rider-pay'))->post(route('sc.rider-pay.cashout.reject', $c), ['reason' => 'GCash number not found'])
            ->assertSessionHas('success');
        $this->assertSame('rejected', $c->fresh()->status);
        $this->assertSame(250.0, $wallet->walletBalance($this->rider1));
        $this->cashout(['amount' => 250])->assertSessionHas('success');   // can ask again
    }
}
