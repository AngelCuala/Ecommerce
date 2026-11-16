<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** An app login lasts a week, like a website login (not 30 idle minutes); then the app is told why it ended. */
class AppLoginTimeLimitTest extends TestCase
{
    use RefreshDatabase;

    private function login(): string
    {
        User::forceCreate(['name' => 'Juan', 'email' => 'juan@gmail.com', 'password' => Hash::make('Secret@123'),
            'role' => 'buyer', 'approval_status' => 'approved', 'email_verified_at' => now()]);

        return $this->postJson('/api/v1/auth/login', ['email' => 'juan@gmail.com', 'password' => 'Secret@123'])->assertOk()->json('token');
    }

    private function me(string $token)
    {
        $this->app['auth']->forgetGuards();
        return $this->withToken($token)->getJson('/api/v1/me');
    }

    public function test_the_app_login_lasts_a_week_then_says_why_it_ended(): void
    {
        $this->assertSame(10080, (int) config('sanctum.expiration'));
        $token = $this->login();

        // Opened in the morning, again in the afternoon, then every day: still signed in for the week.
        $this->travel(5)->hours();
        $this->me($token)->assertOk();
        foreach (range(1, 6) as $day) {
            $this->travel(1)->days();
            $this->me($token)->assertOk();
        }

        // A week after signing in: signed out, with the reason.
        $this->travel(1)->days();
        $this->me($token)->assertStatus(401)
            ->assertJsonPath('message', 'For your security you were signed out after a week. Please log in again.');
    }

    public function test_a_signed_out_or_unknown_token_gets_the_usual_answer(): void
    {
        $this->me('1|not-a-real-token')->assertStatus(401)->assertJsonPath('message', 'Unauthenticated.');
    }
}
