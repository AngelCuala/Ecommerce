<?php

namespace Tests\Feature\Buyer;

use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/**
 * GCash paid outside ALVY: the buyer sends the total to the shop's GCash and uploads the receipt at checkout;
 * the seller checks the receipt and accepting the order marks it paid. No payment gateway is involved.
 */
class GcashReceiptPaymentTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
        Storage::fake('local');
        config(['payments.gcash_receipt' => ['number' => '0917 123 4567', 'name' => 'ALVY Marketplace'], 'payments.gateway' => null]);
    }

    private function payWithGcash(array $extra = [])
    {
        $line = $this->cart($this->buyer1, $this->p02);
        $this->openCheckout($this->buyer1, [$line]);
        return $this->actingAs($this->buyer1)->post(route('checkout.store'), $this->checkoutForm($this->addr1, $extra + [
            'payment_method'    => 'gcash_receipt',
            'payment_proof'     => UploadedFile::fake()->image('gcash-receipt.png', 720, 1280),
            'payment_reference' => '1012 345 678901',
        ]));
    }

    public function test_checkout_shows_gcash_only_when_a_number_is_set(): void
    {
        $line = $this->cart($this->buyer1, $this->p02);
        $this->openCheckout($this->buyer1, [$line])->assertOk()
            ->assertSee('value="gcash_receipt"', false)->assertSee('0917 123 4567')->assertSee('ALVY Marketplace')
            ->assertSee('enctype="multipart/form-data"', false);

        config(['payments.gcash_receipt.number' => null]);
        $this->openCheckout($this->buyer1, [$line])->assertDontSee('value="gcash_receipt"', false);
        $this->actingAs($this->buyer1)->post(route('checkout.store'), $this->checkoutForm($this->addr1, ['payment_method' => 'gcash_receipt']))
            ->assertSessionHasErrors('payment_method');
    }

    public function test_the_order_waits_for_the_seller_with_the_receipt_stored_privately(): void
    {
        $this->payWithGcash()->assertSessionHasNoErrors()->assertRedirect(route('checkout.confirmation'));

        $order = Order::latest('id')->first();
        $this->assertSame([Order::GCASH_RECEIPT, 'For verification', Order::PENDING, '1012345678901'],
            [$order->payment_method, $order->payment_status, $order->status, $order->payment_reference]);
        $this->assertFalse($order->isOnlinePayment());   // no gateway, no automatic refund
        $this->assertSame('GCash', $order->paymentMethodLabel());
        Storage::disk('local')->assertExists($order->payment_proof_path);
        $this->assertStringStartsWith('payment-proofs/', $order->payment_proof_path);

        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertOk()
            ->assertSee('data-gcash-receipt', false)->assertSee('View your GCash receipt')->assertSee('Ref No. 1012345678901');
    }

    public function test_a_receipt_is_required_and_must_be_an_image(): void
    {
        $before = Order::count();
        $this->payWithGcash(['payment_proof' => null])->assertSessionHasErrors(['payment_proof' => 'Upload the screenshot of your GCash receipt.']);
        $this->payWithGcash(['payment_proof' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf')])->assertSessionHasErrors('payment_proof');
        $this->payWithGcash(['payment_reference' => 'abc'])->assertSessionHasErrors('payment_reference');
        $this->assertSame($before, Order::count());
    }

    public function test_only_the_buyer_the_seller_and_admins_can_open_the_receipt(): void
    {
        $this->payWithGcash();
        $order = Order::latest('id')->first();

        $this->actingAs($this->buyer1)->get(route('orders.payment-proof', $order))->assertOk();
        $this->actingAs($this->seller1)->get(route('seller.orders.payment-proof', $order))->assertOk();
        $this->actingAs($this->buyer2)->get(route('orders.payment-proof', $order))->assertForbidden();
    }

    public function test_the_seller_sees_the_receipt_and_accepting_marks_it_paid(): void
    {
        $this->payWithGcash();
        $order = Order::latest('id')->first();

        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertOk()
            ->assertSee('data-gcash-proof', false)->assertSee('Check the receipt')->assertSee('1012345678901')
            ->assertSee('Payment received · Accept order');

        $this->actingAs($this->seller1)->post(route('seller.orders.accept', $order->id))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'GCash payment marked as received'));
        $this->assertSame(['Paid', Order::PROCESSING], [$order->fresh()->payment_status, $order->fresh()->status]);
    }

    public function test_the_seller_can_cancel_when_the_money_never_arrived(): void
    {
        $this->payWithGcash();
        $order = Order::latest('id')->first();

        $this->actingAs($this->seller1)->post(route('seller.orders.cancel', $order->id), ['reason' => 'GCash payment not received'])
            ->assertSessionHas('success');
        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
    }
}
