<?php

namespace Tests\Feature\Seller;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SellerTestData;
use Tests\TestCase;

/** Seller workbook S5 (Inventory & Stock) and S6 (Product Discounts & Sales). */
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

    // ── S5 Inventory & Stock ────────────────────────────────────

    /** SEL-INV-001 (A3) */
    public function test_sel_inv_001_stock_taken_when_the_order_is_placed(): void
    {
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 2)]);
        $this->assertSame(18, $this->p02->fresh()->stock);
        $this->actingAs($this->seller1)->get(route('seller.books.index'))->assertSee('value="18"', false);
    }

    /** SEL-INV-002 (A3) */
    public function test_sel_inv_002_stock_restored_on_cancellation(): void
    {
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02, 2)])->first();
        $this->assertSame(18, $this->p02->fresh()->stock);

        $this->actingAs($this->buyer1)->post(route('orders.cancel', $order->id), ['cancellation_reason' => 'Changed my mind'])->assertSessionHas('success');
        $this->assertSame(20, $this->p02->fresh()->stock);
    }

    /** SEL-INV-003 (A3) */
    public function test_sel_inv_003_last_unit_two_buyers(): void
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
        $this->assertSame(0, \App\Models\Order::where('user_id', $this->buyer2->id)->count());

        $this->assertSame(1, OrderItem::where('product_id', $this->p03->id)->count());
        $this->assertSame(0, $this->p03->fresh()->stock);
    }

    /** SEL-INV-004 */
    public function test_sel_inv_004_automatic_out_of_stock(): void
    {
        $this->p07->update(['stock' => 1]);
        $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p07)]);

        $page = $this->actingAs($this->buyer2)->get(route('products.show', $this->p07->slug))->assertOk()->assertSee('Sold out')->getContent();
        $this->assertMatchesRegularExpression('/value="cart" class="pd-btn pd-btn-line" disabled/', $page);
        $this->actingAs($this->buyer2)->post(route('cart.store', $this->p07->id), ['quantity' => 1])->assertSessionHas('error', 'This item is out of stock.');
        $this->actingAs($this->seller1)->get(route('seller.books.index', ['status' => 'out_of_stock']))->assertDontSee('Power Bank');   // Seller 2's product
        $this->actingAs($this->seller2)->get(route('seller.books.index', ['status' => 'out_of_stock']))->assertSee('Power Bank 10000mAh')->assertSee('Out of Stock');
    }

    /** SEL-INV-005 */
    public function test_sel_inv_005_low_stock_alert_at_the_threshold(): void
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

    /** SEL-INV-006 (A17) */
    public function test_sel_inv_006_manual_stock_adjustment(): void
    {
        $set = fn ($v) => $this->actingAs($this->seller1)->from(route('seller.books.index'))->patch(route('seller.books.stock', $this->p02), ['stock' => $v]);
        $message = 'Stock must be a whole number from 0 to 99,999.';

        $set('-1')->assertSessionHasErrors(['stock' => $message]);
        $set('100000')->assertSessionHasErrors(['stock' => $message]);
        $set('2.5')->assertSessionHasErrors(['stock' => $message]);
        $set('99999')->assertSessionHasNoErrors();
        $this->assertSame(99999, $this->p02->fresh()->stock);
        $set('0')->assertSessionHasNoErrors();
        $this->assertSame(0, $this->p02->fresh()->stock);
        $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertSee('Sold out');
    }

    /** SEL-INV-007 */
    public function test_sel_inv_007_stock_below_the_quantity_in_carts(): void
    {
        $line = $this->cart($this->buyer1, $this->p02, 5);
        $this->actingAs($this->seller1)->patch(route('seller.books.stock', $this->p02), ['stock' => 3]);

        $this->assertSame('Only 3 left', $line->fresh()->unavailableReason());
        $this->actingAs($this->buyer1)->get(route('cart.index'))->assertSee('Only 3 left');
        $this->openCheckout($this->buyer1, [$line])->assertRedirect(route('cart.index'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Only 3 left'));
        $line->update(['quantity' => 3]);
        $this->openCheckout($this->buyer1, [$line])->assertOk();
    }

    // ── S6 Product Discounts & Sales ────────────────────────────

    /** SEL-DISC-001 */
    public function test_sel_disc_001_create_a_scheduled_sale(): void
    {
        $this->travelTo(now()->setTime(11, 0));
        $this->saveP02(['discount_percent' => 20, 'sale_starts_at' => now()->addMinutes(5)->format('Y-m-d\TH:i'),
            'sale_ends_at' => now()->addHours(2)->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();

        $p = $this->p02->fresh();
        $this->assertSame('Upcoming', $p->saleState());
        $this->assertEquals(450, $p->effective_price);
        $this->assertStringContainsString('₱450.00', $this->buyerPrice());
        $this->actingAs($this->seller1)->get(route('seller.books.index'))->assertSee('Sale: Upcoming');
    }

    /** SEL-DISC-002 */
    public function test_sel_disc_002_sale_goes_live_at_the_start_time(): void
    {
        $this->travelTo(now()->setTime(11, 0));
        $start = now()->addMinutes(5);
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
    }

    /** SEL-DISC-003 */
    public function test_sel_disc_003_sale_expires_at_the_end_time(): void
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
        $this->actingAs($this->buyer1)->get(route('cart.index'))->assertSee('₱450.00');
    }

    /** SEL-DISC-004 */
    public function test_sel_disc_004_invalid_sale_dates(): void
    {
        $t = now()->addDay()->setTime(12, 0);
        $this->saveP02(['discount_percent' => 20, 'sale_starts_at' => $t->format('Y-m-d\TH:i'), 'sale_ends_at' => $t->copy()->subHour()->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors(['sale_ends_at' => 'The sale must end after it starts.']);
        $this->saveP02(['discount_percent' => 20, 'sale_starts_at' => now()->subDay()->format('Y-m-d\TH:i'), 'sale_ends_at' => $t->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors(['sale_starts_at' => 'The sale start cannot be in the past.']);
        $this->saveP02(['discount_percent' => 20, 'sale_starts_at' => $t->format('Y-m-d\TH:i'), 'sale_ends_at' => $t->format('Y-m-d\TH:i')])
            ->assertSessionHasErrors(['sale_ends_at' => 'The sale must end after it starts.']);
        $this->assertEquals(0, $this->p02->fresh()->discount_percent);
        $this->assertNull($this->p02->fresh()->saleState());
    }

    /** SEL-DISC-005 */
    public function test_sel_disc_005_discount_percentage_limits(): void
    {
        foreach (['0', '100', '101', '-5'] as $pct) {
            $this->saveP02(['discount_percent' => $pct])->assertSessionHasErrors(['discount_percent' => 'The discount must be from 1% to 99%.']);
        }
        $this->saveP02(['discount_percent' => '1'])->assertSessionHasNoErrors();
        $this->assertEquals(445.5, $this->p02->fresh()->effective_price);
        $this->saveP02(['discount_percent' => '99'])->assertSessionHasNoErrors();
        $this->assertEquals(4.5, $this->p02->fresh()->effective_price);
        // An empty field means no discount (the form shows a blank, not 0).
        $this->saveP02(['discount_percent' => ''])->assertSessionHasNoErrors();
        $this->assertEquals(450, $this->p02->fresh()->effective_price);
    }

    /** SEL-DISC-006 */
    public function test_sel_disc_006_fixed_sale_price_must_be_lower(): void
    {
        foreach (['450.00', '500.00'] as $price) {
            $this->saveP02(['sale_price' => $price])->assertSessionHasErrors(['sale_price' => 'The sale price must be lower than the original price.']);
        }
        $this->saveP02(['sale_price' => '449.99'])->assertSessionHasNoErrors();
        $this->assertEquals(449.99, $this->p02->fresh()->effective_price);
    }

    /** SEL-DISC-007 */
    public function test_sel_disc_007_sale_on_a_product_with_variants(): void
    {
        $this->actingAs($this->seller1)->put(route('seller.books.update', $this->p01), $this->editForm($this->p01, ['discount_percent' => 10]))->assertSessionHasNoErrors();

        $html = $this->actingAs($this->buyer1)->get(route('products.show', $this->p01->slug))->getContent();
        foreach (['"price":179.1', '"price":224.1', '"price":269.1'] as $price) {
            $this->assertStringContainsString($price, $html);
        }
        $this->assertStringContainsString('₱179.10 – ₱269.10', $html);
        $this->assertEquals(224.10, $this->cart($this->buyer1, $this->p01, 1, 'Natural / M')->unit_price);
    }

    /** SEL-DISC-008 */
    public function test_sel_disc_008_edit_or_end_a_running_sale(): void
    {
        $this->saveP02(['discount_percent' => 20, 'sale_ends_at' => now()->addDay()->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $order = $this->placeOrder($this->buyer1, [$this->cart($this->buyer1, $this->p02)])->first();
        $this->assertEquals(360, OrderItem::where('order_id', $order->id)->value('price'));

        $this->saveP02(['discount_percent' => 30])->assertSessionHasNoErrors();
        $this->assertEquals(315, $this->p02->fresh()->effective_price);

        $this->actingAs($this->seller1)->patch(route('seller.books.end-sale', $this->p02))->assertSessionHas('success', fn ($m) => str_contains($m, '₱450.00'));
        $this->assertEquals(450, $this->p02->fresh()->effective_price);
        $this->assertSame('Ended', $this->p02->fresh()->saleState());
        $this->assertEquals(360, OrderItem::where('order_id', $order->id)->value('price'));
    }
}
