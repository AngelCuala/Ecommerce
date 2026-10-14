<?php

namespace Tests\Feature\Buyer;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Voucher;
use App\Models\VoucherClaim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuyerTestData;
use Tests\TestCase;

/** Buyer workbook B6 (Cart), B7 (Vouchers), B8 (Checkout) and B9-001 (COD). */
class B06CartVoucherCheckoutTest extends TestCase
{
    use RefreshDatabase, BuyerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBuyerTestData();
    }

    private function add($product, int $qty = 1, ?string $variation = null, $buyer = null)
    {
        return $this->actingAs($buyer ?? $this->buyer1)->from('/p')->post(route('cart.store', $product->id), array_filter([
            'quantity' => $qty, 'variation_id' => $variation ? $this->variation($product, $variation)->id : null,
        ]));
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

    /** The worked checkout example's cart: Tote Natural-M + Rattan Basket (Seller 1), USB-C Charger (Seller 2). */
    private function workedExampleCart(): array
    {
        return [
            $this->cart($this->buyer1, $this->p01, 1, 'Natural / M'),
            $this->cart($this->buyer1, $this->p02),
            $this->cart($this->buyer1, $this->p05),
        ];
    }

    // ── B6 Cart ───────────────────────────────────────────────

    /** BUY-CART-001, 002, 003 */
    public function test_lines_merge_by_variant_and_group_by_store(): void
    {
        $this->add($this->p01, 1, 'Natural / M');
        $this->add($this->p01, 2, 'Natural / M');
        $this->assertSame(1, CartItem::count());
        $this->assertSame(3, CartItem::first()->quantity);

        $this->add($this->p01, 1, 'Black / S');
        $this->assertSame(2, CartItem::count());

        $this->add($this->p05);
        $this->actingAs($this->buyer1)->get(route('cart.index'))->assertOk()
            ->assertSeeInOrder(['Maria Clara Handicrafts', 'Natural / M', 'Black / S', 'RV Gadget Hub', 'USB-C Fast Charger 20W']);
    }

    /** BUY-CART-004 */
    public function test_only_ticked_items_are_checked_out(): void
    {
        $tote = $this->cart($this->buyer1, $this->p01, 1, 'Natural / M');
        $basket = $this->cart($this->buyer1, $this->p02);
        $charger = $this->cart($this->buyer1, $this->p05);

        $page = $this->openCheckout($this->buyer1, [$tote, $basket])->assertOk();
        $this->assertEqualsCanonicalizing([$tote->id, $basket->id], $page->viewData('lines')->pluck('id')->all());
        $this->assertEquals(699, $page->viewData('summary')['quote']['subtotal']);

        $this->place()->assertSessionHasNoErrors()->assertRedirect(route('checkout.confirmation'));
        $this->assertSame([$charger->id], CartItem::pluck('id')->all());   // the unticked line stays
    }

    /** BUY-CART-005, 006, 009 */
    public function test_quantity_price_changes_and_removing(): void
    {
        $line = $this->cart($this->buyer1, $this->p02);
        $this->actingAs($this->buyer1)->patch(route('cart.update', $line->id), ['quantity' => 20])->assertSessionHas('success');
        $this->actingAs($this->buyer1)->patch(route('cart.update', $line->id), ['quantity' => 21])->assertSessionHas('error', 'Only 20 left in stock.');
        $this->assertSame(20, $line->fresh()->quantity);

        $this->p02->update(['price' => 420]);
        $this->actingAs($this->buyer1)->get(route('cart.index'))->assertSee('₱420.00')->assertSee('₱8,400.00');

        $this->actingAs($this->buyer1)->delete(route('cart.destroy', $line->id))->assertSessionHas('success');
        $this->assertSame(0, CartItem::count());
    }

    /** BUY-CART-007, 008 */
    public function test_sold_out_and_deleted_items_are_marked_and_cannot_be_checked_out(): void
    {
        $basket  = $this->cart($this->buyer1, $this->p02);
        $charger = $this->cart($this->buyer1, $this->p05);
        $lamp    = $this->cart($this->buyer1, $this->p03);
        $this->p02->update(['stock' => 0]);
        $this->p05->delete();

        $cart = $this->actingAs($this->buyer1)->get(route('cart.index'))->assertOk()
            ->assertSee('Sold out')->assertSee('This product was removed by the seller');
        $this->assertMatchesRegularExpression('/value="' . $basket->id . '"[^>]*disabled/', $cart->getContent());
        $this->assertMatchesRegularExpression('/value="' . $charger->id . '"[^>]*disabled/', $cart->getContent());

        $this->openCheckout($this->buyer1, [$basket, $lamp])->assertRedirect(route('cart.index'));
        $this->openCheckout($this->buyer1, [$lamp])->assertOk();                 // the other item can still be bought
        $this->assertSame(3, CartItem::where('user_id', $this->buyer1->id)->count());   // 008: still saved
    }

    // ── B7 Vouchers ───────────────────────────────────────────

    /** BUY-VOU-001 */
    public function test_claim_a_voucher_into_my_vouchers(): void
    {
        $mch50 = Voucher::where('code', 'MCH50')->first();
        $this->actingAs($this->buyer1)->post(route('vouchers.claim', $mch50))->assertSessionHas('success', 'MCH50 was added to My Vouchers.');
        $this->actingAs($this->buyer1)->post(route('vouchers.claim', $mch50))->assertSessionHas('success', 'You already claimed MCH50.');
        $this->assertSame(1, VoucherClaim::count());
        $this->actingAs($this->buyer1)->get(route('vouchers.wallet'))->assertSee('MCH50')->assertSee('Available');
        $this->actingAs($this->buyer1)->get(route('stores.show', $this->seller1->id))->assertSee('Claimed');
    }

    /** BUY-VOU-002, 003, BUY-CHK-001: the worked checkout example. */
    public function test_worked_checkout_example(): void
    {
        $this->openCheckout($this->buyer1, $this->workedExampleCart())->assertOk();

        $p = $this->preview(['seller_vouchers' => [$this->seller1->id => 'MCH50'], 'platform_voucher' => 'PLAT100', 'shipping_voucher' => 'FREESHIP'])->json();
        $this->assertEquals(1098, $p['subtotal']);
        $this->assertEquals(65, $p['stores'][$this->seller1->id]['shipping']);    // 1.2 kg → 2 kg within NCR
        $this->assertEquals(120, $p['stores'][$this->seller2->id]['shipping']);   // 0.2 kg → 1 kg Visayas to NCR
        $this->assertEquals(185, $p['shipping_total']);
        $this->assertEquals(50, $p['seller_discount']);
        $this->assertEquals(100, $p['platform_discount']);
        $this->assertEquals(50, $p['shipping_discount']);
        $this->assertEquals(1083, $p['total']);

        // A second Seller 1 voucher replaces the first instead of stacking.
        $swap = $this->preview(['seller_vouchers' => [$this->seller1->id => 'MCH50'], 'voucher_code' => 'MCH10'])->json();
        $this->assertSame([$this->seller1->id => 'MCH10'], $swap['applied']['sellers']);

        $this->place(['seller_vouchers' => [$this->seller1->id => 'MCH50'], 'platform_voucher' => 'PLAT100', 'shipping_voucher' => 'FREESHIP'])
            ->assertSessionHasNoErrors()->assertRedirect(route('checkout.confirmation'));

        // Two orders, one per store; together ₱1,083.00.
        $s1 = Order::where('seller_id', $this->seller1->id)->firstOrFail();
        $s2 = Order::where('seller_id', $this->seller2->id)->firstOrFail();
        $this->assertEquals(1083, round($s1->total_price + $s2->total_price, 2));
        $this->assertEquals(65, $s1->shipping_fee);
        $this->assertEquals(120, $s2->shipping_fee);
        // Seller earnings (A8, A9): platform and shipping vouchers don't reduce them.
        $this->assertEquals(616.55, round(OrderItem::where('order_id', $s1->id)->sum('seller_earning'), 2));
        $this->assertEquals(379.05, round(OrderItem::where('order_id', $s2->id)->sum('seller_earning'), 2));
        $this->assertSame('Natural / M', OrderItem::where('order_id', $s1->id)->whereNotNull('variation_id')->value('variation_name'));
        // A3: stock is taken when the order is placed (the variant's own stock too).
        $this->assertSame(7, $this->variation($this->p01, 'Natural / M')->stock);
        $this->assertSame(19, $this->p02->fresh()->stock);

        $this->actingAs($this->buyer1)->get(route('checkout.confirmation'))->assertOk()->assertSee('₱1,083.00');
    }

    /** BUY-VOU-004, 005, 006 */
    public function test_voucher_rules_and_messages(): void
    {
        $cheap = $this->product($this->seller1, $this->homeLiving, 'Woven Coaster', 299, 10, 0.1);
        $exact = $this->product($this->seller1, $this->homeLiving, 'Woven Tray', 300, 10, 0.1);

        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $cheap)]);
        $notice = $this->preview(['seller_vouchers' => [$this->seller1->id => 'MCH50']])->json('notices.0');
        $this->assertStringContainsString('spend ₱1.00 more', $notice);

        CartItem::query()->delete();
        $line = $this->cart($this->buyer1, $exact);
        $this->openCheckout($this->buyer1, [$line]);
        $this->assertEquals(50, $this->preview(['seller_vouchers' => [$this->seller1->id => 'MCH50']])->json('seller_discount'));

        // 005: each bad code has its own message.
        $this->assertStringContainsString("doesn't exist", $this->preview(['voucher_code' => 'NOSUCHCODE'])->json('errors.voucher_code'));
        $this->assertStringContainsString('expired', $this->preview(['voucher_code' => 'MCHOLD'])->json('notices.0'));
        $this->assertStringContainsString('not active yet', $this->preview(['voucher_code' => 'MCHSOON'])->json('notices.0'));
        $this->assertStringContainsString('fully claimed', $this->preview(['voucher_code' => 'MCHMAX'])->json('notices.0'));

        // 006: once per buyer.
        $this->place(['seller_vouchers' => [$this->seller1->id => 'MCH50']])->assertSessionHasNoErrors();
        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $exact)]);
        $this->assertStringContainsString('already used', $this->preview(['seller_vouchers' => [$this->seller1->id => 'MCH50']])->json('notices.0'));
    }

    /** BUY-VOU-007 */
    public function test_voucher_is_removed_with_a_notice_when_the_cart_drops_below_the_minimum(): void
    {
        $basket = $this->cart($this->buyer1, $this->p02);
        $this->openCheckout($this->buyer1, [$basket]);
        $this->preview(['seller_vouchers' => [$this->seller1->id => 'MCH50']]);   // chosen on ₱450

        $tote = $this->cart($this->buyer1, $this->p01, 1, 'Natural / S');          // back in the cart: only ₱199 now
        $page = $this->openCheckout($this->buyer1, [$tote])->assertOk()->assertSee('MCH50 was removed');
        $this->assertEquals(0, $page->viewData('summary')['quote']['seller_discount']);
    }

    /** BUY-VOU-008, 009, 010 */
    public function test_percentage_cap_never_negative_and_sale_items(): void
    {
        // 008: 10% of ₱600 = ₱60; 10% of ₱1,250 = ₱125 → capped at ₱100.
        $six = $this->product($this->seller1, $this->homeLiving, 'Six Hundred', 600, 5, 0.1);
        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $six)]);
        $this->assertEquals(60, $this->preview(['seller_vouchers' => [$this->seller1->id => 'MCH10']])->json('seller_discount'));
        CartItem::query()->delete();
        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $this->p03)]);
        $this->assertEquals(100, $this->preview(['seller_vouchers' => [$this->seller1->id => 'MCH10']])->json('seller_discount'));

        // 009: a voucher bigger than the item never makes the merchandise negative.
        $this->voucher('BIG500', 500, 0, ['scope' => 'seller', 'seller_id' => $this->seller1->id]);
        CartItem::query()->delete();
        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $this->p01, 1, 'Natural / S')]);
        $p = $this->preview(['seller_vouchers' => [$this->seller1->id => 'BIG500']])->json();
        $this->assertEquals(199, $p['seller_discount']);
        $this->assertEquals($p['shipping_total'], $p['total']);

        // 010: on a sale item the minimum spend is checked against the sale price.
        $this->p02->update(['sale_price' => 360]);
        CartItem::query()->delete();
        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $this->p02)]);
        $p = $this->preview(['seller_vouchers' => [$this->seller1->id => 'MCH50']])->json();
        $this->assertEquals(360, $p['subtotal']);
        $this->assertEquals(310, $p['subtotal'] - $p['seller_discount']);
    }

    /** BUY-VOU-011 */
    public function test_voucher_comes_back_after_cancelling(): void
    {
        $mch50 = Voucher::where('code', 'MCH50')->first();
        VoucherClaim::create(['user_id' => $this->buyer1->id, 'voucher_id' => $mch50->id]);
        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $this->p02)]);
        $this->place(['seller_vouchers' => [$this->seller1->id => 'MCH50']])->assertSessionHasNoErrors();
        $this->actingAs($this->buyer1)->get(route('vouchers.wallet'))->assertSee('Used');

        $order = Order::firstOrFail();
        $this->actingAs($this->buyer1)->post(route('orders.cancel', $order->id), ['cancellation_reason' => 'Changed my mind']);
        $this->assertSame(0, $mch50->fresh()->used_count);
        $this->actingAs($this->buyer1)->get(route('vouchers.wallet'))->assertSee('Available');
    }

    /** Code-only vouchers are hidden but work when typed. */
    public function test_code_only_voucher_works_when_typed(): void
    {
        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $this->p06, 1, 'Black')])->assertDontSee('RVSECRET');
        $this->assertSame([$this->seller2->id => 'RVSECRET'], $this->preview(['voucher_code' => 'RVSECRET'])->json('applied.sellers'));
    }

    // ── B8 Checkout ───────────────────────────────────────────

    /** BUY-CHK-002 */
    public function test_shipping_depends_on_the_address(): void
    {
        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $this->p02)]);
        $this->assertEquals(50, $this->preview()->json('shipping_total'));                         // NCR → NCR, 0.8 kg
        $this->addressFor($this->buyer1, self::CEBU_CITY);
        $cebu = $this->buyer1->addresses()->where('municipality_code', self::CEBU_CITY)->first();
        $this->assertEquals(120, $this->preview(['address_id' => $cebu->id])->json('shipping_total'));   // NCR → Visayas
    }

    /** BUY-CHK-003 */
    public function test_weight_is_rounded_up_to_the_next_kg(): void
    {
        $line = $this->cart($this->buyer2, $this->p05, 5);   // 5 × 0.2 kg = 1.0 kg, Cebu → Cebu
        $this->openCheckout($this->buyer2, [$line]);
        $this->assertEquals(60, $this->preview(['address_id' => $this->addr3->id], $this->buyer2)->json('shipping_total'));
        $line->update(['quantity' => 6]);                    // 1.2 kg → 2 kg
        $this->assertEquals(80, $this->preview(['address_id' => $this->addr3->id], $this->buyer2)->json('shipping_total'));
    }

    /** BUY-CHK-004 */
    public function test_unserviceable_address_blocks_the_order(): void
    {
        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $this->p02)]);
        $this->assertStringContainsString("don't deliver to Batanes", $this->preview(['address_id' => $this->addr4->id])->json('unserviceable'));
        $this->place([], null, $this->addr4)->assertSessionHas('error', fn ($m) => str_contains($m, 'Batanes'));
        $this->assertSame(0, Order::count());
    }

    /** BUY-CHK-005, 006 */
    public function test_note_to_seller_and_payment_method(): void
    {
        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $this->p02)]);
        $this->place(['buyer_notes' => [$this->seller1->id => str_repeat('x', 201)]])->assertSessionHasErrors('buyer_notes.' . $this->seller1->id);
        $this->place(['payment_method' => ''])->assertSessionHasErrors(['payment_method' => 'Please choose a payment method.']);
        $this->assertSame(0, Order::count());

        $this->place(['buyer_notes' => [$this->seller1->id => 'Paki-wrap po as gift']])->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->assertSame('Paki-wrap po as gift', $order->buyer_note);
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))->assertSee('Paki-wrap po as gift');
    }

    /** BUY-CHK-007 */
    public function test_double_click_places_the_order_once(): void
    {
        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $this->p02, 2)]);
        $form = $this->checkoutForm($this->addr1);
        $this->actingAs($this->buyer1)->post(route('checkout.store'), $form)->assertRedirect(route('checkout.confirmation'));
        $this->actingAs($this->buyer1)->post(route('checkout.store'), $form)->assertRedirect(route('profile.orders'));
        $this->assertSame(1, Order::count());
        $this->assertSame(18, $this->p02->fresh()->stock);
    }

    /** BUY-CHK-008 */
    public function test_price_change_during_checkout_is_not_charged_silently(): void
    {
        $line = $this->cart($this->buyer1, $this->p02);
        $this->openCheckout($this->buyer1, [$line])->assertOk();
        $this->p02->update(['price' => 480]);

        $this->place(['prices' => [$line->id => '450.00']])
            ->assertRedirect(route('checkout.index'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'now ₱480.00 (was ₱450.00)'));
        $this->assertSame(0, Order::count());
    }

    /** BUY-CHK-009 */
    public function test_stock_that_ran_out_blocks_the_order(): void
    {
        $mine = $this->cart($this->buyer1, $this->p03);
        $this->openCheckout($this->buyer1, [$mine]);

        $theirs = $this->cart($this->buyer2, $this->p03);
        $this->openCheckout($this->buyer2, [$theirs]);
        $this->place([], $this->buyer2, $this->addr3)->assertSessionHasNoErrors();
        $this->assertSame(0, $this->p03->fresh()->stock);

        $this->place()->assertRedirect(route('cart.index'))->assertSessionHas('error', fn ($m) => str_contains($m, 'Sold out'));
        $this->assertSame(1, Order::count());
        $this->assertSame(0, $this->p03->fresh()->stock);   // never below 0
    }

    // ── B9 Payment ────────────────────────────────────────────

    /** BUY-PAY-001 */
    public function test_cod_order_goes_to_to_ship_with_the_amount_to_collect(): void
    {
        $this->openCheckout($this->buyer1, [$this->cart($this->buyer1, $this->p02)]);
        $this->place()->assertSessionHasNoErrors();
        $order = Order::firstOrFail();

        $this->assertSame('COD', $order->payment_method);
        $this->assertSame('to_ship', $order->tab());
        $this->actingAs($this->seller1)->get(route('seller.orders.show', $order->id))
            ->assertSee('Collect on delivery')->assertSee('₱500.00');   // ₱450 + ₱50 shipping
    }
}
