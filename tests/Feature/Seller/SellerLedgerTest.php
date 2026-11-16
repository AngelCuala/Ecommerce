<?php

namespace Tests\Feature\Seller;

use App\Models\Order;
use App\Models\PayoutAccount;
use App\Models\SellerPayout;
use App\Models\SellerWalletEntry;
use App\Models\UserNotification;
use App\Services\SellerWallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** PAYMENTS_SPEC §5, §7: the seller wallet is a ledger of delivered orders; withdrawals are sent by ALVY. */
class SellerLedgerTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    private \App\Models\User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
        $this->admin = \App\Models\User::forceCreate(['name' => 'Admin', 'email' => 'admin@alvy.test', 'password' => bcrypt('x'), 'role' => 'admin',
            'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    /** One COD order of 2 × P-02 (₱900): the seller earns ₱810 after ALVY's 10%. */
    private function delivered(): Order
    {
        $order = $this->moveTo($this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 2)])->first(), Order::DELIVERED);
        if (! $order->delivered_at) {
            $order->forceFill(['delivered_at' => now()])->save();
        }

        return $order->fresh();
    }

    private function balances(): array
    {
        return app(SellerWallet::class)->balances($this->seller1->id);
    }

    public function test_a_delivered_order_is_on_hold_then_available(): void
    {
        $order = $this->delivered();
        $earning = round((float) $order->items->sum('seller_earning'), 2);
        $b = $this->balances();
        $this->assertSame([$earning, 0.0], [$b['on_hold'], $b['available']]);
        $line = SellerWalletEntry::sole();
        $this->assertSame(['on_hold', $order->returnDeadline()->toDateString()], [$line->status, $line->release_at->toDateString()]);
        $this->actingAs($this->seller1)->get(route('seller.earnings'))->assertOk()->assertSee('data-ledger', false)->assertSee('Expected');

        // The buyer confirms the item is OK (or the window ends): available at once; the line is not duplicated.
        $order->update(['status' => Order::COMPLETED]);
        $b = $this->balances();
        $this->assertSame([0.0, $earning], [$b['on_hold'], $b['available']]);
        $this->assertSame(['available', 1], [SellerWalletEntry::sole()->status, SellerWalletEntry::count()]);
    }

    public function test_a_refunded_order_is_reversed(): void
    {
        $order = $this->delivered();
        $this->balances();
        $order->update(['status' => Order::REFUNDED]);
        $b = $this->balances();
        $this->assertSame([0.0, 0.0], [$b['on_hold'], $b['available']]);
        $this->assertSame('reversed', SellerWalletEntry::sole()->status);
    }

    public function test_withdraw_to_a_bank_and_alvy_sends_it(): void
    {
        $order = $this->delivered();
        $order->update(['status' => Order::COMPLETED]);
        $available = $this->balances()['available'];
        $withdraw = fn (array $data) => $this->actingAs($this->seller1)->from(route('seller.earnings'))
            ->post(route('seller.earnings.payout'), $data + ['request_token' => (string) Str::uuid()]);

        $withdraw(['amount' => $available + 1, 'method' => 'bank', 'bank_name' => 'BPI', 'account_number' => '9876543210', 'account_name' => 'Juan Dela Cruz'])
            ->assertSessionHasErrors(['amount' => 'Not enough available balance: you have ₱' . number_format($available, 2) . '.']);
        $withdraw(['amount' => 10, 'method' => 'bank', 'bank_name' => 'BPI', 'account_number' => '9876543210', 'account_name' => 'Juan Dela Cruz'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'BPI 9876543210'));
        $this->assertSame(round($available - 10, 2), $this->balances()['available']);   // locked at once
        $p = SellerPayout::sole();

        // Admin: the transfer failed → back in the balance; again with the saved account → completed with the reference.
        $this->actingAs($this->admin)->get(route('admin.seller-payouts.index'))->assertOk()->assertSee('9876543210');
        $this->actingAs($this->admin)->from(route('admin.seller-payouts.index'))->post(route('admin.seller-payouts.failed', $p), ['reason' => 'Account number not found'])
            ->assertSessionHas('success');
        $this->assertSame($available, $this->balances()['available']);
        $withdraw(['amount' => $available, 'payout_account_id' => PayoutAccount::sole()->id])->assertSessionHas('success');
        $p2 = SellerPayout::latest('id')->first();
        $this->actingAs($this->admin)->from(route('admin.seller-payouts.index'))->post(route('admin.seller-payouts.paid', $p2), ['reference' => 'INSTA123456'])
            ->assertSessionHas('success');
        $this->assertSame(['paid', 'INSTA123456', 0.0], [$p2->fresh()->status, $p2->fresh()->reference, $this->balances()['available']]);
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'Withdrawal completed')->exists());
        $this->actingAs($this->admin)->from(route('admin.seller-payouts.index'))->post(route('admin.seller-payouts.paid', $p2), ['reference' => 'INSTA999999'])
            ->assertSessionHas('error');   // once
    }
}
