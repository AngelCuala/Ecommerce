<?php

namespace Tests\Feature\Buyer;

use App\Models\Order;
use App\Models\User;
use App\Support\AlvyGcash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/**
 * GCash paid to ALVY: at checkout the buyer scans ALVY's GCash QR, sends the whole checkout's total and uploads
 * one receipt with its Ref No. The orders wait in To Pay (the sellers don't see them) until an admin approves
 * the payment on Admin → GCash payments; a rejected payment cancels them. No payment gateway is involved.
 */
class GcashReceiptPaymentTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
        Storage::fake('local');
        Storage::fake('public');
        config(['payments.gateway' => null]);
        Storage::disk('public')->put('gcash-qr/alvy.png', 'png');
        AlvyGcash::save(['qr_path' => 'gcash-qr/alvy.png', 'name' => 'ALVY TRADING', 'number' => '09171234567']);
        session()->forget('gcash_cents');
        $this->admin = User::forceCreate(['name' => 'Admin', 'email' => 'admin@alvy.test', 'password' => Hash::make('x'), 'role' => 'admin', 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    private function receipt(): UploadedFile
    {
        return UploadedFile::fake()->image('gcash-receipt.png', 720, 1280);
    }

    private function payWithGcash(array $extra = [], ?array $lines = null)
    {
        $lines ??= [$this->cart($this->buyer1, $this->p02)];
        $this->openCheckout($this->buyer1, $lines);
        return $this->actingAs($this->buyer1)->post(route('checkout.store'), $this->checkoutForm($this->addr1, $extra + [
            'payment_method'    => 'gcash_receipt',
            'payment_proof'     => $this->receipt(),
            'payment_reference' => '1012 345 678901',
        ]));
    }

    private function twoStores(): array
    {
        return [$this->cart($this->buyer1, $this->p02), $this->cart($this->buyer1, $this->p05)];
    }

    // ── Checkout ──

    public function test_checkout_shows_alvys_qr_and_the_whole_total(): void
    {
        $page = $this->openCheckout($this->buyer1, $this->twoStores())->assertOk()
            ->assertSee('value="gcash_receipt"', false)
            ->assertSee(asset('storage/gcash-qr/alvy.png'), false)
            ->assertSee('Pay ALVY with GCash')->assertSee('ALVY TRADING')->assertSee('09171234567')
            ->assertSee('name="payment_proof"', false)->assertSee('name="payment_reference"', false)
            ->assertSee('One payment for all 2 stores', false)
            ->assertSee('data-gcash-pay', false)
            ->assertSee('enctype="multipart/form-data"', false);
        $page->assertDontSee('payment_proofs[', false);
    }

    public function test_no_gcash_until_alvys_qr_is_set(): void
    {
        AlvyGcash::save(['qr_path' => null]);
        $line = $this->cart($this->buyer1, $this->p02);

        $this->openCheckout($this->buyer1, [$line])->assertOk()->assertDontSee('value="gcash_receipt"', false);
        $this->actingAs($this->buyer1)->post(route('checkout.store'), $this->checkoutForm($this->addr1, ['payment_method' => 'gcash_receipt']))
            ->assertSessionHas('error', 'GCash is not available right now. Please choose Cash on Delivery.');
        $this->assertSame(0, Order::count());
    }

    public function test_the_amount_to_send_is_the_orders_total_with_its_own_centavos(): void
    {
        $lines = $this->twoStores();
        $this->openCheckout($this->buyer1, $lines);
        $preview = $this->actingAs($this->buyer1)->postJson(route('checkout.preview'), ['address_id' => $this->addr1->id])->assertOk();

        $this->payWithGcash([], $lines)->assertSessionHasNoErrors();
        $orders = Order::all();
        $this->assertEqualsWithDelta($preview->json('gcash_orders'), (float) $orders->sum('total_price'), 0.001);
        $expected = (float) $orders->first()->payment_expected;
        $this->assertEqualsWithDelta($preview->json('gcash_total'), $expected, 0.001);   // what the buyer was shown is what is stored
        $this->assertSame([$expected], $orders->pluck('payment_expected')->map(fn ($v) => (float) $v)->unique()->values()->all());
        $cover = (float) $orders->sum('total_price') - $expected;
        $this->assertTrue($cover > 0 && $cover < 1, "ALVY covers under ₱1 (was {$cover})");
        $this->assertNotSame(0, (int) round(($expected - floor($expected)) * 100));   // it has centavos
    }

    public function test_waiting_payments_never_share_the_same_centavos(): void
    {
        $taken = [];
        foreach (range(1, 98) as $n) {
            $taken[] = $n;   // pretend 98 payments are waiting with centavos 1..98
        }
        $order = $this->placeOrder($this->buyer2, [$this->cart($this->buyer2, $this->p02)])->first();
        foreach ($taken as $c) {
            $copy = $order->replicate(['tracking_number']);
            $copy->forceFill(['status' => Order::AWAITING_PAYMENT, 'payment_method' => Order::GCASH_RECEIPT, 'payment_status' => 'For verification',
                'payment_expected' => 100 + $c / 100, 'checkout_group' => (string) \Illuminate\Support\Str::uuid(), 'tracking_number' => 'T' . $c])->saveQuietly();
        }
        session()->forget('gcash_cents');
        $this->assertSame(99, AlvyGcash::cents());   // the only one left
        $this->assertSame(1249.99, AlvyGcash::amount(1250.00, 99));
        $this->assertSame(1250.37, AlvyGcash::amount(1250.40, 37));
        $this->assertSame(1249.50, AlvyGcash::amount(1250.40, 50));
    }

    public function test_one_receipt_covers_every_store_and_the_orders_wait_in_to_pay(): void
    {
        $this->payWithGcash([], $this->twoStores())->assertSessionHasNoErrors()->assertRedirect(route('checkout.confirmation'));

        $orders = Order::orderBy('id')->get();
        $this->assertCount(2, $orders);
        foreach ($orders as $order) {
            $this->assertSame([Order::GCASH_RECEIPT, 'For verification', Order::AWAITING_PAYMENT, '1012345678901', null],
                [$order->payment_method, $order->payment_status, $order->status, $order->payment_reference, $order->pay_by]);
            $this->assertFalse($order->canBePaid());           // no "Pay now"
            $this->assertFalse($order->isCancellableByBuyer()); // ALVY decides first
        }
        $this->assertSame($orders[0]->payment_proof_path, $orders[1]->payment_proof_path);
        $this->assertStringStartsWith('payment-proofs/', $orders[0]->payment_proof_path);
        Storage::disk('local')->assertExists($orders[0]->payment_proof_path);

        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $orders[0]->id))->assertOk()
            ->assertSee('data-gcash-checking', false)->assertSee('ALVY is checking your GCash payment')
            ->assertSee('View your GCash receipt')->assertDontSee('Pay now');
        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->buyer1->id, 'title' => 'Checking your GCash payment']);
    }

    public function test_the_sellers_dont_see_the_order_until_it_is_approved(): void
    {
        $this->payWithGcash();
        $order = Order::latest('id')->first();

        $this->actingAs($this->seller1)->get(route('seller.orders.index'))->assertOk()->assertDontSee($order->number());
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $this->seller1->id, 'title' => 'New order ' . $order->number()]);
    }

    public function test_the_confirmation_page_says_alvy_checks_the_gcash_payment(): void
    {
        $this->payWithGcash()->assertRedirect(route('checkout.confirmation'));

        $this->actingAs($this->buyer1)->get(route('checkout.confirmation'))->assertOk()
            ->assertSee('with GCash')->assertSee('ALVY checks your receipt first')->assertSee('Order total (GCash)')
            ->assertSee('Ref No. 1012345678901')->assertDontSee('cash on delivery')->assertDontSee('(COD)');
    }

    public function test_the_receipt_and_ref_no_are_required(): void
    {
        $this->payWithGcash(['payment_proof' => null])->assertSessionHasErrors(['payment_proof' => 'Upload the screenshot of your GCash receipt.']);
        $this->payWithGcash(['payment_proof' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf')])->assertSessionHasErrors('payment_proof');
        $this->payWithGcash(['payment_reference' => ''])->assertSessionHasErrors('payment_reference');
        $this->payWithGcash(['payment_reference' => '1012abc678901'])->assertSessionHasErrors(['payment_reference' => 'The GCash Ref No. can only have numbers (no letters).']);
        $this->payWithGcash(['payment_reference' => '101234567890'])->assertSessionHasErrors(['payment_reference' => 'The GCash Ref No. has 13 numbers. Copy it from your receipt.']);
        $this->assertSame(0, Order::count());
    }

    public function test_a_ref_no_can_be_used_only_once(): void
    {
        $this->payWithGcash()->assertSessionHasNoErrors();
        $this->payWithGcash(['payment_reference' => '1012345678901'])
            ->assertSessionHasErrors(['payment_reference' => 'This GCash Ref No. was already used for another order. Check your receipt.']);
        $this->assertSame(1, Order::count());
    }

    public function test_the_ref_no_box_takes_numbers_only(): void
    {
        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->assertOk()
            ->assertSee('maxlength="13" pattern="\d{13}"', false)->assertSee("replace(/\\D/g, '')", false)->assertSee('data-gcash-exact', false);
    }

    public function test_only_the_buyer_and_admins_open_the_receipt(): void
    {
        $this->payWithGcash();
        $order = Order::latest('id')->first();

        $this->actingAs($this->buyer1)->get(route('orders.payment-proof', $order))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.orders.payment-proof', $order))->assertOk();
        $this->actingAs($this->buyer2)->get(route('orders.payment-proof', $order))->assertForbidden();
    }

    // ── Admin: approve / reject ──

    public function test_the_admin_sees_the_receipt_the_ref_no_and_the_total(): void
    {
        $this->payWithGcash([], $this->twoStores());
        $orders = Order::orderBy('id')->get();

        $this->actingAs($this->admin)->get(route('admin.gcash.index'))->assertOk()
            ->assertSee('data-gcash-group="' . $orders[0]->checkout_group . '"', false)
            ->assertSee('1012345678901')
            ->assertSee('₱' . number_format($orders[0]->payment_expected, 2))
            ->assertSee('data-gcash-hints', false)->assertSee('Reading the receipt…')
            ->assertSee($orders[0]->number())->assertSee($orders[1]->number())
            ->assertSee(route('admin.orders.payment-proof', $orders[0]->id), false);
        $this->actingAs($this->buyer1)->get(route('admin.gcash.index'))->assertForbidden();
    }

    public function test_approving_sends_every_order_to_its_seller_and_only_then_takes_the_stock(): void
    {
        [$s02, $s05] = [$this->p02->fresh()->stock, $this->p05->fresh()->stock];
        $this->payWithGcash([], $this->twoStores());
        $group = Order::first()->checkout_group;
        $this->assertSame([$s02, $s05], [$this->p02->fresh()->stock, $this->p05->fresh()->stock]);   // not yet

        $this->actingAs($this->admin)->post(route('admin.gcash.approve', $group))->assertSessionHas('success');
        $this->assertSame([$s02 - 1, $s05 - 1], [$this->p02->fresh()->stock, $this->p05->fresh()->stock]);

        foreach (Order::all() as $order) {
            $this->assertSame([Order::PENDING, 'Paid'], [$order->status, $order->payment_status]);
            $this->assertDatabaseHas('user_notifications', ['user_id' => $order->seller_id, 'title' => 'New order ' . $order->number()]);
        }
        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->buyer1->id, 'title' => 'Payment received']);

        // The seller now sees it, with "Paid with GCash", and accepts it normally.
        $order = Order::where('seller_id', $this->seller1->id)->first();
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertOk()
            ->assertSee('data-gcash-proof', false)->assertSee('Paid with GCash')->assertSee('Accept order')->assertDontSee('Payment received');
        $this->actingAs($this->seller1)->post(route('seller.orders.accept', $order->id))->assertSessionHas('success');
        $this->assertSame(Order::PROCESSING, $order->fresh()->status);

        // Twice: nothing more happens.
        $this->actingAs($this->admin)->post(route('admin.gcash.approve', $group))->assertSessionHas('error', 'That GCash payment was already checked.');
    }

    public function test_rejecting_cancels_the_orders_and_tells_the_buyer_why(): void
    {
        $stock = $this->p02->fresh()->stock;
        $this->payWithGcash();
        $order = Order::latest('id')->first();
        $this->assertSame($stock, $this->p02->fresh()->stock);   // GCash takes stock only on approval

        $this->actingAs($this->admin)->post(route('admin.gcash.reject', $order->checkout_group), ['reason' => 'not_received'])
            ->assertSessionHas('success');

        $order->refresh();
        $this->assertSame([Order::CANCELLED, 'Not received'], [$order->status, $order->payment_status]);
        $this->assertStringContainsString('No GCash payment with this Ref No. reached ALVY', $order->cancellation_reason);
        $this->assertSame($stock, $this->p02->fresh()->stock);   // nothing was taken, nothing added
        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->buyer1->id, 'title' => 'GCash payment rejected',
            'body' => "ALVY couldn't confirm your GCash payment for order {$order->number()}, so the order was cancelled. Reason: No GCash payment with this Ref No. reached ALVY."]);
        $this->assertDatabaseMissing('user_notifications', ['user_id' => $this->seller1->id, 'title' => 'Order ' . $order->number() . ' cancelled']);
    }

    public function test_an_item_that_sold_out_while_waiting_is_cancelled_and_refunded_on_approval(): void
    {
        $this->payWithGcash();
        $order = Order::latest('id')->first();
        $this->p02->update(['stock' => 0]);   // someone else bought the last one meanwhile

        $this->actingAs($this->admin)->post(route('admin.gcash.approve', $order->checkout_group))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Refunds to send'));
        $order->refresh();
        $this->assertSame([Order::CANCELLED, 'Refund pending'], [$order->status, $order->payment_status]);
        $this->assertSame(0, $this->p02->fresh()->stock);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->buyer1->id, 'title' => 'Order cancelled']);
    }

    public function test_a_wrong_amount_that_arrived_is_listed_for_refund(): void
    {
        $this->payWithGcash();
        $order = Order::latest('id')->first();

        $this->actingAs($this->admin)->post(route('admin.gcash.reject', $order->checkout_group),
            ['reason' => 'wrong_amount', 'note' => 'received ₱100 only', 'received' => 1])->assertSessionHas('success');
        $this->assertSame([Order::CANCELLED, 'Refund pending'], [$order->fresh()->status, $order->fresh()->payment_status]);

        $this->actingAs($this->admin)->get(route('admin.gcash.index'))->assertOk()->assertSee('data-gcash-refunds', false)->assertSee($order->number());
        $this->actingAs($this->admin)->post(route('admin.gcash.refunded', $order->id), ['refund_reference' => '5012345678901'])->assertSessionHas('success');
        $this->assertSame('Refunded', $order->fresh()->payment_status);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->buyer1->id, 'title' => 'GCash refund sent']);
    }

    public function test_a_paid_gcash_order_cannot_be_cancelled_by_the_buyer_or_seller_and_is_not_refunded(): void
    {
        $this->payWithGcash();
        $order = Order::latest('id')->first();
        $this->actingAs($this->admin)->post(route('admin.gcash.approve', $order->checkout_group));

        $this->actingAs($this->buyer1)->post(route('orders.cancel', $order->id), ['cancellation_reason' => 'Changed my mind'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'GCash payments are not refunded'));
        $this->actingAs($this->seller1)->post(route('seller.orders.cancel', $order->id), ['reason' => 'Out of stock'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'only ALVY can cancel it'));
        $this->assertSame([Order::PENDING, 'Paid'], [$order->fresh()->status, $order->fresh()->payment_status]);
    }

    // ── ALVY's GCash account ──

    public function test_the_admin_sets_alvys_qr_name_and_number(): void
    {
        AlvyGcash::save(['qr_path' => null, 'name' => null, 'number' => null]);

        $this->actingAs($this->admin)->post(route('admin.gcash.account'), ['name' => 'ALVY'])->assertSessionHasErrors('qr');
        $this->actingAs($this->admin)->post(route('admin.gcash.account'), [
            'qr' => UploadedFile::fake()->image('qr.png', 600, 600), 'name' => 'ALVY Trading', 'number' => '09998887777',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $account = AlvyGcash::account();
        $this->assertSame(['ALVY Trading', '09998887777'], [$account['name'], $account['number']]);
        Storage::disk('public')->assertExists(AlvyGcash::settings()['qr_path']);

        $this->actingAs($this->admin)->post(route('admin.gcash.account'), ['name' => 'ALVY', 'number' => '123'])->assertSessionHasErrors('number');
        $this->actingAs($this->buyer1)->post(route('admin.gcash.account'), ['name' => 'X'])->assertForbidden();
    }

    // ── Receipt reader, admin notice, 24-hour reminder ──

    public function test_the_admins_are_told_about_a_new_payment(): void
    {
        $this->payWithGcash();
        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->admin->id, 'title' => 'GCash payment to check']);
    }

    private function readerSays(array $found): void
    {
        config(['services.assistant.gemini_key' => 'test-key']);
        \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory());   // a new answer replaces the last one
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['candidates' => [['content' => ['parts' => [
            ['text' => json_encode($found)],
        ]]]]])]);
    }

    public function test_a_wrong_amount_is_rejected_and_the_buyer_is_told_why_and_how_to_fix_it(): void
    {
        $this->payWithGcash();
        $order = Order::latest('id')->first();
        $this->readerSays(['is_gcash_receipt' => true, 'amount' => 100.00, 'reference' => '9999 999 999999', 'date' => now()->toDateString()]);
        $this->artisan('gcash:check')->assertSuccessful();

        $order->refresh();
        $this->assertSame([Order::AWAITING_PAYMENT, 'Rejected'], [$order->status, $order->payment_status]);
        $codes = array_column($order->payment_check['rejection']['problems'], 'code');
        $this->assertSame(['amount', 'reference'], $codes);
        $expected = '₱' . number_format($order->payment_expected, 2);

        $note = \App\Models\UserNotification::where('user_id', $this->buyer1->id)->where('title', 'GCash payment rejected')->sole();
        $this->assertStringContainsString('Your receipt shows ₱100.00, but this payment needs exactly ' . $expected, $note->body);
        $this->assertStringContainsString('How to fix it: Send exactly ' . $expected . ' to ALVY', $note->body);
        $this->assertStringContainsString('Type the 13-number Ref No. exactly as it appears on your receipt.', $note->body);

        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertOk()
            ->assertSee('data-gcash-rejected', false)->assertSee('Your GCash payment was rejected')->assertSee('How to fix it')
            ->assertSee(route('orders.gcash-resubmit', $order->id), false)->assertSee('Try 1 of 3');
        $this->actingAs($this->admin)->get(route('admin.gcash.index'))->assertOk()
            ->assertSee('data-gcash-fixing', false)->assertSee('rejected by the receipt reader');
        $this->assertSame(0, \App\Models\UserNotification::where('user_id', $this->seller1->id)->count());   // the seller still knows nothing
    }

    public function test_an_old_receipt_and_a_picture_that_is_not_a_receipt_are_rejected(): void
    {
        $this->payWithGcash();
        $order = Order::latest('id')->first();
        $this->readerSays(['is_gcash_receipt' => true, 'amount' => (float) $order->payment_expected, 'reference' => '1012345678901', 'date' => '2025-05-20']);
        $this->artisan('gcash:check');
        $this->assertSame(['old'], array_column($order->fresh()->payment_check['rejection']['problems'], 'code'));
        $this->assertStringContainsString('dated May 20, 2025, before you placed this order', $order->fresh()->payment_check['rejection']['problems'][0]['reason']);

        $this->resubmit($order, '2023456789012');
        $this->readerSays(['is_gcash_receipt' => false]);
        $this->artisan('gcash:check');
        $this->assertSame(['not_receipt'], array_column($order->fresh()->payment_check['rejection']['problems'], 'code'));
    }

    public function test_money_sent_to_another_number_is_rejected_when_alvys_number_is_set(): void
    {
        $this->payWithGcash();
        $order = Order::latest('id')->first();
        $this->readerSays(['is_gcash_receipt' => true, 'amount' => (float) $order->payment_expected, 'reference' => '1012345678901', 'recipient_number' => '+63 979 507 6928']);
        $this->artisan('gcash:check');
        $this->assertSame(['recipient'], array_column($order->fresh()->payment_check['rejection']['problems'], 'code'));
    }

    private function resubmit(Order $order, string $ref)
    {
        return $this->actingAs($this->buyer1)->post(route('orders.gcash-resubmit', $order->id), [
            'payment_proof' => $this->receipt(), 'payment_reference' => $ref,
        ]);
    }

    public function test_the_buyer_uploads_the_right_receipt_and_it_is_checked_again(): void
    {
        $this->payWithGcash();
        $order = Order::latest('id')->first();
        $old = $order->payment_proof_path;
        $this->readerSays(['is_gcash_receipt' => false]);
        $this->artisan('gcash:check');

        $this->resubmit($order, 'abc')->assertSessionHasErrors('payment_reference');
        $this->resubmit($order, '2023456789012')->assertSessionHas('success');

        $order->refresh();
        $this->assertSame(['For verification', '2023456789012', 2], [$order->payment_status, $order->payment_reference, $order->payment_check['tries']]);
        $this->assertNotSame($old, $order->payment_proof_path);
        Storage::disk('local')->assertExists([$old, $order->payment_proof_path]);   // the earlier receipt is kept for the admin
        $this->assertSame('1012345678901', $order->payment_check['attempts'][0]['reference']);
        $this->assertSame(2, \App\Models\UserNotification::where('user_id', $this->admin->id)->where('title', 'GCash payment to check')->count());
        $this->actingAs($this->admin)->get(route('admin.gcash.index'))->assertOk()->assertSee('data-gcash-attempt', false)->assertSee('Earlier receipt 1: Ref 1012345678901');

        // A good receipt now: nothing is rejected and the admin approves as usual.
        $this->readerSays(['is_gcash_receipt' => true, 'amount' => (float) $order->payment_expected, 'reference' => '2023456789012', 'date' => now()->toDateString()]);
        $this->artisan('gcash:check');
        $this->assertSame('For verification', $order->fresh()->payment_status);
        $this->actingAs($this->admin)->post(route('admin.gcash.approve', $order->checkout_group))->assertSessionHas('success');
        $this->assertSame(Order::PENDING, $order->fresh()->status);
    }

    public function test_the_third_rejected_receipt_cancels_the_order(): void
    {
        $this->payWithGcash();
        $order = Order::latest('id')->first();
        foreach (['2023456789012', '3034567890123'] as $ref) {
            $this->readerSays(['is_gcash_receipt' => false]);
            $this->artisan('gcash:check');
            $this->assertSame('Rejected', $order->fresh()->payment_status);
            $this->resubmit($order, $ref)->assertSessionHas('success');
        }
        $this->readerSays(['is_gcash_receipt' => false]);
        $this->artisan('gcash:check');

        $order->refresh();
        $this->assertSame([Order::CANCELLED, 'Not received'], [$order->status, $order->payment_status]);
        $this->assertStringContainsString('3 receipts tried', $order->cancellation_reason);
    }

    public function test_a_rejection_not_fixed_in_24_hours_cancels_the_order(): void
    {
        $this->payWithGcash();
        $order = Order::latest('id')->first();
        $this->readerSays(['is_gcash_receipt' => false]);
        $this->artisan('gcash:check');

        $this->travel(23)->hours();
        $this->artisan('gcash:check');
        $this->assertSame(Order::AWAITING_PAYMENT, $order->fresh()->status);
        $this->travel(2)->hours();
        $this->artisan('gcash:check');
        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
        $this->assertStringContainsString('not fixed within 24 hours', $order->fresh()->cancellation_reason);
    }

    public function test_the_buyer_may_cancel_a_rejected_payment_instead_of_fixing_it(): void
    {
        $this->payWithGcash();
        $order = Order::latest('id')->first();
        $this->assertFalse($order->isCancellableByBuyer());
        $this->readerSays(['is_gcash_receipt' => false]);
        $this->artisan('gcash:check');

        $this->actingAs($this->buyer1)->post(route('orders.cancel', $order->id), ['cancellation_reason' => 'I will pay with COD instead'])->assertSessionHasNoErrors();
        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
    }

    public function test_the_admin_rejects_and_lets_the_buyer_fix_it(): void
    {
        $this->payWithGcash();
        $order = Order::latest('id')->first();

        $this->actingAs($this->admin)->post(route('admin.gcash.reject', $order->checkout_group), ['reason' => 'not_received', 'let_fix' => 1, 'note' => 'nothing in GCash yet'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'how to fix it'));
        $order->refresh();
        $this->assertSame([Order::AWAITING_PAYMENT, 'Rejected', 'admin'], [$order->status, $order->payment_status, $order->payment_check['rejection']['by']]);
        $this->assertStringContainsString('(nothing in GCash yet)', $order->payment_check['rejection']['problems'][0]['reason']);
        $this->assertDatabaseHas('user_notifications', ['user_id' => $this->buyer1->id, 'title' => 'GCash payment rejected']);
    }

    public function test_the_reader_confirms_a_matching_receipt(): void
    {
        $this->payWithGcash();
        $order = Order::latest('id')->first();
        $this->readerSays(['is_gcash_receipt' => true, 'amount' => (float) $order->payment_expected, 'reference' => '1012345678901', 'date' => now()->toDateString()]);
        $this->artisan('gcash:check')->assertSuccessful();
        $this->assertSame('For verification', $order->fresh()->payment_status);

        $this->actingAs($this->admin)->get(route('admin.gcash.index'))->assertOk()
            ->assertSee('Amount on the receipt matches')->assertSee('Ref No. on the receipt matches')->assertDontSee('⚠');
    }

    public function test_after_24_hours_the_admin_and_buyer_get_one_reminder(): void
    {
        $this->payWithGcash();
        $this->travel(25)->hours();
        $this->artisan('gcash:check')->assertSuccessful();
        $this->artisan('gcash:check')->assertSuccessful();

        $this->assertSame(1, \App\Models\UserNotification::where('user_id', $this->admin->id)->where('title', 'like', 'GCash payment waiting over%')->count());
        $this->assertSame(1, \App\Models\UserNotification::where('user_id', $this->buyer1->id)->where('title', 'Still checking your GCash payment')->count());
    }

    public function test_store_settings_no_longer_ask_for_a_gcash_qr(): void
    {
        $this->actingAs($this->seller1)->get(route('seller.store-settings'))->assertOk()->assertDontSee('data-gcash-settings', false);
    }
}
