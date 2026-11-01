<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceLoginTimeLimit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Logins end after a week without using the site, and a week after signing in however active the account is. */
class LoginTimeLimitTest extends TestCase
{
    use RefreshDatabase;

    private function buyer(): User
    {
        return User::forceCreate(['name' => 'Juan', 'email' => 'juan@gmail.com', 'password' => Hash::make('secret123'),
            'role' => 'buyer', 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    private function login(bool $remember = false)
    {
        return $this->post('/login', ['email' => 'juan@gmail.com', 'password' => 'secret123'] + ($remember ? ['remember' => '1'] : []));
    }

    public function test_limits_are_configured(): void
    {
        $this->assertSame(10080, (int) config('session.lifetime'));
        $this->assertSame(10080, (int) config('session.max_login_minutes'));
    }

    public function test_active_user_stays_signed_in_within_the_limits(): void
    {
        $user = $this->buyer();
        $this->login()->assertRedirect();
        $this->assertAuthenticatedAs($user);

        foreach (range(1, 6) as $i) {
            $this->travel(1)->days();   // a morning login still works in the afternoon, and for days after
            $this->get(route('profile.orders'))->assertOk();
        }
        $this->assertAuthenticatedAs($user);
    }

    public function test_signed_out_after_a_week_without_using_the_site(): void
    {
        $this->buyer();
        $this->login();
        $this->get(route('profile.orders'))->assertOk();

        $this->travel(7)->days();
        $this->travel(1)->minutes();
        $this->get(route('profile.orders'))->assertRedirect(route('login'))
            ->assertSessionHas('error', 'For your security you were signed out after a week. Please log in again.');
        $this->assertGuest();
    }

    public function test_signed_out_after_a_week_even_when_active_and_remember_me_does_not_bring_it_back(): void
    {
        $user = $this->buyer();
        $this->login(remember: true);
        $token = $user->fresh()->remember_token;

        foreach (range(1, 7) as $i) {   // active every day for a week
            $this->travel(1)->days();
            $response = $this->get(route('profile.orders'));
        }
        $response->assertRedirect(route('login'))->assertSessionHas('error', 'For your security you were signed out after a week. Please log in again.');
        $this->assertGuest();
        $this->assertNotSame($token, $user->fresh()->remember_token);   // the old remember-me cookie no longer works
        $this->assertSame(10080, config('auth.guards.web.remember'));   // the remember-me cookie lasts a week at most
    }

    public function test_json_requests_get_401_and_the_login_page_shows_the_reason(): void
    {
        $this->buyer();
        $this->login();
        $this->travel(8)->days();
        $this->getJson(route('profile.orders'))->assertStatus(401)->assertJson(['message' => 'For your security you were signed out after a week. Please log in again.']);

        $this->login();
        $this->travel(8)->days();
        $this->followingRedirects()->get(route('profile.orders'))->assertSee('For your security you were signed out after a week.');
    }

    public function test_a_new_login_starts_a_new_week(): void
    {
        $user = $this->buyer();
        $this->login();
        foreach (range(1, 6) as $i) {   // 6 days of activity
            $this->travel(1)->days();
            $this->get(route('profile.orders'))->assertOk();
        }
        $this->post(route('logout'));
        $this->login();
        $this->assertSame(now()->getTimestamp(), session(EnforceLoginTimeLimit::STARTED));

        // The old login would be over a week old now; the new one is 2 days old.
        $this->travel(2)->days();
        $this->get(route('profile.orders'))->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_login_page_asks_before_signing_out_an_account_that_is_still_logged_in(): void
    {
        $user = $this->buyer();
        $this->login();

        // e.g. the browser's Back button right after logging in: still signed in, and asked first.
        $this->get('/login')->assertOk()->assertSee('Do you want to log out?')->assertSee('No, stay logged in')
            ->assertSee('Yes, log out')->assertSee($user->email);
        $this->assertAuthenticatedAs($user);

        $this->post(route('logout'), ['next' => 'login'])->assertRedirect(route('login'));
        $this->assertGuest();
        $this->get('/login')->assertOk()->assertSee('Log In');
    }
}
