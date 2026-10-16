<?php

namespace Tests\Feature\Seller;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** Seller workbook S5 (Inventory & Stock, SEL-INV-001…010) and S6 (Product Discounts & Sales, SEL-DISC-001…010). */
class S05InventoryAndDiscountsTest extends TestCase
{
    use RefreshDatabase, SellerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedSellerTestData();
    }

    private function saveP02(array $over = [])
    {
        return $this->actingAs($this->seller1)->from(route('seller.books.edit', $this->p02))
            ->put(route('seller.books.update', $this->p02), $this->editForm($this->p02->fresh(), $over));
    }

    private function buyerPrice(): string
    {
        return $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertOk()->getContent();
    }

    /** An unpaid e-wallet (GCash) order for 1 × P-02. */
    private function unpaidEwalletOrder(): Order
    {
        config(['payments.gateway' => 'sandbox']);
        return $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)], null, ['payment_method' => 'gcash'])->first();
    }

    // ── S5 Inventory & Stock ────────────────────────────────────

    /** SEL-INV-001 (A3) */
    public function test_sel_inv_001_stock_deducted_when_a_cod_order_is_placed(): void
    {
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 2)]);
        $this->assertSame(18, $this->p02->fresh()->stock);
        $this->actingAs($this->seller1)->get(route('seller.books.index'))->assertSee('value="18"', false);
    }

    /** SEL-INV-002 (A3) */
    public function test_sel_inv_002_stock_deducted_when_an_unpaid_ewallet_order_is_placed(): void
    {
        $order = $this->unpaidEwalletOrder();
        $this->assertSame(Order::AWAITING_PAYMENT, $order->status);   // To Pay
        $this->assertSame(19, $this->p02->fresh()->stock);
    }

    /** SEL-INV-003 (A3) */
    public function test_sel_inv_003_stock_restored_when_the_buyer_cancels(): void
    {
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 2)])->first();
        $this->assertSame(18, $this->p02->fresh()->stock);

        $this->actingAs($this->buyer1)->post(route('orders.cancel', $order->id), ['cancellation_reason' => 'Changed my mind'])->assertSessionHas('success');
        $this->assertSame(20, $this->p02->fresh()->stock);
    }

    /** SEL-INV-004 (A3, A6) */
    public function test_sel_inv_004_stock_restored_on_auto_cancel_of_an_unpaid_order(): void
    {
        $order = $this->unpaidEwalletOrder();
        $this->assertSame(19, $this->p02->fresh()->stock);

        $this->travel(59)->minutes();
        $this->artisan('orders:cancel-unpaid')->assertSuccessful();
        $this->assertSame(Order::AWAITING_PAYMENT, $order->fresh()->status);

        $this->travel(2)->minutes();   // 1 hour after it was placed
        $this->artisan('orders:cancel-unpaid')->assertSuccessful();
        $this->assertSame(Order::CANCELLED, $order->fresh()->status);
        $this->assertStringContainsString('unpaid', $order->fresh()->cancellation_reason);
        $this->assertSame(20, $this->p02->fresh()->stock);
    }

    /** SEL-INV-005 (A3) */
    public function test_sel_inv_005_stock_per_variant(): void
    {
        $before = $this->p01->variations()->pluck('stock', 'name')->all();
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p01, 1, 'Natural / M')]);

        $after = $this->p01->variations()->pluck('stock', 'name')->all();
        $this->assertSame(7, $after['Natural / M']);
        unset($before['Natural / M'], $after['Natural / M']);
        $this->assertSame($before, $after);   // every other variant unchanged
    }

    /** SEL-INV-006 (A3) */
    public function test_sel_inv_006_overselling_1_left_2_buyers_check_out_at_once(): void
    {
        $first  = $this->cart($this->buyer1, $this->p03);
        $second = $this->cart($this->buyer2, $this->p03);
        $this->openCheckout($this->buyer2, [$second])->assertOk();   // Buyer 2 sees 1 in stock…

        // …but Buyer 1 places the order first. The stock row is locked, so only one gets the lamp.
        $this->flushSession();
        $this->openCheckout($this->buyer1, [$first])->assertOk();
        $this->actingAs($this->buyer1)->post(route('checkout.store'), $this->checkoutForm($this->addr1))->assertSessionHasNoErrors();

        $this->flushSession();
        $this->actingAs($this->buyer2)->withSession(['checkout_selection' => [$second->id]])->from(route('checkout.index'))
            ->post(route('checkout.store'), $this->checkoutForm($this->addr3))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Capiz Shell Lamp') && str_contains($m, 'Sold out'));
        $this->assertSame(0, Order::where('user_id', $this->buyer2->id)->count());
        $this->assertSame(0, \App\Models\Payment::where('user_id', $this->buyer2->id)->count());   // no charge

        $this->assertSame(1, OrderItem::where('product_id', $this->p03->id)->count());
        $this->assertSame(0, $this->p03->fresh()->stock);
    }

    /** SEL-INV-007 */
    public function test_sel_inv_007_automatic_sold_out_status(): void
    {
        $this->p07->update(['stock' => 1]);
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p07)]);

        $page = $this->actingAs($this->buyer2)->get(route('products.show', $this->p07->slug))->assertOk()->assertSee('Sold out')->getContent();
        $this->assertMatchesRegularExpression('/value="cart" class="pd-btn pd-btn-line" disabled/', $page);
        $this->assertMatchesRegularExpression('/value="buy_now" class="pd-btn pd-btn-solid" disabled/', $page);
        $this->actingAs($this->buyer2)->post(route('cart.store', $this->p07->id), ['quantity' => 1])->assertSessionHas('error', 'This item is out of stock.');
        $this->actingAs($this->seller2)->get(route('seller.books.index', ['status' => 'out_of_stock']))->assertSee('Power Bank 10000mAh')->assertSee('Out of Stock');
    }

    /** SEL-INV-008 (A29) */
    public function test_sel_inv_008_low_stock_alert_at_the_threshold(): void
    {
        $this->p02->update(['stock' => 7]);
        $alerts = fn () => UserNotification::where('user_id', $this->seller1->id)->where('title', 'Low stock')->count();

        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)]);   // 7 → 6
        $this->assertSame(0, $alerts());
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)]);   // 6 → 5: alert
        $this->assertSame(1, $alerts());
        $this->assertStringContainsString('"Rattan Fruit Basket" has only 5 left', UserNotification::where('title', 'Low stock')->value('body'));
        $this->placeOrder($this->buyer2, [$this->cart($this->buyer2, $this->p02)]);   // 5 → 4: no second alert
        $this->assertSame(1, $alerts());
    }

    /** SEL-INV-009 (A17) */
    public function test_sel_inv_009_manual_stock_adjustment(): void
    {
        $set = fn ($v) => $this->actingAs($this->seller1)->from(route('seller.books.index'))->patch(route('seller.books.stock', $this->p02), ['stock' => $v]);
        $message = 'Stock must be a whole number from 0 to 99,999.';

        foreach (['-1', '100000', '2.5', 'abc'] as $bad) {
            $set($bad)->assertSessionHasErrors('stock');
            $this->assertSame(20, $this->p02->fresh()->stock, $bad);   // the previous stock is kept
        }
        $set('-1')->assertSessionHasErrors(['stock' => $message]);
        $set('99999')->assertSessionHasNoErrors();
        $this->assertSame(99999, $this->p02->fresh()->stock);
        $set('0')->assertSessionHasNoErrors();
        $this->assertSame(0, $this->p02->fresh()->stock);
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertSee('Sold out');
    }

    /** SEL-INV-010 */
    public function test_sel_inv_010_reduce_stock_below_the_quantity_in_a_cart(): void
    {
        $line = $this->cart($this->buyer1, $this->p02, 5);
        $this->actingAs($this->seller1)->patch(route('seller.books.stock', $this->p02), ['stock' => 3]);

        $this->assertSame('Only 3 left', $line->fresh()->unavailableReason());
        $this->actingAs($this->buyer1)->get(route('cart.index'))->assertSee('Only 3 left');
        $this->openCheckout($this->buyer1, [$line])->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Only 3 left'));
        $this->assertSame(0, Order::count());
        $line->update(['quantity' => 3]);
        $this->openCheckout($this->buyer1, [$line])->assertOk();
    }

    // ── S6 Product Discounts & Sales ────────────────────────────

    /** SEL-DISC-001 (A28) */
    public function test_sel_disc_001_create_a_scheduled_percentage_sale(): void
    {
        $this->travelTo(now()->setTime(11, 0));
        $this->saveP02(['discount_percent' => 20, 'sale_starts_at' => now()->addMinutes(10)->format('Y-m-d\TH:i'),
            'sale_ends_at' => now()->addHours(2)->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();

        $p = $this->p02->fresh();
        $this->assertSame('Upcoming', $p->saleState());
        $this->assertSame('11:10', $p->sale_starts_at->format('H:i'));
        $this->assertSame('13:00', $p->sale_ends_at->format('H:i'));
        $this->assertEquals(450, $p->effective_price);
        $html = $this->buyerPrice();
        $this->assertStringContainsString('₱450.00', $html);
        $this->assertStringNotContainsString('-20%', $html);
        $this->actingAs($this->seller1)->get(route('seller.books.index'))->assertSee('Sale: Upcoming');
    }

    /** SEL-DISC-002 */
    public function test_sel_disc_002_sale_goes_live_at_the_exact_start_time(): void
    {
        $this->travelTo(now()->setTime(11, 0));
        $start = now()->addMinutes(10);
        $this->saveP02(['discount_percent' => 20, 'sale_starts_at' => $start->format('Y-m-d\TH:i'), 'sale_ends_at' => now()->addHours(2)->format('Y-m-d\TH:i')]);

        $this->travelTo($start->copy()->subMinute());
        $this->assertEquals(450, $this->p02->fresh()->effective_price);
        $this->assertStringNotContainsString('-20%', $this->buyerPrice());

        $this->travelTo($start->copy()->addMinute());
        $this->assertEquals(360, $this->p02->fresh()->effective_price);
        $html = $this->buyerPrice();
        $this->assertStringContainsString('₱360.00', $html);
        $this->assertStringContainsString('line-through" style="color:#a8a29e;">₱450.00', $html);
        $this->assertStringContainsString('-20%', $html);
        $line = $this->cart($this->buyer1, $this->p02);
        $this->openCheckout($this->buyer1, [$line])->assertOk()->assertSee('₱360.00');
    }

    /** SEL-DISC-003 */
    public function test_sel_disc_003_sale_ends_at_the_exact_end_time(): void
    {
        $this->travelTo(now()->setTime(11, 0));
        $end = now()->addHours(2);
        $this->saveP02(['discount_percent' => 20, 'sale_ends_at' => $end->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $line = $this->cart($this->buyer1, $this->p02);

        $this->travelTo($end->copy()->subMinute());
        $this->assertEquals(360, CartItem::find($line->id)->unit_price);

        $this->travelTo($end->copy()->addMinute());
        $this->assertEquals(450, $this->p02->fresh()->effective_price);
        $this->assertEquals(450, CartItem::find($line->id)->unit_price);
        $this->assertStringNotContainsString('-20%', $this->buyerPrice());
        $this->actingAs($this->buyer1)->get(route('cart.index'))->assertSee('₱450.00');
        $this->openCheckout($this->buyer1, [$line])->assertOk()->assertSee('₱450.00');
    }

    /** SEL-DISC-004 */
    public function test_sel_disc_004_invalid_sale_dates(): void
    {
        $t = now()->addDay()->setTime(12, 0);
        $this->saveP02(['discount_percent' => 20, 'sale_starts_at' => $t->format('Y-m-d\TH:i'), 'sale_ends_at' => $t->copy()->subHour()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors(['sale_ends_at' => 'The sale must end after it starts.']);
        $this->saveP02(['discount_percent' => 20, 'sale_starts_at' => $t->format('Y-m-d\TH:i'), 'sale_ends_at' => $t->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors(['sale_ends_at' => 'The sale must end after it starts.']);
        $this->saveP02(['discount_percent' => 20, 'sale_starts_at' => now()->subDay()->format('Y-m-d\TH:i'), 'sale_ends_at' => $t->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors(['sale_starts_at' => 'The sale start cannot be in the past.']);
        $this->assertEquals(0, $this->p02->fresh()->discount_percent);
        $this->assertNull($this->p02->fresh()->saleState());
    }

    /** SEL-DISC-005 */
    public function test_sel_disc_005_discount_percentage_at_the_limits(): void
    {
        foreach (['0', '100', '101', '-5'] as $pct) {
            $this->saveP02(['discount_percent' => $pct])->assertSessionHasErrors(['discount_percent' => 'The discount must be from 1% to 99%.']);
        }
        $this->saveP02(['discount_percent' => '10.5'])->assertSessionHasErrors(['discount_percent' => 'The discount must be a whole number from 1% to 99%.']);
        $this->assertNull($this->p02->fresh()->saleState());

        $this->saveP02(['discount_percent' => '1'])->assertSessionHasNoErrors();
        $this->assertEquals(445.5, $this->p02->fresh()->effective_price);
        $this->saveP02(['discount_percent' => '99'])->assertSessionHasNoErrors();
        $this->assertEquals(4.5, $this->p02->fresh()->effective_price);
    }

    /** SEL-DISC-006 (A17) */
    public function test_sel_disc_006_fixed_sale_price_at_or_above_the_original(): void
    {
        foreach (['450.01', '450.00'] as $price) {
            $this->saveP02(['sale_price' => $price])->assertSessionHasErrors(['sale_price' => 'The sale price must be lower than the original price.']);
        }
        $this->saveP02(['sale_price' => '0.99'])->assertSessionHasErrors(['sale_price' => 'The sale price must be at least ₱1.00.']);
        $this->assertNull($this->p02->fresh()->saleState());

        $this->saveP02(['sale_price' => '449.99'])->assertSessionHasNoErrors();
        $this->assertEquals(449.99, $this->p02->fresh()->effective_price);
        $this->saveP02(['sale_price' => '1.00'])->assertSessionHasNoErrors();
        $this->assertEquals(1, $this->p02->fresh()->effective_price);
    }

    /** SEL-DISC-007 (A28) */
    public function test_sel_disc_007_overlapping_sales_on_the_same_product(): void
    {
        $this->travelTo(now()->setTime(10, 0));
        $this->saveP02(['discount_percent' => 20, 'sale_starts_at' => now()->setTime(13, 0)->format('Y-m-d\TH:i'),
            'sale_ends_at' => now()->setTime(15, 0)->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();

        $this->saveP02(['discount_percent' => 10, 'sale_starts_at' => now()->setTime(14, 0)->format('Y-m-d\TH:i'),
            'sale_ends_at' => now()->setTime(16, 0)->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors('sale_starts_at');
        $errors = session('errors')->get('sale_starts_at');
        $this->assertStringContainsString('"Rattan Fruit Basket" already has a sale', $errors[0]);
        $this->assertStringContainsString('only one sale at a time', $errors[0]);

        $p = $this->p02->fresh();
        $this->assertEquals(20, $p->discount_percent);
        $this->assertSame(['13:00', '15:00'], [$p->sale_starts_at->format('H:i'), $p->sale_ends_at->format('H:i')]);
    }

    /** SEL-DISC-008 */
    public function test_sel_disc_008_sale_on_a_product_with_variants(): void
    {
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p01), $this->editForm($this->p01, ['discount_percent' => 10]))->assertSessionHasNoErrors();

        $html = $this->actingAs($this->buyer1)->get(route('products.show', $this->p01->slug))->getContent();
        foreach (['"price":179.1', '"price":224.1', '"price":269.1'] as $price) {
            $this->assertStringContainsString($price, $html);
        }
        $this->assertStringContainsString('₱179.10 – ₱269.10', $html);
        $this->assertEquals(224.10, $this->cart($this->buyer1, $this->p01, 1, 'Natural / M')->unit_price);
    }

    /** SEL-DISC-009 (A28) */
    public function test_sel_disc_009_edit_a_running_sale(): void
    {
        $this->saveP02(['discount_percent' => 20, 'sale_ends_at' => now()->addDay()->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $this->assertEquals(360, OrderItem::where('order_id', $order->id)->value('price'));

        $this->saveP02(['discount_percent' => 30, 'sale_ends_at' => now()->addDay()->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $this->assertEquals(315, $this->p02->fresh()->effective_price);
        $this->flushSession();
        $this->actingAs($this->buyer2)->get(route('products.show', $this->p02->slug))->assertSee('₱315.00');
        // Buyer 1's earlier order keeps the price paid.
        $this->assertEquals(360, OrderItem::where('order_id', $order->id)->value('price'));
        $this->actingAs($this->buyer1)->get(route('profile.orders.show', $order->id))->assertSee('360.00');
    }

    /** SEL-DISC-010 (A28) */
    public function test_sel_disc_010_end_a_running_sale_now(): void
    {
        $this->saveP02(['discount_percent' => 20, 'sale_ends_at' => now()->addDay()->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $line = $this->cart($this->buyer1, $this->p02);
        $this->assertEquals(360, $line->fresh()->unit_price);

        $this->actingAs($this->seller1)->patch(route('seller.books.end-sale', $this->p02))->assertSessionHas('success', fn ($m) => str_contains($m, '₱450.00'));
        $this->assertSame('Ended', $this->p02->fresh()->saleState());
        $this->assertEquals(450, $this->p02->fresh()->effective_price);
        $this->assertStringNotContainsString('-20%', $this->buyerPrice());
        $this->assertEquals(450, CartItem::find($line->id)->unit_price);
        $this->actingAs($this->buyer1)->get(route('cart.index'))->assertSee('₱450.00');
    }
}
