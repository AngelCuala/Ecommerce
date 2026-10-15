<?php

namespace Tests\Feature;

use App\Mail\LoginOtpMail;
use App\Models\Category;
use App\Models\SellerApplication;
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

/**
 * Sign-up that starts with "Continue with Google": the registration steps stay the same,
 * but the email is locked to the Google one and the email code (OTP) step is skipped.
 */
class GoogleSignupTest extends TestCase
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

    private function fakeGoogle(string $email, string $id = 'g-555'): void
    {
        $google = (new GoogleUser())->setRaw(['given_name' => 'Juan', 'family_name' => 'Dela Cruz'])
            ->map(['id' => $id, 'email' => $email, 'name' => 'Juan Dela Cruz']);

        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')->andReturn($google);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function buyerPayload(array $overrides = []): array
    {
        return array_merge([
            'role' => 'buyer', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz',
            'email' => 'typed.something.else@example.com',
            'password' => 'secret123', 'password_confirmation' => 'secret123',
            'sex' => 'male', 'contact_no' => '09171234567', 'birthday' => '2000-01-15',
            'region' => 'CALABARZON', 'province' => 'Laguna', 'province_code' => '043400000', 'municipality' => 'Magdalena', 'municipality_code' => '043415000', 'barangay' => 'Poblacion',
            'house_number' => '12B', 'street' => 'Rizal Street', 'terms' => '1',
            'valid_id' => UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'),
        ], $overrides);
    }

    public function test_new_google_email_opens_sign_up_with_the_email_locked(): void
    {
        $this->fakeGoogle('new.person@gmail.com');

        $this->get(route('auth.google.callback'))->assertRedirect(route('register'));

        $this->get(route('register'))->assertOk()
            ->assertSee('value="new.person@gmail.com" readonly', false)
            ->assertSee('Verified with Google');
    }

    public function test_google_buyer_sign_up_skips_the_email_code(): void
    {
        $this->fakeGoogle('new.person@gmail.com');
        $this->get(route('auth.google.callback'));

        $this->from(route('register'))->post(route('register'), $this->buyerPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        // The Google email is used even though another one was typed.
        $this->assertDatabaseMissing('users', ['email' => 'typed.something.else@example.com']);
        $user = User::where('email', 'new.person@gmail.com')->firstOrFail();
        $this->assertSame('g-555', $user->google_id);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame('pending', $user->approval_status);

        Mail::assertNotSent(LoginOtpMail::class);
        $this->assertNull(session('google_signup'));
        $this->assertNull(session('otp.user_id'));
    }

    public function test_google_seller_sign_up_goes_straight_to_the_application_status(): void
    {
        Category::create(['name' => 'Home & Garden']);
        $this->fakeGoogle('shop.owner@gmail.com');
        $this->get(route('auth.google.callback'));

        $this->from(route('register'))->post(route('register'), $this->buyerPayload([
            'role' => 'seller', 'contact_no' => '09189876543', 'password' => 'Tindahan@2026',
            'password_confirmation' => 'Tindahan@2026', 'zip_code' => '4001',
            'shop_name' => 'Juan Crafts', 'line_of_business' => 'Home & Garden', 'seller_type' => 'Individual',
            'tin' => '123-456-789-000', 'valid_id' => null,
            'government_id'   => UploadedFile::fake()->create('gov.jpg', 100, 'image/jpeg'),
            'dti_certificate' => UploadedFile::fake()->create('dti.pdf', 100, 'application/pdf'),
            'bir_2303'        => UploadedFile::fake()->create('bir.pdf', 100, 'application/pdf'),
        ]))->assertSessionHasNoErrors()->assertRedirect(route('seller.status'));

        $user = User::where('email', 'shop.owner@gmail.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('pending', $user->approval_status);
        $this->assertSame('pending', SellerApplication::where('user_id', $user->id)->value('status'));
        Mail::assertNotSent(LoginOtpMail::class);
    }

    public function test_use_a_different_email_returns_to_a_normal_sign_up_with_a_code(): void
    {
        $this->fakeGoogle('new.person@gmail.com');
        $this->get(route('auth.google.callback'));

        $this->get(route('register', ['google' => 'forget']))->assertRedirect(route('register'));
        $this->assertNull(session('google_signup'));

        $this->from(route('register'))->post(route('register'), $this->buyerPayload(['email' => 'juan.typed@example.com']))
            ->assertRedirect(route('otp.show'));
        $this->assertSame('unverified', User::where('email', 'juan.typed@example.com')->value('approval_status'));
        Mail::assertSent(LoginOtpMail::class);
    }

    public function test_unfinished_sign_up_is_verified_by_google(): void
    {
        $user = User::create([
            'name' => 'Juan', 'email' => 'half.done@gmail.com', 'password' => Hash::make('secret123'),
            'role' => 'buyer', 'approval_status' => 'unverified',
        ]);
        $this->fakeGoogle('half.done@gmail.com');

        $this->get(route('auth.google.callback'))->assertRedirect(route('login'))->assertSessionHas('success');

        $user->refresh();
        $this->assertSame('pending', $user->approval_status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertGuest();
    }
}
