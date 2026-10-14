<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Important actions by anyone are logged with who, what, the result and the IP — never passwords. */
class ActivityLogTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\ChecksOut;

    private User $admin;
    private User $seller;
    private User $buyer;
    private Product $lamp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin  = $this->user('admin', 'admin@alvy.test', 'Admin Ana');
        $this->seller = $this->user('seller', 'seller@alvy.test', 'Seller Sam');
        $this->buyer  = $this->user('buyer', 'buyer@alvy.test', 'Buyer Ben');
        $cat = Category::create(['name' => 'Home']);
        $this->lamp = Product::create(['category_id' => $cat->id, 'seller_id' => $this->seller->id, 'title' => 'Desk Lamp',
            'price' => 800, 'stock' => 5, 'status' => 'active']);
    }

    private function user(string $role, string $email, string $name): User
    {
        return User::create(['name' => $name, 'email' => $email, 'password' => Hash::make('Secret@123'),
            'role' => $role, 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    public function test_a_buyers_order_is_logged_with_details_and_ip(): void
    {
        CartItem::create(['user_id' => $this->buyer->id, 'product_id' => $this->lamp->id, 'quantity' => 2]);

        $this->actingAs($this->buyer)->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->post(route('checkout.store'), $this->checkoutForm($this->addressFor($this->buyer)))->assertSessionHasNoErrors();

        $log = ActivityLog::where('action', 'checkout.store')->sole();
        $this->assertSame('Buyer Ben', $log->admin_name);
        $this->assertSame('buyer', $log->user_role);
        $this->assertSame('Placed an order', $log->action_label);
        $this->assertStringContainsString('Desk Lamp ×2', $log->description);
        $this->assertStringContainsString('total ₱1,650.00', $log->description);
        $this->assertSame('success', $log->status);
        $this->assertSame('203.0.113.7', $log->ip_address);
        $this->assertSame('Order', $log->subject_type);
    }

    public function test_seller_stock_change_and_cart_are_logged(): void
    {
        $this->actingAs($this->seller)->patch(route('seller.books.stock', $this->lamp), ['stock' => 20]);
        $this->assertSame('"Desk Lamp" stock 5 → 20', ActivityLog::where('action', 'seller.books.stock')->sole()->description);

        $this->actingAs($this->buyer)->post(route('cart.store', $this->lamp->id), ['quantity' => 3]);
        $log = ActivityLog::where('action', 'cart.store')->sole();
        $this->assertSame('Added to cart', $log->action_label);
        $this->assertSame('"Desk Lamp" ×3 (now 3 in cart)', $log->description);
    }

    public function test_failed_sign_in_is_logged_without_the_password_and_page_views_are_not(): void
    {
        $this->get('/login');
        $this->get(route('home'));
        $this->assertSame(0, ActivityLog::count());

        $this->post('/login', ['email' => 'buyer@alvy.test', 'password' => 'wrong-password'])->assertSessionHasErrors();

        $log = ActivityLog::sole();
        $this->assertSame('login.attempt', $log->action);
        $this->assertSame('failed', $log->status);
        $this->assertSame('guest', $log->user_role);
        $this->assertStringContainsString('buyer@alvy.test', $log->description);
        $this->assertStringNotContainsString('wrong-password', json_encode(ActivityLog::all()->toArray()));
    }

    public function test_cloudflare_visitor_ip_is_used(): void
    {
        $this->actingAs($this->buyer)
            ->withHeader('CF-Connecting-IP', '198.51.100.23')
            ->post(route('cart.store', $this->lamp->id), ['quantity' => 1]);

        $this->assertSame('198.51.100.23', ActivityLog::sole()->ip_address);
    }

    public function test_explicitly_logged_actions_are_not_logged_twice(): void
    {
        $this->post('/login', ['email' => 'admin@alvy.test', 'password' => 'Secret@123']);

        $this->assertSame(1, ActivityLog::count());                  // the existing "admin_login" row only
        $this->assertSame('admin_login', ActivityLog::sole()->action);
        $this->assertSame('admin', ActivityLog::sole()->user_role);
    }

    public function test_admin_sees_and_filters_everyones_activity(): void
    {
        $this->actingAs($this->buyer)->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->post(route('cart.store', $this->lamp->id), ['quantity' => 1]);
        $this->actingAs($this->seller)->withServerVariables(['REMOTE_ADDR' => '192.0.2.44'])
            ->patch(route('seller.books.stock', $this->lamp), ['stock' => 9]);

        $this->actingAs($this->admin)->get(route('admin.activity.index'))->assertOk()
            ->assertSee('Buyer Ben')->assertSee('Seller Sam')->assertSee('203.0.113.7')->assertSee('Updated stock');
        $this->actingAs($this->admin)->get(route('admin.activity.index', ['role' => 'seller']))->assertOk()
            ->assertSee('Seller Sam')->assertDontSee('Buyer Ben');
        $this->actingAs($this->admin)->get(route('admin.activity.index', ['search' => '203.0.113.7']))->assertOk()
            ->assertSee('Buyer Ben')->assertDontSee('Seller Sam');

        $this->actingAs($this->seller)->get(route('admin.activity.index'))->assertForbidden();
    }
}
