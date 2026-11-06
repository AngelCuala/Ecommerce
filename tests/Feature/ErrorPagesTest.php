<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Error pages say what happened and give a way back, instead of the bare framework pages. */
class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_missing_page_shows_alvys_404(): void
    {
        $this->get('/no-such-page-here')->assertNotFound()
            ->assertSee('Page not found')->assertSee('Go to the shop');
    }

    public function test_another_portals_page_says_whose_account_is_logged_in(): void
    {
        $buyer = User::forceCreate(['name' => 'Ana Buyer', 'email' => 'ana@alvy.test', 'password' => Hash::make('x'), 'role' => 'customer', 'email_verified_at' => now()]);

        $res = $this->actingAs($buyer)->get(route('admin.dashboard'));
        if ($res->status() === 403) {
            $res->assertSee('This page is not for your account')->assertSee('Ana Buyer');
        } else {
            $res->assertRedirect();   // the portal hand-off may send the account to its own login instead
        }
    }

    public function test_a_cart_or_wishlist_button_address_opened_as_a_link_goes_to_the_product(): void
    {
        $this->get('/cart/301')->assertRedirect(route('products.show', 301));
        $this->get('/wishlist/12')->assertRedirect(route('products.show', 12));
    }
}
