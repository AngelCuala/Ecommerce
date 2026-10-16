<?php

namespace Tests\Feature\Buyer;

use App\Mail\ContactChangeMail;
use App\Models\CartItem;
use App\Models\Message;
use App\Models\Order;
use App\Models\Review;
use App\Models\UserAddress;
use App\Models\UserNotification;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuyerTestData;
use Tests\TestCase;

/**
 * Buyer workbook (B1–B14) cases not covered by the B01…B10 files: ACCT-002/005/007/008/009,
 * ADDR-009, SRCH-011, PDP-002/004, CART-005/008/010, VOU-003/004/009, CHK-010/011, TRK-006,
 * CAN-006, RET-004, RATE-004 and CHAT-002/005.
 */
class B15BuyerWorkbookCasesTest extends TestCase
{
    use RefreshDatabase, BuyerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->seedBuyerTestData();
    }

    private function preview(array $data = [], $buyer = null)
    {
        return $this->actingAs($buyer ?? $this->buyer1)->postJson(route('checkout.preview'), $data + ['address_id' => $this->addr1->id])->assertOk();
    }

    private function place(array $data = [], $buyer = null, $address = null)
    {
        return $this->actingAs($buyer ?? $this->buyer1)->from(route('checkout.index'))
            ->post(route('checkout.store'), $this->checkoutForm($address ?? $this->addr1, $data));
    }

    /** A COD order of 2 × P-02 for Buyer 1, moved to $status. */
    private function order(string $status = Order::PENDING, $buyer = null): Order
    {
        $buyer ??= $this->buyer1;
        $this->openCheckout($buyer, [$this->cart($buyer, $this->p02, 2)]);
        $this->place([], $buyer, $buyer->is($this->buyer1) ? $this->addr1 : $this->addr3)->assertSessionHasNoErrors();
        $order = Order::where('user_id', $buyer->id)->latest('id')->firstOrFail();
        foreach ([Order::PROCESSING, Order::SHIPPED, Order::DELIVERED, Order::COMPLETED] as $step) {
            if ($order->status === $status) {
                break;
            }
            $order->update(['status' => $step] + ($step === Order::DELIVERED ? ['delivered_at' => now()] : []));
        }
        $this->flushSession();
        return $order->fresh();
    }

    // ── B1 Account & Security ─────────────────────────────────

    /** BUY-ACCT-002 (A16) */
    public function test_buy_acct_002_profile_field_validation(): void
    {
        $save = fn (array $data) => $this->actingAs($this->buyer1)->from(route('profile.show'))->patch(route('profile.update'), $data + ['name' => 'Juan Miguel Dela Cruz']);

        $save(['name' => ''])->assertSessionHasErrors(['name' => 'Please enter your name.']);
        $save(['name' => 'Juan123'])->assertSessionHasErrors(['name' => 'Your name may only contain letters, spaces and . - \' (no numbers or symbols).']);
        $save(['name' => 'J'])->assertSessionHasErrors(['name' => 'Your name must be at least 2 characters.']);
        $save(['avatar' => UploadedFile::fake()->image('size_5.1MB.jpg')->size(5222)])->assertSessionHasErrors(['avatar' => 'The profile photo can be at most 5 MB.']);
        $save(['avatar' => UploadedFile::fake()->create('renamed_exe.jpg', 2100, 'application/x-msdownload')])->assertSessionHasErrors('avatar');

        $this->assertSame('Juan Miguel Dela Cruz', $this->buyer1->fresh()->name);
        $this->assertNull($this->buyer1->fresh()->profile_photo_path);
        $save(['name' => 'Juan Miguel Jr.'])->assertSessionHasNoErrors();
        $this->assertSame('Juan Miguel Jr.', $this->buyer1->fresh()->name);
    }

    /** BUY-ACCT-005 */
    public function test_buy_acct_005_new_password_breaks_the_rule(): void
    {
        $change = fn ($new, $confirm = null) => $this->actingAs($this->buyer1)->from(route('profile.security'))
            ->patch(route('profile.change-password'), ['current_password' => 'Mamimili@2026', 'password' => $new, 'password_confirmation' => $confirm ?? $new]);

        $cases = [
            'Mami@26'       => '8 characters',
            'mamimili@2027' => 'uppercase',
            'MAMIMILI@2027' => 'lowercase',
            'Mamimili@abcd' => 'number',
            'Mamimili2027'  => 'symbol',
        ];
        foreach ($cases as $password => $rule) {
            $change($password)->assertSessionHasErrors('password');
            $this->assertStringContainsString($rule, session('errors')->first('password'), $password);
        }
        $change('Mamimili@2027', 'Mamimili@2028')->assertSessionHasErrors(['password' => 'The new password and its confirmation do not match.']);
        $change('Mamimili@2026')->assertSessionHasErrors(['password' => 'The new password must be different from your current one.']);
        $this->assertTrue(Hash::check('Mamimili@2026', $this->buyer1->fresh()->password));
    }

    /** BUY-ACCT-007 */
    public function test_buy_acct_007_change_email_wrong_or_expired_otp(): void
    {
        Mail::fake();
        $this->actingAs($this->buyer1)->post(route('profile.email.change'), ['email' => 'buyer.test01@example.com'])->assertSessionHas('success');
        $code = null;
        Mail::assertSent(ContactChangeMail::class, function ($m) use (&$code) { $code = $m->code; return true; });

        $this->actingAs($this->buyer1)->post(route('profile.contact.verify'), ['code' => $code === '000000' ? '111111' : '000000'])
            ->assertSessionHasErrors(['code' => 'That code is not correct.']);

        $this->travel(5)->minutes();
        $this->travel(1)->seconds();
        $this->actingAs($this->buyer1)->post(route('profile.contact.verify'), ['code' => $code])
            ->assertSessionHasErrors(['code' => 'This code has expired. Send a new one.']);
        $this->assertSame('juanmiguel.delacruz@example.com', $this->buyer1->fresh()->email);
    }

    /** BUY-ACCT-008 */
    public function test_buy_acct_008_change_email_to_one_already_used(): void
    {
        Mail::fake();
        foreach (['anamarie.reyes@example.com', 'MARIACLARA.SANTOS@example.com'] as $email) {
            $this->actingAs($this->buyer1)->post(route('profile.email.change'), ['email' => $email])
                ->assertSessionHasErrors(['email' => 'That email address is already in use by another account.']);
        }
        Mail::assertNothingSent();
        $this->assertNull($this->buyer1->fresh()->pending_email);
    }

    /** BUY-ACCT-009 */
    public function test_buy_acct_009_change_phone_with_verification_and_format(): void
    {
        Mail::fake();
        $this->buyer2->update(['phone' => '09173456789']);

        $this->actingAs($this->buyer1)->post(route('profile.phone.change'), ['phone' => '0917123'])->assertSessionHasErrors('phone');
        $this->actingAs($this->buyer1)->post(route('profile.phone.change'), ['phone' => '09173456789'])
            ->assertSessionHasErrors(['phone' => 'That mobile number is already used by another account.']);
        Mail::assertNothingSent();

        $this->actingAs($this->buyer1)->post(route('profile.phone.change'), ['phone' => '09170000001'])->assertSessionHas('success');
        $this->assertSame('09171234567', $this->buyer1->fresh()->phone);   // saved only after the code
        $code = null;
        Mail::assertSent(ContactChangeMail::class, function ($m) use (&$code) { $code = $m->code; return true; });
        $this->actingAs($this->buyer1)->post(route('profile.contact.verify'), ['code' => $code])->assertSessionHas('success');
        $this->assertSame('09170000001', $this->buyer1->fresh()->phone);
    }

    // ── B2 Address Book ───────────────────────────────────────

    /** BUY-ADDR-009 (A22) */
    public function test_buy_addr_009_cannot_open_another_buyers_address(): void
    {
        $this->actingAs($this->buyer1)->patch(route('addresses.update', $this->addr3), [
            'label' => 'Hijacked', 'full_name' => 'X', 'phone' => '09171234567', 'region' => 'NCR', 'province' => 'Metro Manila',
            'city' => 'Quezon City', 'barangay' => 'Central', 'address_line' => 'Somewhere', 'municipality_code' => self::QUEZON_CITY,
        ])->assertForbidden();
        $this->actingAs($this->buyer1)->delete(route('addresses.destroy', $this->addr3))->assertForbidden();
        $this->actingAs($this->buyer1)->patch(route('addresses.set-default', $this->addr3))->assertForbidden();

        $this->assertSame('78 Osmeña Blvd.', $this->addr3->fresh()->address_line);
        $this->actingAs($this->buyer1)->get(route('profile.addresses'))->assertDontSee('78 Osmeña Blvd.');
    }

    // ── B3 Browse & B4 Product Detail ─────────────────────────

    /** BUY-SRCH-011 (A18) */
    public function test_buy_srch_011_products_of_a_vacation_store(): void
    {
        $this->seller1->sellerApplication->update(['is_on_vacation' => true]);

        $this->actingAs($this->buyer1)->get(route('shop.index', ['search' => 'Rattan']))->assertOk()
            ->assertSee('Rattan Fruit Basket')->assertSee('On vacation');
        $this->actingAs($this->buyer1)->post(route('cart.store', $this->p02->id), ['quantity' => 1])
            ->assertSessionHas('error', 'This store is on vacation and is not taking orders right now.');
        $this->assertSame(0, CartItem::count());
    }

    /** BUY-PDP-002 */
    public function test_buy_pdp_002_selecting_a_variant_updates_price_stock_and_image(): void
    {
        $this->variation($this->p01, 'Natural / S')->update(['image_path' => 'variants/natural.jpg']);
        $this->variation($this->p01, 'Black / S')->update(['image_path' => 'variants/black.jpg']);

        // The page script switches price, stock and image from this list.
        $html = $this->actingAs($this->buyer1)->get(route('products.show', $this->p01->slug))->assertOk()->getContent();
        foreach ([['Natural \/ S', 199, 10], ['Natural \/ L', 299, 5], ['Black \/ S', 199, 10]] as [$name, $price, $stock]) {
            $this->assertMatchesRegularExpression('/"name":"' . preg_quote($name, '/') . '"[^}]*"price":' . $price . '[^}]*"stock":' . $stock
                . '|"name":"' . preg_quote($name, '/') . '"[^}]*"stock":' . $stock . '[^}]*"price":' . $price . '/', $html, $name);
        }
        $this->assertStringContainsString('variants\/natural.jpg', $html);
        $this->assertStringContainsString('variants\/black.jpg', $html);
    }

    /** BUY-PDP-004 (A17) */
    public function test_buy_pdp_004_quantity_limits(): void
    {
        $add = fn ($qty) => $this->actingAs($this->buyer1)->from('/p')->post(route('cart.store', $this->p02->id), ['quantity' => $qty]);
        foreach ([0, -1, 21, '1.5', 'abc'] as $bad) {
            $add($bad)->assertRedirect('/p');
            $this->assertSame(0, CartItem::count(), "quantity {$bad} must be rejected");
            $this->flushSession();
        }
        $add(1)->assertSessionHas('success');
        CartItem::query()->delete();
        $add(20)->assertSessionHas('success');
        $this->assertSame(20, CartItem::sole()->quantity);
        // The + button stops at the stock.
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertSee('max="20"', false);
    }

    // ── B6 Cart ───────────────────────────────────────────────

    /** BUY-CART-005 (A17) */
    public function test_buy_cart_005_change_quantity_at_the_limits(): void
    {
        $line = $this->cart($this->buyer1, $this->p02);
        $set = fn ($q) => $this->actingAs($this->buyer1)->from(route('cart.index'))->patch(route('cart.update', $line->id), ['quantity' => $q]);

        $set(0)->assertSessionHasErrors(['quantity' => 'The quantity must be at least 1.']);
        $set(1)->assertSessionHas('success');
        $set(20)->assertSessionHas('success');
        $set(21)->assertSessionHas('error', 'Only 20 left in stock.');
        $this->assertSame(20, $line->fresh()->quantity);
    }

    /** BUY-CART-008 */
    public function test_buy_cart_008_cart_persists_after_logout_and_on_another_device(): void
    {
        $this->actingAs($this->buyer1)->post(route('cart.store', $this->p02->id), ['quantity' => 2]);
        $this->actingAs($this->buyer1)->post(route('cart.store', $this->p05->id), ['quantity' => 1]);
        $this->post(route('logout'));
        $this->flushSession();

        // Signing in again (or on another browser) shows the same cart: it is saved on the account.
        $this->actingAs($this->buyer1->fresh())->get(route('cart.index'))->assertOk()
            ->assertSee('Rattan Fruit Basket')->assertSee('USB-C Fast Charger 20W');
        $this->assertEquals([2, 1], CartItem::where('user_id', $this->buyer1->id)->orderBy('id')->pluck('quantity')->all());
    }

    /** BUY-CART-010 (A22) */
    public function test_buy_cart_010_cannot_change_another_buyers_cart(): void
    {
        $theirs = $this->cart($this->buyer2, $this->p02, 2);

        $this->actingAs($this->buyer1)->patch(route('cart.update', $theirs->id), ['quantity' => 5])->assertNotFound();
        $this->actingAs($this->buyer1)->delete(route('cart.destroy', $theirs->id))->assertNotFound();
        $this->assertSame(2, $theirs->fresh()->quantity);
    }

    // ── B7 Vouchers ───────────────────────────────────────────

    /** BUY-VOU-003 (A2) */
    public function test_buy_vou_003_stacking_per_the_agreed_rule(): void
    {
        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $this->p01, 1, 'Natural / M'), $this->cart($this->buyer1, $this->p02), $this->cart($this->buyer1, $this->p05)]);
        $all = ['platform_voucher' => 'PLAT100', 'shipping_voucher' => 'FREESHIP'];

        $this->assertEquals(1083, $this->preview($all + ['seller_vouchers' => [$this->seller1->id => 'MCH50']])->json('total'));

        // MCH10 for Seller 1 replaces MCH50 (never both): ₱699 × 10% = ₱69.90.
        $p = $this->preview($all + ['seller_vouchers' => [$this->seller1->id => 'MCH50'], 'voucher_code' => 'MCH10'])->json();
        $this->assertSame([$this->seller1->id => 'MCH10'], $p['applied']['sellers']);
        $this->assertEquals(69.90, $p['seller_discount']);
        $this->assertEquals(1063.10, $p['total']);

        // RVSECRET isn't applicable: Seller 2's subtotal ₱399 is under ₱1,000.
        $p = $this->preview($all + ['seller_vouchers' => [$this->seller1->id => 'MCH10', $this->seller2->id => 'RVSECRET']])->json();
        $this->assertArrayNotHasKey($this->seller2->id, $p['applied']['sellers']);
        $this->assertStringContainsString('₱1,000', implode(' ', $p['notices']));
        $this->assertEquals(1063.10, $p['total']);
    }

    /** BUY-VOU-004 */
    public function test_buy_vou_004_minimum_spend_at_the_boundary(): void
    {
        $line = $this->cart($this->buyer1, $this->p02);
        $this->openCheckout($this->buyer1, [$line]);
        $mch50 = ['seller_vouchers' => [$this->seller1->id => 'MCH50']];

        $this->p02->update(['price' => 299.99]);
        $p = $this->preview($mch50)->json();
        $this->assertEquals(0, $p['seller_discount']);
        $this->assertStringContainsString('spend ₱0.01 more', implode(' ', $p['notices']));

        foreach ([300.00, 300.01] as $price) {
            $this->p02->update(['price' => $price]);
            $this->assertEquals(50, $this->preview($mch50)->json('seller_discount'), (string) $price);
        }
        $this->assertSame(0, Order::count());
    }

    /** BUY-VOU-009 */
    public function test_buy_vou_009_shipping_voucher_larger_than_shipping(): void
    {
        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $this->p02)]);
        $p = $this->preview(['shipping_voucher' => 'FREESHIP'])->json();
        $this->assertEquals([50, 50, 450], [$p['shipping_total'], $p['shipping_discount'], $p['subtotal']]);
        $this->assertEquals(450, $p['total']);   // ₱0.00 shipping, never negative

        CartItem::query()->delete();
        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $this->p02), $this->cart($this->buyer1, $this->p01, 1, 'Natural / M')]);
        $p = $this->preview(['shipping_voucher' => 'FREESHIP'])->json();
        $this->assertEquals([65, 50, 699], [$p['shipping_total'], $p['shipping_discount'], $p['subtotal']]);
        $this->assertEquals(699 + 15, $p['total']);   // ₱15.00 shipping left; merchandise unchanged
    }

    // ── B8 Checkout ───────────────────────────────────────────

    /** BUY-CHK-010 (A27) */
    public function test_buy_chk_010_voucher_becomes_invalid_during_checkout(): void
    {
        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $this->p02)]);
        $form = $this->checkoutForm($this->addr1, ['seller_vouchers' => [$this->seller1->id => 'MCH50']]);
        $this->assertEquals(50, $this->preview(['seller_vouchers' => [$this->seller1->id => 'MCH50']])->json('seller_discount'));

        Voucher::where('code', 'MCH50')->sole()->end();   // Seller 1 ends MCH50

        $this->actingAs($this->buyer1)->from(route('checkout.index'))->post(route('checkout.store'), $form)
            ->assertSessionHasErrors("seller_vouchers.{$this->seller1->id}");
        $this->assertSame(0, Order::count());
        $this->assertEquals(500, $this->preview()->json('total'));   // the updated total, without MCH50

        // The same page can now place the order without the voucher (it isn't treated as already placed).
        unset($form['seller_vouchers']);
        $this->flushSession();
        $this->actingAs($this->buyer1)->post(route('checkout.store'), $form)->assertRedirect(route('checkout.confirmation'));
        $this->assertEquals(500, Order::sole()->total_price);
    }

    /** BUY-CHK-011 (A22) */
    public function test_buy_chk_011_tampered_price_or_total_in_the_request(): void
    {
        $line = $this->cart($this->buyer1, $this->p02);
        $this->openCheckout($this->buyer1, [$line]);

        // A tampered item price is caught as a price change; nothing is placed at ₱1.00.
        $this->place(['prices' => [$line->id => '1.00']])->assertSessionHas('error', fn ($m) => str_contains($m, 'Prices changed'));
        $this->assertSame(0, Order::count());

        // A tampered total is ignored: the order uses the real price.
        $this->flushSession();
        $this->place(['total' => '1.00', 'total_price' => '1.00', 'subtotal' => '1.00', 'price' => '1.00'])->assertSessionHasNoErrors();
        $order = Order::sole();
        $this->assertEquals(500, $order->total_price);   // ₱450 + ₱50 shipping
        $this->assertEquals(450, $order->items()->sole()->price);
    }

    // ── B10 Tracking, B11 Cancellation ────────────────────────

    /** BUY-TRK-006 (A22) */
    public function test_buy_trk_006_cannot_open_another_buyers_order(): void
    {
        $theirs = $this->order(Order::PENDING, $this->buyer2);

        $res = $this->actingAs($this->buyer1)->get(route('profile.orders.show', $theirs->id));
        $this->assertContains($res->getStatusCode(), [302, 403, 404]);
        $res->assertDontSee('78 Osmeña Blvd.')->assertDontSee('Ana Marie Reyes');
    }

    /** BUY-CAN-006 (A37) */
    public function test_buy_can_006_double_click_cancel(): void
    {
        $order = $this->order(Order::PROCESSING);
        $this->assertSame(18, $this->p02->fresh()->stock);

        $this->actingAs($this->buyer1)->post(route('orders.cancel', $order->id), ['cancellation_reason' => 'Changed my mind'])->assertSessionHas('success');
        $this->actingAs($this->buyer1)->post(route('orders.cancel', $order->id), ['cancellation_reason' => 'Changed my mind'])->assertSessionMissing('success');

        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
        $this->assertSame(20, $this->p02->fresh()->stock);   // restored once, not twice
    }

    // ── B12 Return, B13 Ratings, B14 Chat ─────────────────────

    /** BUY-RET-004 (A31) */
    public function test_buy_ret_004_evidence_and_description_rules(): void
    {
        $order = $this->order(Order::DELIVERED);
        $ask = fn (array $data) => $this->actingAs($this->buyer1)->from(route('profile.orders.show', $order->id))
            ->post(route('orders.return', $order->id), $data + ['reason' => 'Damaged item', 'details' => 'Basket arrived with a broken rim.']);
        $photos = fn ($n) => array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg")->size(500), range(1, $n));

        $ask([])->assertSessionHasErrors('evidence');
        $ask(['evidence_photos' => $photos(6)])->assertSessionHasErrors(['evidence_photos' => 'You can add up to 5 photos.']);
        $ask(['evidence_photos' => [UploadedFile::fake()->image('size_5.1MB.jpg')->size(5222)]])->assertSessionHasErrors('evidence_photos.0');
        $ask(['evidence_photos' => $photos(1), 'details' => 'Item used'])->assertSessionHasErrors('details');
        $this->assertSame(0, \App\Models\ReturnRequest::count());

        $ask(['evidence_photos' => $photos(5)])->assertSessionHasNoErrors();
        $this->assertSame(1, \App\Models\ReturnRequest::count());
    }

    /** BUY-RATE-004 (A12) */
    public function test_buy_rate_004_stars_text_and_photo_limits(): void
    {
        $item = $this->order(Order::COMPLETED)->items()->first();
        $rate = fn (array $data) => $this->actingAs($this->buyer1)->from('/r')->post(route('reviews.store', $item), $data);
        $photos = fn ($n) => array_map(fn ($i) => UploadedFile::fake()->image("r{$i}.jpg")->size(300), range(1, $n));

        $rate(['rating' => 0])->assertSessionHasErrors('rating');
        $rate(['rating' => 5, 'comment' => str_repeat('a', 501)])->assertSessionHasErrors('comment');
        $rate(['rating' => 5, 'photos' => $photos(6)])->assertSessionHasErrors(['photos' => 'You can add up to 5 photos.']);
        $this->assertSame(0, Review::count());

        $rate(['rating' => 5, 'comment' => str_repeat('a', 500), 'photos' => $photos(5)])->assertSessionHas('success');
        $review = Review::sole();
        $this->assertSame(500, mb_strlen($review->comment));
        $this->assertCount(5, $review->photos());
    }

    /** BUY-CHAT-002 (A16, A25) */
    public function test_buy_chat_002_invalid_attachments_and_message_length(): void
    {
        $thread = Message::threadKey($this->buyer1->id, $this->seller1->id);
        $send = fn (array $data) => $this->actingAs($this->buyer1)->from(route('messages.direct', $thread))->post(route('messages.direct.store', $thread), $data);

        $send(['attachment' => UploadedFile::fake()->create('installer.exe', 2100, 'application/x-msdownload')])->assertSessionHasErrors('attachment');
        $send(['attachment' => UploadedFile::fake()->image('size_5.1MB.jpg')->size(5222)])->assertSessionHasErrors('attachment');
        $send(['body' => str_repeat('m', 1001)])->assertSessionHasErrors('body');
        $this->assertSame(0, Message::count());
    }

    /** BUY-CHAT-005 */
    public function test_buy_chat_005_mark_notifications_as_read(): void
    {
        UserNotification::where('user_id', $this->buyer1->id)->delete();
        foreach (['Order placed', 'Order confirmed', 'Order shipped'] as $title) {
            UserNotification::create(['user_id' => $this->buyer1->id, 'title' => $title, 'body' => $title, 'type' => 'info', 'url' => '/profile/orders']);
        }
        $unread = fn () => UserNotification::where('user_id', $this->buyer1->id)->whereNull('read_at')->count();
        $this->assertSame(3, $unread());

        $this->actingAs($this->buyer1)->get(route('notifications.open', UserNotification::where('user_id', $this->buyer1->id)->first()->id));
        $this->assertSame(2, $unread());
        $this->actingAs($this->buyer1)->post(route('notifications.read-all'))->assertSessionHas('success');
        $this->assertSame(0, $unread());
    }
}
