<?php

namespace Tests\Feature\Buyer;

use App\Models\Order;
use App\Models\Refund;
use App\Models\ReturnShipment;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/**
 * An approved return's money: the buyer says where to send it (GCash, Maya or a bank account, with their password),
 * and it is sent as soon as the rider hands the item back to the seller: urgent on Admin → Refunds, where an admin
 * sends it and types the reference. Nothing counts as refunded before that.
 */
class RefundFlowTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRiderTestData();
        $this->admin = $this->account('admin', 'admin@alvy.example.com', 'ALVY Admin');
    }

    /** A delivered COD order with an approved return; the item is still with the buyer. */
    private function approvedReturn(): array
    {
        $parcel = $this->parcel('delivered');
        $order = $parcel->order;
        $order->update(['status' => Order::DELIVERED, 'delivered_at' => now(), 'payment_status' => 'Paid']);
        $this->actingAs($this->buyer1)->post(route('orders.return', $order->id), [
            'reason' => 'Damaged item', 'details' => 'The basket arrived crushed.', 'evidence' => UploadedFile::fake()->image('d.jpg'),
        ])->assertSessionHas('success');
        $this->actingAs($this->seller1)->post(route('seller.orders.return', $order->id), ['decision' => 'approve'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'once the item is back'));

        return [$order->fresh(), ReturnShipment::sole(), Refund::sole()];
    }

    private function details(Order $order, array $over = [])
    {
        return $this->actingAs($this->buyer1)->from(route('profile.orders.show', $order->id))->post(route('orders.refund-details', $order->id), $over + [
            'method' => 'gcash', 'account_name' => 'Juan Miguel Dela Cruz', 'account_number' => '0917 111 2222', 'confirm' => '1',
        ]);
    }

    /** The rider hands the item back to the seller. */
    private function handBack(ReturnShipment $rs): void
    {
        $rs->forceFill(['status' => 'to_seller', 'picked_up_at' => now(), 'seller_rider_id' => $this->rider2->id, 'seller_assigned_at' => now()])->save();
        $this->actingAs($this->rider2->user)->post(route('courier.rider.return-pickups.handed', $rs->id))->assertSessionHas('success');
    }

    public function test_the_buyer_gives_details_and_the_refund_is_sent_once_the_item_is_back(): void
    {
        [$order, $rs, $refund] = $this->approvedReturn();
        $this->assertSame(['awaiting_details', 0.0], [$refund->status, (float) $order->refunded_amount]);
        $this->assertSame('Return approved · refund pending', $order->buyerStatusLabel());

        // The order page asks where to send it.
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertOk()
            ->assertSee('data-refund-details-form', false)->assertSee('Where should we send your ₱' . number_format((float) $refund->amount, 2))
            ->assertSee('data-return-step="details" data-state="current"', false)->assertSee('data-refund-pending', false);

        // The number's format, the bank and the confirmation are checked; no password is asked.
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertDontSee('name="password"', false);
        $this->details($order, ['account_number' => '12345'])->assertSessionHasErrors('account_number');
        $this->details($order, ['method' => 'bank', 'account_number' => '001234567890'])->assertSessionHasErrors('bank_name');
        $this->details($order, ['confirm' => null])->assertSessionHasErrors('confirm');
        $this->details($order)->assertSessionHas('success', fn ($m) => str_contains($m, 'as soon as the item is back'));

        $refund->refresh();
        $this->assertSame(['waiting_for_item', 'gcash', '09171112222'], [$refund->status, $refund->method, $refund->account_number]);
        $this->assertStringNotContainsString('09171112222', (string) DB::table('refunds')->value('account_number'));   // stored encrypted
        $this->details($order)->assertSessionHas('error');   // locked once given

        // Not sent before the item is back.
        $this->actingAs($this->admin)->get(route('admin.refunds.index', ['tab' => 'waiting']))->assertOk()->assertSee('data-refund="' . $refund->id . '"', false);
        $this->actingAs($this->admin)->post(route('admin.refunds.sent', $refund), ['reference' => '1012345678901'])->assertSessionHas('error');

        // The rider hands it back: urgent for the admins, the buyer is told.
        $this->handBack($rs);
        $this->assertSame('ready_to_send', $refund->fresh()->status);
        $this->assertTrue(UserNotification::where('user_id', $this->admin->id)->where('title', 'Urgent: refund to send')->exists());
        $this->assertTrue(UserNotification::where('user_id', $this->buyer1->id)->where('title', 'Your refund is being sent')->exists());
        $this->actingAs($this->admin)->get(route('admin.refunds.index'))->assertOk()
            ->assertSee('data-refund="' . $refund->id . '"', false)->assertSee('URGENT')->assertSee('09171112222');

        // The admin sends it and types the reference: now it counts as refunded.
        $this->actingAs($this->admin)->post(route('admin.refunds.sent', $refund), ['reference' => ''])->assertSessionHasErrors('reference');
        $this->actingAs($this->admin)->post(route('admin.refunds.sent', $refund), ['reference' => '98765456789987654'])->assertSessionHasErrors('reference');   // a GCash Ref No. is 13 numbers
        $this->actingAs($this->admin)->get(route('admin.refunds.index'))->assertSee('maxlength="13"', false);
        $this->actingAs($this->admin)->post(route('admin.refunds.sent', $refund), ['reference' => '1012345678901'])->assertSessionHas('success');
        $refund->refresh();
        $this->assertSame(['sent', $this->admin->id, '1012345678901'], [$refund->status, (int) $refund->sent_by, $refund->reference]);
        $this->assertEquals((float) $refund->amount, (float) $order->fresh()->refunded_amount);
        $this->assertSame('Refunded', $order->fresh()->buyerStatusLabel());
        $this->assertTrue(UserNotification::where('user_id', $this->buyer1->id)->where('title', 'Refund sent')->where('body', 'like', '%1012345678901%')->exists());
        $this->actingAs($this->admin)->post(route('admin.refunds.sent', $refund), ['reference' => '1012345678901'])->assertSessionHas('error');   // not twice

        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertOk()
            ->assertSee('data-return-step="refund" data-state="done"', false)->assertSee('data-refund-status="sent"', false)
            ->assertSee('Refund sent · ₱')->assertDontSee('data-refund-details-form', false)->assertDontSee('data-refund-pending', false);
    }

    public function test_an_item_back_before_the_details_is_ready_as_soon_as_they_are_given(): void
    {
        [$order, $rs, $refund] = $this->approvedReturn();
        $this->handBack($rs);
        $this->assertSame('awaiting_details', $refund->fresh()->status);
        $this->assertTrue(UserNotification::where('user_id', $this->buyer1->id)->where('title', 'Enter where to send your refund')->exists());
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertSee('The item is already back with the seller');

        $this->details($order, ['method' => 'bank', 'bank_name' => 'BDO Unibank', 'account_number' => '0012-3456-7890'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'sending your refund now'));
        $this->assertSame(['ready_to_send', '001234567890', 'BDO Unibank'], [$refund->fresh()->status, $refund->fresh()->account_number, $refund->fresh()->bank_name]);
    }

    public function test_details_that_dont_work_go_back_to_the_buyer(): void
    {
        [$order, $rs, $refund] = $this->approvedReturn();
        $this->details($order, ['account_name' => 'Someone Else']);
        $this->handBack($rs);
        $this->actingAs($this->admin)->get(route('admin.refunds.index'))->assertSee("not the buyer's name", false);

        $this->actingAs($this->admin)->post(route('admin.refunds.send-back', $refund), ['reason' => 'The GCash name does not match yours.'])->assertSessionHas('success');
        $refund->refresh();
        $this->assertSame(['awaiting_details', null, null], [$refund->status, $refund->method, $refund->account_number]);
        $this->assertTrue(UserNotification::where('user_id', $this->buyer1->id)->where('title', 'Fix your refund details')->exists());
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertSee('data-refund-sent-back', false)->assertSee('The GCash name does not match yours.');

        // Fixed: the item is already back, so it is ready at once.
        $this->details($order);
        $this->assertSame('ready_to_send', $refund->fresh()->status);
    }

    public function test_alvys_money_goes_down_when_the_refund_is_sent_but_its_commission_stays(): void
    {
        $dashboard = fn () => $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->viewData('grossSales');
        $commission = fn () => $this->actingAs($this->admin)->get(route('admin.dashboard'))->viewData('commissionEarned');

        $wallet = fn () => app(\App\Services\SellerWallet::class)->balances($this->seller1->id)['on_hold'];
        $parcel = $this->parcel('delivered');
        $order = $parcel->order;
        $order->update(['status' => Order::DELIVERED, 'delivered_at' => now(), 'payment_status' => 'Paid']);
        $order->items()->update(['commission_amount' => 25]);
        $this->actingAs($this->buyer1)->post(route('orders.return', $order->id), [
            'reason' => 'Damaged item', 'details' => 'The basket arrived crushed.', 'evidence' => UploadedFile::fake()->image('d.jpg')]);
        $before = [$dashboard(), $commission(), $wallet()];
        $this->assertGreaterThan(0, $before[2]);   // ALVY holds the seller's share during the return window

        $this->actingAs($this->seller1)->post(route('seller.orders.return', $order->id), ['decision' => 'approve']);
        [$rs, $refund] = [ReturnShipment::sole(), Refund::sole()];
        $this->assertEquals(0, $wallet());                                         // the seller's share is gone
        $this->assertEquals([$before[0], $before[1]], [$dashboard(), $commission()]);   // approved, not sent: ALVY still has the money

        $this->details($order);
        $this->handBack($rs);
        $this->actingAs($this->admin)->post(route('admin.refunds.sent', $refund), ['reference' => '1012345678901']);
        $this->assertEqualsWithDelta($before[0] - (float) $refund->amount, $dashboard(), 0.001);   // the refund left ALVY's money
        $this->assertEquals($before[1], $commission());                                          // the commission stays
        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('data-refunds-sent', false);
    }

    public function test_only_the_buyer_can_give_the_details(): void
    {
        [$order] = $this->approvedReturn();
        $this->actingAs($this->buyer2)->post(route('orders.refund-details', $order->id), [
            'method' => 'gcash', 'account_name' => 'X', 'account_number' => '09170000000', 'confirm' => '1',
        ])->assertNotFound();
        $this->actingAs($this->buyer1)->get(route('admin.refunds.index'))->assertForbidden();
    }
}
