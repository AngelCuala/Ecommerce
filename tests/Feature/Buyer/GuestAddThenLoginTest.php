<?php

namespace Tests\Feature\Buyer;

use App\Models\CartItem;
use App\Models\WishlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuyerTestData;
use Tests\TestCase;

/** Signed out, a shopper presses Add to cart / Buy now / the heart: after logging in it is done, and they're back on the product. */
class GuestAddThenLoginTest extends TestCase
{
    use RefreshDatabase, BuyerTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedBuyerTestData();
    }

    private function logIn()
    {
        return $this->post('/login', ['email' => 'juanmiguel.delacruz@example.com', 'password' => 'Mamimili@2026']);
    }

    public function test_add_to_cart_while_signed_out_lands_in_the_cart_after_logging_in(): void
    {
        $this->get(route('products.show', $this->p02->slug))->assertOk()->assertSee(route('cart.store', $this->p02->id), false);   // browsing works signed out, with the Add to cart form

        $this->post(route('cart.store', $this->p02->id), ['quantity' => 2])
            ->assertRedirect(\App\Support\Portal::loginUrl())
            ->assertSessionHas('success', 'Log in or sign up to add "' . $this->p02->title . '" to your cart. We\'ll add it right after.');
        $this->assertSame(0, CartItem::count());

        $this->logIn()->assertRedirect(route('products.show', $this->p02->slug))
            ->assertSessionHas('success', fn ($m) => str_starts_with($m, 'Welcome back, Juan Miguel Dela Cruz!') && str_contains($m, $this->p02->title));
        $line = CartItem::where('user_id', $this->buyer1->id)->sole();
        $this->assertSame([$this->p02->id, 2], [$line->product_id, (int) $line->quantity]);

        // Only once: logging in again later adds nothing more.
        $this->post(route('logout'));
        $this->logIn()->assertRedirect(route('home'));
        $this->assertSame(2, (int) CartItem::where('user_id', $this->buyer1->id)->sole()->quantity);
    }

    public function test_the_wishlist_heart_while_signed_out_lands_in_the_wishlist(): void
    {
        $this->post(route('wishlist.toggle', $this->p02->id), ['state' => 'on'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'to your wishlist'));

        $this->logIn()->assertRedirect(route('products.show', $this->p02->slug))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '"' . $this->p02->title . '" was added to your wishlist.'));
        $this->assertTrue(WishlistItem::where('user_id', $this->buyer1->id)->where('product_id', $this->p02->id)->exists());
    }

    public function test_buy_now_while_signed_out_goes_to_checkout_after_logging_in(): void
    {
        $this->post(route('cart.store', $this->p02->id), ['quantity' => 1, 'action' => 'buy_now']);

        $this->logIn()->assertRedirect(route('checkout.index', ['buy_now' => 1]));
        $this->assertSame($this->p02->id, session('buy_now')['product_id']);
        $this->assertSame(0, CartItem::count());   // Buy now leaves the cart alone
    }

    public function test_a_problem_is_shown_and_nothing_is_added(): void
    {
        $this->p02->update(['stock' => 0]);
        $this->post(route('cart.store', $this->p02->id));

        $this->logIn()->assertRedirect(route('products.show', $this->p02->slug))->assertSessionHas('error');
        $this->assertSame(0, CartItem::count());
    }
}
