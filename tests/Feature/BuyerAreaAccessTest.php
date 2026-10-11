<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** The storefront and buyer account pages are for guests and buyers only. */
class BuyerAreaAccessTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, string $status = 'approved'): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => "{$role}.{$status}@alvy.test", 'password' => Hash::make('Secret@123'),
            'role' => $role, 'approval_status' => $status, 'email_verified_at' => now(),
        ]);
    }

    /** Every buyer-side page, as GET URLs. */
    private function buyerPages(): array
    {
        $seller  = $this->user('seller', 'approved');
        $cat     = Category::create(['name' => 'Pet Supplies']);
        $product = Product::create([
            'category_id' => $cat->id, 'seller_id' => $seller->id, 'title' => 'Dog Food',
            'price' => 100, 'stock' => 5, 'status' => 'active',
        ]);

        return [
            '/', '/shop?search=dog', '/categories', '/categories/pet-supplies', '/products/' . $product->slug,
            '/cart', '/checkout', '/profile', '/profile/orders', '/profile/addresses', '/profile/security',
            '/profile/settings', '/profile/notifications', '/become-seller',
        ];
    }

    public function test_approved_seller_is_sent_to_the_seller_dashboard_from_every_buyer_page(): void
    {
        $pages = $this->buyerPages();
        $this->actingAs(User::where('role', 'seller')->first());

        foreach ($pages as $url) {
            $this->get($url)->assertRedirect(route('seller.dashboard'));
        }
        $this->post(route('cart.store', 1))->assertRedirect(route('seller.dashboard'));
        $this->post(route('checkout.store'))->assertRedirect(route('seller.dashboard'));
        $this->post(route('orders.cancel', 1))->assertRedirect(route('seller.dashboard'));
        $this->post(route('orders.confirm-delivery', 1))->assertRedirect(route('seller.dashboard'));
    }

    public function test_pending_seller_is_sent_to_the_application_status_page(): void
    {
        $pages = $this->buyerPages();
        $this->actingAs($this->user('seller', 'pending'));

        foreach (['/', '/shop?search=dog', '/cart', '/profile'] as $url) {
            $this->get($url)->assertRedirect(route('seller.status'));
        }
        // The status page itself has no storefront links.
        $this->get(route('seller.status'))->assertOk()
            ->assertDontSee('Back to shop')
            ->assertDontSee(route('cart.index'), false);
    }

    public function test_admin_is_sent_to_the_admin_dashboard_from_every_buyer_page(): void
    {
        $pages = $this->buyerPages();
        $this->actingAs($this->user('admin'));

        foreach ($pages as $url) {
            $this->get($url)->assertRedirect(route('admin.dashboard'));
        }
        $this->post(route('orders.cancel', 1))->assertRedirect(route('admin.dashboard'));
    }

    public function test_guests_and_buyers_can_still_shop(): void
    {
        $pages = $this->buyerPages();

        foreach (['/', '/categories/pet-supplies', $pages[4]] as $url) {
            $this->get($url)->assertOk();
        }

        $this->actingAs($this->user('buyer'));
        foreach (['/', '/categories/pet-supplies', $pages[4], '/cart', '/profile', '/become-seller'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_logging_in_sends_each_role_to_its_own_area(): void
    {
        $this->user('seller');
        $this->user('admin');

        $this->post('/login', ['email' => 'seller.approved@alvy.test', 'password' => 'Secret@123'])
            ->assertRedirect(route('seller.dashboard'));
        $this->post('/logout');

        // The main login opens the admin panel, not the logistics / sorting panel.
        $this->post('/login', ['email' => 'admin.approved@alvy.test', 'password' => 'Secret@123'])
            ->assertRedirect(route('admin.dashboard'));
        $this->post('/logout');

        // The logistics panel is reached through its own login page.
        $this->post(route('logistics.login.store'), ['email' => 'admin.approved@alvy.test', 'password' => 'Secret@123'])
            ->assertRedirect(route('admin.logistics.dashboard'));
    }
}
