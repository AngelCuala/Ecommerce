<?php

namespace Tests\Feature\Buyer;

use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuyerTestData;
use Tests\TestCase;

/** The − / + on the checkout page: saves the quantity (cart line or Buy now) and answers with the new totals, no reload. */
class CheckoutQuantityTest extends TestCase
{
    use RefreshDatabase, BuyerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBuyerTestData();
    }

    private function setQty(string $line, int $qty, bool $buyNow = false)
    {
        return $this->actingAs($this->buyer1)->postJson(route('checkout.quantity'), array_filter([
            'line' => $line, 'quantity' => $qty, 'address_id' => $this->addr1->id, 'buy_now' => $buyNow ? 1 : null,
        ]));
    }

    public function test_checkout_shows_the_quantity_buttons_for_each_line(): void
    {
        $basket = $this->cart($this->buyer1, $this->p02);

        $this->actingAs($this->buyer1)->get(route('checkout.index', ['items' => [$basket->id]]))->assertOk()
            ->assertSee('data-qty-line="' . $basket->id . '"', false)
            ->assertSee('data-quantity-url="' . route('checkout.quantity') . '"', false);
    }

    public function test_plus_saves_the_line_and_returns_the_new_totals(): void
    {
        $basket = $this->cart($this->buyer1, $this->p02);
        $charger = $this->cart($this->buyer1, $this->p05);
        $this->actingAs($this->buyer1)->get(route('checkout.index', ['items' => [$basket->id]]))->assertOk();   // only the basket ticked

        $before = $this->setQty((string) $basket->id, 1)->assertOk()->json();
        $after = $this->setQty((string) $basket->id, 2)->assertOk()->json();

        $this->assertSame(2, $basket->fresh()->quantity);
        $this->assertSame(1, $charger->fresh()->quantity);
        $this->assertCount(1, $after['lines']);                       // still only the ticked item
        $this->assertSame(2, $after['lines'][0]['quantity']);
        $this->assertEqualsWithDelta($before['lines'][0]['subtotal'] * 2, $after['lines'][0]['subtotal'], 0.01);
        $this->assertGreaterThan($before['total'], $after['total']);
        $this->assertArrayHasKey((string) $basket->id, $after['prices']);
    }

    public function test_more_than_the_stock_is_refused(): void
    {
        $basket = $this->cart($this->buyer1, $this->p02);
        $stock = $basket->fresh()->availableStock();
        $this->actingAs($this->buyer1)->get(route('checkout.index', ['items' => [$basket->id]]))->assertOk();

        $this->setQty((string) $basket->id, $stock + 1)->assertStatus(422)->assertJsonPath('message', 'Only ' . $stock . ' left in stock.');
        $this->assertSame(1, $basket->fresh()->quantity);
    }

    public function test_another_buyers_line_cannot_be_changed(): void
    {
        $theirs = $this->cart($this->buyer2, $this->p02);

        $this->setQty((string) $theirs->id, 2)->assertStatus(422);
        $this->assertSame(1, $theirs->fresh()->quantity);
    }

    public function test_buy_now_quantity_changes_without_touching_the_cart(): void
    {
        $this->actingAs($this->buyer1)->post(route('cart.store', $this->p02->id), ['action' => 'buy_now', 'quantity' => 1])
            ->assertRedirect(route('checkout.index', ['buy_now' => 1]));
        $this->actingAs($this->buyer1)->get(route('checkout.index', ['buy_now' => 1]))->assertOk()
            ->assertSee('data-qty-line="buy"', false);

        $res = $this->setQty('buy', 3, buyNow: true)->assertOk()->json();

        $this->assertSame(3, session('buy_now')['quantity']);
        $this->assertSame(3, $res['lines'][0]['quantity']);
        $this->assertSame('buy', $res['lines'][0]['key']);
        $this->assertSame(0, CartItem::count());
    }
}
