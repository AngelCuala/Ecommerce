<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** "Buy now" goes straight to checkout with just that item; "Add to cart" still adds to the cart. */
class BuyNowTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private Product $lamp;
    private Product $mug;

    protected function setUp(): void
    {
        parent::setUp();
        $seller = User::create(['name' => 'Seller', 'email' => 's@alvy.test', 'password' => Hash::make('Secret@123'),
            'role' => 'seller', 'approval_status' => 'approved', 'email_verified_at' => now()]);
        $this->buyer = User::create(['name' => 'Buyer', 'email' => 'b@alvy.test', 'password' => Hash::make('Secret@123'),
            'role' => 'buyer', 'approval_status' => 'approved', 'email_verified_at' => now()]);
        $cat = Category::create(['name' => 'Home']);
        $this->lamp = Product::create(['category_id' => $cat->id, 'seller_id' => $seller->id, 'title' => 'Desk Lamp', 'price' => 800, 'stock' => 3, 'status' => 'active']);
        $this->mug  = Product::create(['category_id' => $cat->id, 'seller_id' => $seller->id, 'title' => 'Coffee Mug', 'price' => 150, 'stock' => 10, 'status' => 'active']);

        // Something already waiting in the cart.
        CartItem::create(['user_id' => $this->buyer->id, 'product_id' => $this->mug->id, 'quantity' => 2]);
    }

    private function order(array $extra = [])
    {
        return $this->actingAs($this->buyer)->from(route('checkout.index', ['buy_now' => 1]))->post(route('checkout.store'), $extra + [
            'buy_now' => '1', 'full_name' => 'Buyer', 'phone' => '09170000000', 'email' => 'b@alvy.test', 'region' => 'NCR',
            'province' => 'Metro Manila', 'city' => 'Quezon City', 'barangay' => 'Commonwealth', 'zip_code' => '1121', 'payment_method' => 'cod',
        ]);
    }

    public function test_buy_now_checks_out_only_that_item_and_keeps_the_cart(): void
    {
        $this->actingAs($this->buyer)->post(route('cart.store', $this->lamp->id), ['quantity' => 2, 'action' => 'buy_now'])
            ->assertRedirect(route('checkout.index', ['buy_now' => 1]));

        $this->actingAs($this->buyer)->get(route('checkout.index', ['buy_now' => 1]))->assertOk()
            ->assertSee('Desk Lamp')->assertDontSee('Coffee Mug')
            ->assertSee('₱1,650.00');                         // 2 × 800 + ₱50 delivery
        $this->assertSame(1, CartItem::count());              // the lamp was not put in the cart

        $this->order()->assertSessionHasNoErrors()->assertRedirect(route('checkout.confirmation'));

        $order = Order::firstOrFail();
        $this->assertSame(1, $order->items()->count());
        $this->assertSame($this->lamp->id, $order->items()->value('product_id'));
        $this->assertEquals(1650, $order->total_price);
        $this->assertSame(1, $this->lamp->fresh()->stock);
        // The mug is still in the cart, and the Buy now item is done.
        $this->assertSame(2, CartItem::where('product_id', $this->mug->id)->value('quantity'));
        $this->assertNull(session('buy_now'));
    }

    public function test_add_to_cart_still_adds_to_the_cart(): void
    {
        $this->actingAs($this->buyer)->from('/products/desk-lamp-' . $this->lamp->id)
            ->post(route('cart.store', $this->lamp->id), ['quantity' => 1, 'action' => 'cart'])
            ->assertRedirect('/products/desk-lamp-' . $this->lamp->id)
            ->assertSessionHas('success');
        $this->assertSame(2, CartItem::count());
    }

    public function test_normal_checkout_uses_the_cart_again(): void
    {
        $this->actingAs($this->buyer)->post(route('cart.store', $this->lamp->id), ['quantity' => 1, 'action' => 'buy_now']);

        $this->actingAs($this->buyer)->get(route('checkout.index'))->assertOk()
            ->assertSee('Coffee Mug')->assertDontSee('Desk Lamp');
        $this->assertNull(session('buy_now'));
    }

    public function test_buy_now_respects_stock_and_works_with_vouchers(): void
    {
        $this->actingAs($this->buyer)->from('/products/x')->post(route('cart.store', $this->lamp->id), ['quantity' => 5, 'action' => 'buy_now'])
            ->assertRedirect('/products/x')->assertSessionHas('error', 'Only 3 left in stock.');

        Voucher::create(['code' => 'ALVY10', 'name' => '10% off', 'scope' => 'platform', 'discount_type' => 'percent',
            'discount_value' => 10, 'max_discount' => 150, 'min_spend' => 0, 'per_user_limit' => 1, 'is_active' => true]);

        $this->actingAs($this->buyer)->post(route('cart.store', $this->lamp->id), ['quantity' => 1, 'action' => 'buy_now']);
        $this->actingAs($this->buyer)->get(route('checkout.index', ['buy_now' => 1]))->assertSee('ALVY10');
        $this->order(['platform_voucher' => 'ALVY10'])->assertSessionHasNoErrors();

        $this->assertEquals(80, Order::firstOrFail()->platform_discount);   // 10% of ₱800
    }
}
