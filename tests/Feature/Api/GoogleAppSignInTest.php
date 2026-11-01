<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

/** "Continue with Google" in the app: the website's Google sign-in returns a one-time code to the app. */
class GoogleAppSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google.client_id' => 'test-id', 'services.google.client_secret' => 'test-secret']);
        Storage::fake('public');
        Storage::fake('local');
        Mail::fake();
    }

    private function fakeGoogle(string $email, string $id = 'g-777'): void
    {
        $google = (new GoogleUser())->setRaw(['given_name' => 'Ana', 'family_name' => 'Reyes'])
            ->map(['id' => $id, 'email' => $email, 'name' => 'Ana Reyes']);
        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')->andReturn($google);
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    /** Starts at /auth/google?app=1 and follows Google back; returns the code sent to the app. */
    private function codeFor(string $email): string
    {
        $this->fakeGoogle($email);
        $this->get('/auth/google?app=1')->assertRedirect();
        $res = $this->get(route('auth.google.callback'));
        $location = $res->headers->get('Location');
        $this->assertStringStartsWith('alvyshop://google-auth?code=', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $q);
        return $q['code'];
    }

    public function test_an_existing_account_signs_in_with_the_code_once(): void
    {
        $u = User::create(['name' => 'Ana Reyes', 'email' => 'ana.reyes@gmail.com', 'password' => Hash::make('Secret@123'), 'role' => 'buyer',
            'approval_status' => 'approved', 'email_verified_at' => now()]);
        $code = $this->codeFor('ana.reyes@gmail.com');

        $this->postJson('/api/v1/auth/google/exchange', ['code' => $code])->assertOk()
            ->assertJsonPath('status', 'ok')->assertJsonPath('user.email', 'ana.reyes@gmail.com')->assertJsonStructure(['token']);
        $this->assertSame('g-777', $u->fresh()->google_id);
        // The code works once.
        $this->postJson('/api/v1/auth/google/exchange', ['code' => $code])->assertStatus(422);
    }

    public function test_a_new_google_email_signs_up_without_the_email_code(): void
    {
        $code = $this->codeFor('new.ana@gmail.com');
        $this->postJson('/api/v1/auth/google/exchange', ['code' => $code])->assertOk()
            ->assertJsonPath('status', 'signup')->assertJsonPath('email', 'new.ana@gmail.com')->assertJsonPath('first_name', 'Ana');

        $this->post('/api/v1/auth/register', [
            'google_code' => $code, 'role' => 'buyer', 'first_name' => 'Ana', 'last_name' => 'Reyes',
            'email' => 'typed.other@example.com', 'password' => 'Secret@123', 'password_confirmation' => 'Secret@123',
            'sex' => 'female', 'contact_no' => '09171234567', 'birthday' => '2000-01-15',
            'region' => 'CALABARZON', 'province' => 'Laguna', 'province_code' => '043400000', 'municipality' => 'Magdalena',
            'municipality_code' => '043415000', 'barangay' => 'Poblacion', 'house_number' => '12B', 'street' => 'Rizal Street', 'terms' => '1',
            'valid_id' => UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('status', 'ok')->assertJsonStructure(['token']);

        $user = User::where('email', 'new.ana@gmail.com')->first();
        $this->assertNotNull($user?->email_verified_at);
        $this->assertSame('g-777', $user->google_id);
        $this->assertFalse(User::where('email', 'typed.other@example.com')->exists());
    }

    public function test_a_cancelled_google_sign_in_goes_back_to_the_app_with_the_reason(): void
    {
        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        $provider->shouldReceive('user')->andThrow(new \RuntimeException('denied'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google?app=1');
        $location = $this->get(route('auth.google.callback'))->headers->get('Location');
        $this->assertStringStartsWith('alvyshop://google-auth?error=', $location);
        // The website's own Google sign-in is unchanged.
        $this->get('/auth/google');
        $this->get(route('auth.google.callback'))->assertRedirect(route('login'));
    }

    public function test_policies_have_the_websites_text(): void
    {
        $p = $this->getJson('/api/v1/policies/privacy_policy')->assertOk();
        $this->assertStringContainsString('<h2', $p->json('content'));
        $this->assertStringNotContainsString('<style', $p->json('content'));
        $this->assertContains('seller_policy', collect($p->json('others'))->pluck('key')->all());
        $this->assertNull($this->getJson('/api/v1/policies/return_refund_policy')->json('content'));
    }
}
