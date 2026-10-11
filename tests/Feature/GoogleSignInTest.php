<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

/** "Continue with Google" on the login page (Socialite is mocked; no calls to Google). */
class GoogleSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'test-id', 'services.google.client_secret' => 'test-secret']);
    }

    private function user(string $role, string $status, string $email): User
    {
        return User::create([
            'name' => ucfirst($role), 'email' => $email, 'password' => Hash::make('Secret@123'),
            'role' => $role, 'approval_status' => $status, 'email_verified_at' => now(),
        ]);
    }

    private function fakeGoogle(string $email, string $id = 'g-123', array $raw = []): void
    {
        $google = (new GoogleUser())->setRaw($raw + ['given_name' => 'Juan', 'family_name' => 'Dela Cruz'])
            ->map(['id' => $id, 'email' => $email, 'name' => 'Juan Dela Cruz']);

        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_login_page_offers_google_and_email(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('Continue with Google')
            ->assertSee('or log in with email')
            ->assertSee(route('auth.google'), false);
    }

    public function test_google_button_explains_when_not_configured(): void
    {
        config(['services.google.client_id' => null]);

        $this->get(route('auth.google'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_existing_buyer_is_signed_in_and_linked(): void
    {
        $buyer = $this->user('buyer', 'approved', 'juan@example.com');
        $this->fakeGoogle('Juan@Example.com');

        $this->get(route('auth.google.callback'))->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($buyer);
        $this->assertSame('g-123', $buyer->fresh()->google_id);
    }

    public function test_seller_goes_to_seller_dashboard(): void
    {
        $seller = $this->user('seller', 'approved', 'shop@example.com');
        $this->fakeGoogle('shop@example.com');

        $this->get(route('auth.google.callback'))->assertRedirect(route('seller.dashboard'));
        $this->assertAuthenticatedAs($seller);
    }

    public function test_pending_account_is_blocked_with_a_message_on_the_login_page(): void
    {
        $this->user('buyer', 'pending', 'wait@example.com');
        $this->fakeGoogle('wait@example.com');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_unknown_email_is_sent_to_sign_up_with_details_filled_in(): void
    {
        $this->fakeGoogle('new.person@example.com');

        $this->get(route('auth.google.callback'))
            ->assertRedirect(route('register'))
            ->assertSessionHas('info')
            ->assertSessionHasInput('email', 'new.person@example.com')
            ->assertSessionHasInput('first_name', 'Juan');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'new.person@example.com']);
    }
}
