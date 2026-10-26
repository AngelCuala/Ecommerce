<?php

namespace Tests\Feature\Buyer;

use App\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\BuyerTestData;
use Tests\TestCase;

/** "<product> was added to your cart." (no IDs in the pop-up), stock checks, and no double adds. */
class AddToCartNoticeTest extends TestCase
{
    use RefreshDatabase, BuyerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBuyerTestData();
    }

    public function test_notice_names_the_real_product_id_not_the_cart_line_or_quantity(): void
    {
        // Make the cart line id, quantity and product id all different numbers.
        CartItem::create(['user_id' => $this->buyer2->id, 'product_id' => $this->p05->id, 'quantity' => 1]);
        CartItem::create(['user_id' => $this->buyer2->id, 'product_id' => $this->p06->id, 'quantity' => 1, 'variation_id' => $this->p06->variations()->value('id')]);

        $this->actingAs($this->buyer1)->from('/p')->post(route('cart.store', $this->p02->id), ['quantity' => 3])
            ->assertRedirect('/p')
            ->assertSessionHas('success', '"Rattan Fruit Basket" was added to your cart.')
            ->assertSessionHas('cart_added', $this->p02->id);

        $line = CartItem::where('user_id', $this->buyer1->id)->where('product_id', $this->p02->id)->first();
        $this->assertNotSame($line->id, $this->p02->id);
        $this->assertSame(3, $line->quantity);

        // The toast on the next page shows it, with a link to the cart, and the cart badge counts it.
        $this->actingAs($this->buyer1)->withSession(['success' => '"Rattan Fruit Basket" was added to your cart.', 'cart_added' => $this->p02->id])
            ->get(route('home'))->assertOk()
            ->assertSee('was added to your cart.')->assertDontSee('Product ID')
            ->assertSee('data-cart-added="' . $this->p02->id . '"', false);
    }

    public function test_out_of_stock_and_too_many_are_refused_with_no_success_notice(): void
    {
        $this->actingAs($this->buyer1)->from('/p')->post(route('cart.store', $this->p04->id))   // stock 0
            ->assertSessionHas('error', 'This item is out of stock.')->assertSessionMissing('success');

        $this->actingAs($this->buyer1)->from('/p')->post(route('cart.store', $this->p03->id), ['quantity' => 2])   // 1 left
            ->assertSessionHas('error', 'Only 1 left in stock.')->assertSessionMissing('cart_added');
        $this->assertSame(0, CartItem::where('user_id', $this->buyer1->id)->count());
    }

    public function test_the_same_form_sent_twice_adds_once(): void
    {
        $form = ['quantity' => 2, 'cart_token' => (string) Str::uuid()];
        $this->actingAs($this->buyer1)->from('/p')->post(route('cart.store', $this->p02->id), $form)->assertSessionHas('success');
        $this->actingAs($this->buyer1)->from('/p')->post(route('cart.store', $this->p02->id), $form)->assertSessionHas('success');
        $this->assertSame(2, (int) CartItem::where('user_id', $this->buyer1->id)->sum('quantity'));

        // A fresh form (a deliberate second add) still adds.
        $this->actingAs($this->buyer1)->from('/p')->post(route('cart.store', $this->p02->id), ['quantity' => 1, 'cart_token' => (string) Str::uuid()]);
        $this->assertSame(3, (int) CartItem::where('user_id', $this->buyer1->id)->sum('quantity'));
    }

    public function test_a_refused_form_can_be_corrected_and_sent_again(): void
    {
        $token = (string) Str::uuid();
        $this->actingAs($this->buyer1)->from('/p')->post(route('cart.store', $this->p03->id), ['quantity' => 5, 'cart_token' => $token])->assertSessionHas('error');
        $this->actingAs($this->buyer1)->from('/p')->post(route('cart.store', $this->p03->id), ['quantity' => 1, 'cart_token' => $token])
            ->assertSessionHas('success', '"Capiz Shell Lamp" was added to your cart.');
        $this->assertSame(1, (int) CartItem::where('user_id', $this->buyer1->id)->sum('quantity'));
    }

    public function test_cart_badge_counts_products_by_id_not_quantity(): void
    {
        // 3 baskets, the same basket again, and 2 options of the tote bag: 2 different products.
        $this->actingAs($this->buyer1)->from('/p')->post(route('cart.store', $this->p02->id), ['quantity' => 3]);
        $this->actingAs($this->buyer1)->from('/p')->post(route('cart.store', $this->p02->id), ['quantity' => 1]);
        foreach ($this->p01->variations()->where('stock', '>', 0)->take(2)->pluck('id') as $variation) {
            $this->actingAs($this->buyer1)->from('/p')->post(route('cart.store', $this->p01->id), ['quantity' => 2, 'variation_id' => $variation]);
        }
        $this->assertSame(8, (int) CartItem::where('user_id', $this->buyer1->id)->sum('quantity'));

        $html = $this->actingAs($this->buyer1)->get(route('home'))->getContent();
        $this->assertMatchesRegularExpression('/style="background:#fff;color:#fa4e1c;">2<\/span>/', $html);

        // The Check out button counts product IDs too (both tote options share one ID).
        $cart = $this->actingAs($this->buyer1)->get(route('cart.index'))->getContent();
        $this->assertSame(3, substr_count($cart, 'data-product="'));
        $this->assertSame(2, substr_count($cart, 'data-product="' . $this->p01->id . '"'));
    }

    public function test_pages_render_a_one_time_token_on_every_add_to_cart_form(): void
    {
        $html = $this->actingAs($this->buyer1)->get(route('products.show', $this->p02->slug))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/name="cart_token" value="[0-9a-f-]{36}"/', $html);
        $this->assertStringContainsString('dataset.sending', $this->get(route('shop.index'))->getContent());
    }
}
