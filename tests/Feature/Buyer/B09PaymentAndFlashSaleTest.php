<?php

namespace Tests\Feature\Buyer;

use App\Models\FlashSale;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StoreFollow;
use App\Models\UserNotification;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuyerTestData;
use Tests\TestCase;

/** Buyer workbook B9-002…007 (online payment), B11-003 (cancel a prepaid order) and B14-004 (flash sale reminder). */
class B09PaymentAndFlashSaleTest extends TestCase
{
    use RefreshDatabase, BuyerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBuyerTestData();
        config(['payments.gateway' => 'sandbox']);
    }

    /** Check out P-02 ×2 with GCash; returns the redirect response (to the payment page). */
    private function payOnline(array $extra = [])
    {
        $line = $this->cart($this->buyer1, $this->p02, 2);
        $this->openCheckout($this->buyer1, [$line]);

        return $this->actingAs($this->buyer1)->post(route('checkout.store'), $this->checkoutForm($this->addr1, $extra + ['payment_method' => 'gcash']));
    }

    private function sandbox(Payment $payment, string $result)
    {
        return $this->actingAs($this->buyer1)->post(route('payments.sandbox.act', $payment), ['result' => $result]);
    }

    /** Online payment is off unless a gateway is set up: COD only. */
    public function test_online_payment_is_off_by_default(): void
    {
        config(['payments.gateway' => null]);
        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->assertDontSee('GCash');
        $this->actingAs($this->buyer1)->post(route('checkout.store'), $this->checkoutForm($this->addr1, ['payment_method' => 'gcash']))
            ->assertSessionHasErrors('payment_method');
        $this->assertSame(0, Order::count());
    }

    /** BUY-PAY-002 */
    public function test_ewallet_payment_succeeds(): void
    {
        $payment = null;
        $this->payOnline()->assertRedirect();
        $payment = Payment::sole();
        $order = Order::sole();
        $this->assertSame(route('payments.sandbox', $payment), $payment->checkout_url);
        $this->assertSame('Awaiting Payment', $order->status);
        $this->assertSame('to_pay', $order->tab());
        $this->assertSame(18, $this->p02->fresh()->stock);   // A3: taken when the order is placed
        $this->assertFalse(UserNotification::where('user_id', $this->seller1->id)->exists());   // seller waits for the payment

        $this->sandbox($payment, 'pay')->assertRedirect(route('payments.return', $payment));
        $this->actingAs($this->buyer1)->get(route('payments.return', $payment))->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('Pending', $order->status);
        $this->assertSame('to_ship', $order->tab());
        $this->assertSame('Paid', $order->payment_status);
        $this->assertNotNull($payment->fresh()->provider_ref);
        $this->assertNotNull($payment->fresh()->paid_at);
        $this->assertTrue(UserNotification::where('user_id', $this->seller1->id)->where('title', 'like', 'New order%')->exists());
    }

    /** BUY-PAY-003 */
    public function test_failed_payment_stays_in_to_pay_with_pay_again(): void
    {
        $this->payOnline();
        $payment = Payment::sole();
        $this->sandbox($payment, 'decline');
        $this->actingAs($this->buyer1)->get(route('payments.return', $payment))->assertSessionHas('error', fn ($m) => str_contains($m, 'Payment failed'));

        $order = Order::sole();
        $this->assertSame('Awaiting Payment', $order->status);
        $this->assertSame('Failed', $order->payment_status);
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertSee('Payment failed')->assertSee('Pay again');

        // Pay again opens a new payment for the same order (no new order).
        $this->actingAs($this->buyer1)->post(route('orders.pay', $order->id))->assertRedirect();
        $this->assertSame(2, Payment::count());
        $this->assertSame(1, Order::count());
    }

    /** BUY-PAY-004 */
    public function test_cancelling_at_the_gateway_returns_to_to_pay(): void
    {
        $this->payOnline();
        $payment = Payment::sole();
        $this->sandbox($payment, 'cancel')->assertRedirect(route('payments.return', [$payment, 'cancelled' => 1]));
        $this->actingAs($this->buyer1)->get(route('payments.return', [$payment, 'cancelled' => 1]))->assertSessionHas('error', fn ($m) => str_contains($m, 'nothing was charged'));

        $this->assertSame('cancelled', $payment->fresh()->status);
        $this->assertSame('Awaiting Payment', Order::sole()->status);
        $this->assertSame(1, Order::count());
    }

    /** BUY-PAY-005 */
    public function test_closing_the_browser_never_charges_twice(): void
    {
        $this->payOnline();
        $payment = Payment::sole();
        $order = Order::sole();

        // Browser closed on the gateway page: "Pay now" re-uses the open payment instead of starting another.
        $this->actingAs($this->buyer1)->post(route('orders.pay', $order->id))->assertRedirect($payment->checkout_url);
        $this->assertSame(1, Payment::count());

        // The gateway confirms it later.
        app(\App\Services\PaymentService::class)->markPaid($payment, 'pay_123');
        app(\App\Services\PaymentService::class)->markPaid($payment, 'pay_123');   // a repeated confirmation changes nothing
        $this->assertSame('Pending', $order->fresh()->status);
        $this->actingAs($this->buyer1)->post(route('orders.pay', $order->id))->assertSessionHas('success', 'This order is already paid.');
        $this->assertSame(1, Payment::where('status', 'paid')->count());
    }

    /** BUY-PAY-006 */
    public function test_unpaid_order_cancels_after_an_hour(): void
    {
        $this->payOnline(['seller_vouchers' => [$this->seller1->id => 'MCH50']]);
        $this->assertSame(1, Voucher::where('code', 'MCH50')->value('used_count'));

        $this->travel(61)->minutes();
        $this->artisan('orders:cancel-unpaid')->assertSuccessful();

        $order = Order::sole();
        $this->assertSame('Cancelled', $order->status);
        $this->assertStringContainsString('unpaid', $order->cancellation_reason);
        $this->assertSame(20, $this->p02->fresh()->stock);
        $this->assertSame(0, Voucher::where('code', 'MCH50')->value('used_count'));
        $this->assertSame('expired', Payment::sole()->status);
    }

    /** BUY-PAY-007 */
    public function test_paying_just_before_and_just_after_the_limit(): void
    {
        $this->payOnline();
        $early = Payment::sole();
        $this->travel(59)->minutes();
        $this->sandbox($early, 'pay');
        $this->assertSame('Pending', $early->orders()->first()->status);

        $this->travelBack();
        $this->payOnline();
        $late = Payment::latest('id')->first();
        $this->travel(61)->minutes();
        $this->artisan('orders:cancel-unpaid');
        $this->sandbox($late, 'pay');
        $lateOrder = $late->orders()->first();
        $this->assertSame('Cancelled', $lateOrder->status);
        $this->assertNotSame('Paid', $lateOrder->payment_status);
        $this->actingAs($this->buyer1)->post(route('orders.pay', $lateOrder->id))->assertSessionHas('error');
    }

    /** BUY-CAN-003 */
    public function test_cancelling_a_prepaid_order_refunds_it(): void
    {
        $this->payOnline(['seller_vouchers' => [$this->seller1->id => 'MCH50']]);
        $this->sandbox(Payment::sole(), 'pay');
        $order = Order::sole();
        $this->assertSame('Paid', $order->payment_status);

        $this->actingAs($this->buyer1)->post(route('orders.cancel', $order->id), ['cancellation_reason' => 'Changed my mind'])->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('Cancelled', $order->status);
        $this->assertSame('Refunded', $order->payment_status);
        $this->assertEquals($order->total_price, $order->refunded_amount);
        $this->assertSame(0, Voucher::where('code', 'MCH50')->value('used_count'));
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertSee('Refunded');
    }

    /** BUY-CHAT-004 */
    public function test_flash_sale_reminder_arrives_before_the_start(): void
    {
        StoreFollow::create(['user_id' => $this->buyer1->id, 'seller_id' => $this->seller1->id]);
        $sale = FlashSale::create(['product_id' => $this->p02->id, 'seller_id' => $this->seller1->id, 'flash_price' => 299,
            'quantity' => 5, 'starts_at' => now()->addMinutes(40), 'ends_at' => now()->addHours(2)]);

        $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertSee('Flash sale')->assertSee('Remind me');
        $this->actingAs($this->buyer1)->post(route('flash-sales.remind', $sale))->assertSessionHas('success');

        $this->artisan('flash-sales:remind');   // 40 minutes before: too early
        $this->assertSame(0, UserNotification::where('title', 'Flash sale starts soon')->count());

        $this->travel(30)->minutes();          // 10 minutes before
        $this->artisan('flash-sales:remind');
        $this->artisan('flash-sales:remind');  // only once
        $note = UserNotification::where('user_id', $this->buyer1->id)->where('title', 'Flash sale starts soon')->sole();
        $this->assertStringContainsString('₱299.00', $note->body);
    }

    /** Assumption A5: the flash price wins while flash units are left, then the normal price is back. */
    public function test_flash_price_while_units_last(): void
    {
        $this->p02->update(['sale_price' => 400]);
        $sale = FlashSale::create(['product_id' => $this->p02->id, 'seller_id' => $this->seller1->id, 'flash_price' => 299,
            'quantity' => 2, 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHour()]);

        $this->assertEquals(299, $this->p02->fresh()->effective_price);
        $this->actingAs($this->buyer2)->get(route('products.show', $this->p02->slug))->assertSee('FLASH SALE')->assertSee('₱299.00');

        $line = $this->cart($this->buyer1, $this->p02, 2);
        $this->openCheckout($this->buyer1, [$line]);
        $this->actingAs($this->buyer1)->post(route('checkout.store'), $this->checkoutForm($this->addr1))->assertSessionHasNoErrors();
        $this->assertEquals(299, Order::sole()->items()->first()->price);
        $this->assertSame(2, $sale->fresh()->sold);

        // Sold out at the flash price: the store sale price applies again.
        $this->assertEquals(400, $this->p02->fresh()->effective_price);

        // Cancelling gives the flash units back.
        $this->actingAs($this->buyer1)->post(route('orders.cancel', Order::sole()->id), ['cancellation_reason' => 'Changed my mind']);
        $this->assertSame(0, $sale->fresh()->sold);
    }

    /** Sellers schedule flash sales for their own products only. */
    public function test_seller_schedules_a_flash_sale(): void
    {
        $form = ['product_id' => $this->p02->id, 'flash_price' => 299, 'quantity' => 5,
            'starts_at' => now()->addDay()->format('Y-m-d H:i'), 'ends_at' => now()->addDay()->addHours(2)->format('Y-m-d H:i')];

        $this->actingAs($this->seller1)->post(route('seller.flash-sales.store'), ['flash_price' => 500] + $form)->assertSessionHasErrors('flash_price');
        $this->actingAs($this->seller2)->post(route('seller.flash-sales.store'), $form)->assertSessionHasErrors('product_id');
        $this->actingAs($this->seller1)->post(route('seller.flash-sales.store'), ['product_id' => $this->p01->id] + $form)->assertSessionHasErrors('product_id');
        $this->actingAs($this->seller1)->post(route('seller.flash-sales.store'), $form)->assertSessionHas('success');
        $this->actingAs($this->seller1)->post(route('seller.flash-sales.store'), $form)->assertSessionHasErrors('starts_at');   // overlaps
        $this->actingAs($this->seller1)->get(route('seller.flash-sales.index'))->assertOk()->assertSee('Rattan Fruit Basket')->assertSee('Upcoming');
    }
}
