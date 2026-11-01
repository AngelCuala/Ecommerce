<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** After signing out, the browser's Back button must not show a page of the signed-in account. */
class BackAfterLogoutTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        return User::create(['name' => ucfirst($role) . ' User', 'email' => $role . '@alvy.test', 'password' => Hash::make('Secret@123'),
            'role' => $role, 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    private function assertNotStored($response): void
    {
        $cc = (string) $response->headers->get('Cache-Control');
        $this->assertStringContainsString('no-store', $cc, 'Cache-Control was: ' . $cc);
    }

    public function test_every_page_shown_to_a_signed_in_user_is_not_stored_by_the_browser(): void
    {
        $buyer = $this->user('buyer');
        foreach (['/', route('shop.index'), route('categories.page'), route('profile.show'), route('profile.orders')] as $url) {
            $this->assertNotStored($this->actingAs($buyer)->get($url));
        }
        $this->assertNotStored($this->actingAs($this->user('admin'))->get(route('admin.dashboard')));
    }

    public function test_after_logout_the_account_pages_are_closed(): void
    {
        $buyer = $this->user('buyer');
        $this->post('/login', ['email' => $buyer->email, 'password' => 'Secret@123']);
        $this->assertAuthenticatedAs($buyer);

        $this->assertNotStored($this->post(route('logout')));
        $this->assertGuest();
        $this->get(route('profile.show'))->assertRedirect();
        $this->get('/')->assertOk()->assertDontSee('Buyer User');
    }

    public function test_sign_out_is_on_the_account_page_only(): void
    {
        $hasSignOut = fn ($response) => (bool) preg_match('#<form[^>]+action="[^"]*/logout"#', $response->getContent());

        $buyer = $this->user('buyer');
        $this->assertTrue($hasSignOut($this->actingAs($buyer)->get(route('profile.show'))->assertOk()));
        foreach (['/', route('shop.index'), route('profile.orders')] as $url) {
            $this->assertFalse($hasSignOut($this->actingAs($buyer)->get($url)), $url . ' still has a sign-out button');
        }
    }

    public function test_admin_has_no_account_page_and_signs_out_from_the_bottom_of_the_sidebar(): void
    {
        $admin = $this->user('admin');
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.account.edit'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.logistics.account.edit'));
        $this->actingAs($admin)->get('/admin/account')->assertNotFound();

        foreach ([route('admin.dashboard'), route('admin.logistics.dashboard')] as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('#<form[^>]+action="[^"]*/logout"#', $html, $url . ' has no sign-out');
            $this->assertStringNotContainsString('/admin/account', $html);
            // In every sidebar (desktop, and the mobile drawer), the sign-out comes after the menu: the very bottom.
            preg_match_all('#<aside\b.*?</aside>#s', $html, $sidebars);
            $this->assertNotEmpty($sidebars[0], $url);
            foreach ($sidebars[0] as $aside) {
                $this->assertGreaterThan(strrpos($aside, '</nav>'), strpos($aside, '/logout"'), $url);
            }
        }
    }

    public function test_guests_browsing_keep_normal_caching(): void
    {
        $cc = (string) $this->get('/')->headers->get('Cache-Control');
        $this->assertStringNotContainsString('no-store', $cc);
    }
}
