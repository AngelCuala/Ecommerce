<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceLoginTimeLimit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Logins end after 30 idle minutes, and after 8 hours however active the account is. */
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
        $this->assertSame(30, (int) config('session.lifetime'));
        $this->assertSame(480, (int) config('session.max_login_minutes'));
    }

    public function test_active_user_stays_signed_in_within_the_limits(): void
    {
        $user = $this->buyer();
        $this->login()->assertRedirect();
        $this->assertAuthenticatedAs($user);

        foreach (range(1, 6) as $i) {
            $this->travel(25)->minutes();   // never 30 idle minutes in a row
            $this->get(route('profile.orders'))->assertOk();
        }
        $this->assertAuthenticatedAs($user);
    }

    public function test_signed_out_after_30_idle_minutes(): void
    {
        $this->buyer();
        $this->login();
        $this->get(route('profile.orders'))->assertOk();

        $this->travel(31)->minutes();
        $this->get(route('profile.orders'))->assertRedirect(route('login'))
            ->assertSessionHas('error', 'You were signed out after 30 minutes of inactivity. Please log in again.');
        $this->assertGuest();
    }

    public function test_signed_out_after_8_hours_even_when_active_and_remember_me_does_not_bring_it_back(): void
    {
        $user = $this->buyer();
        $this->login(remember: true);
        $token = $user->fresh()->remember_token;

        foreach (range(1, 20) as $i) {   // active every 25 minutes for 8h20m
            $this->travel(25)->minutes();
            $response = $this->get(route('profile.orders'));
        }
        $response->assertRedirect(route('login'))->assertSessionHas('error', 'For your security you were signed out after 8 hours. Please log in again.');
        $this->assertGuest();
        $this->assertNotSame($token, $user->fresh()->remember_token);   // the old remember-me cookie no longer works
        $this->assertSame(480, config('auth.guards.web.remember'));   // the remember-me cookie lasts 8 hours at most
    }

    public function test_json_requests_get_401_and_the_login_page_shows_the_reason(): void
    {
        $this->buyer();
        $this->login();
        $this->travel(31)->minutes();
        $this->getJson(route('profile.orders'))->assertStatus(401)->assertJson(['message' => 'You were signed out after 30 minutes of inactivity. Please log in again.']);

        $this->login();
        $this->travel(31)->minutes();
        $this->followingRedirects()->get(route('profile.orders'))->assertSee('You were signed out after 30 minutes of inactivity.');
    }

    public function test_a_new_login_starts_a_new_8_hours(): void
    {
        $user = $this->buyer();
        $this->login();
        foreach (range(1, 19) as $i) {   // 7h55m of activity
            $this->travel(25)->minutes();
            $this->get(route('profile.orders'))->assertOk();
        }
        $this->post(route('logout'));
        $this->login();
        $this->assertSame(now()->getTimestamp(), session(EnforceLoginTimeLimit::STARTED));

        // The old login would be over 8 hours now; the new one is 25 minutes old.
        $this->travel(25)->minutes();
        $this->get(route('profile.orders'))->assertOk();
        $this->assertAuthenticatedAs($user);
    }
}
