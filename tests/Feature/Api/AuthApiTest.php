<?php

namespace Tests\Feature\Api;

use App\Mail\LoginOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RiderTestData;
use Tests\TestCase;

/** Phase 1 of the app conversion: /api/v1 sign-in, email code, sign-up and sign-out, with the web's rules. */
class AuthApiTest extends TestCase
{
    use RefreshDatabase, RiderTestData;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        $this->seedRiderTestData();
    }

    private function login(string $email, string $password, array $extra = [])
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password] + $extra);
    }

    private function lastCode(): string
    {
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function ($m) use (&$code) { $code = $m->code; return true; });
        return $code;
    }

    public function test_each_role_signs_in_to_its_own_part_of_the_app(): void
    {
        $this->login('juanmiguel.delacruz@example.com', 'Mamimili@2026')->assertOk()
            ->assertJsonPath('status', 'ok')->assertJsonPath('user.home', 'buyer')->assertJsonStructure(['token']);
        $this->login('mariaclara.santos@example.com', 'Tindahan@2026')->assertOk()->assertJsonPath('user.home', 'seller');
        $this->login('seller.test01@example.com', 'Tindahan@2026')->assertOk()->assertJsonPath('user.home', 'seller_status');   // Seller 3, pending
        $this->login('pedrojose.garcia@example.com', 'Rider@2026')->assertOk()
            ->assertJsonPath('user.home', 'rider')->assertJsonPath('user.rider_id', $this->rider1->id);
        // One login for everyone: a logistics company too.
        $this->login('operations@bilishatid.example.com', 'Hatid@2026')->assertOk()->assertJsonPath('user.home', 'logistics');
    }

    public function test_wrong_password_and_blocked_accounts_get_the_web_messages(): void
    {
        $this->login('juanmiguel.delacruz@example.com', 'wrong')->assertStatus(422)
            ->assertJsonValidationErrors(['email' => 'These credentials do not match our records.']);

        $this->buyer2->update(['role' => 'suspended']);
        $this->login('anamarie.reyes@example.com', 'Suki#2026Ana')->assertStatus(422)
            ->assertJsonValidationErrors(['email' => 'Your account has been suspended. Please contact support.']);

        $this->buyer2->update(['role' => 'buyer', 'approval_status' => 'pending']);
        $this->login('anamarie.reyes@example.com', 'Suki#2026Ana')->assertStatus(422)
            ->assertJsonValidationErrors(['email' => 'Your account is pending administrator approval. You will be notified once it has been reviewed.']);

        $this->assertSame(0, \Laravel\Sanctum\PersonalAccessToken::count());
    }

    public function test_token_opens_me_and_logout_ends_it(): void
    {
        $token = $this->login('pedrojose.garcia@example.com', 'Rider@2026')->json('token');
        $this->online($this->rider1);

        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('user.email', 'pedrojose.garcia@example.com');
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk()->assertJsonPath('message', 'You have been signed out.');
        $this->assertFalse((bool) $this->rider1->fresh()->is_online);   // signed out = Offline

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_login_ends_after_30_idle_minutes(): void
    {
        $token = $this->login('juanmiguel.delacruz@example.com', 'Mamimili@2026')->json('token');
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();

        $this->travel(29)->minutes();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();   // used again: the clock restarts

        $this->travel(31)->minutes();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    private function signup(array $over = [])
    {
        return $this->postJson('/api/v1/auth/register', array_merge([
            'role' => 'buyer', 'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'email' => 'Juan.App@Example.com',
            'password' => 'Secret@123', 'password_confirmation' => 'Secret@123', 'sex' => 'male', 'contact_no' => '09171112222',
            'birthday' => '2000-01-15', 'region' => 'CALABARZON', 'province' => 'Laguna', 'province_code' => '043400000',
            'municipality' => 'Magdalena', 'municipality_code' => '043415000', 'barangay' => 'Poblacion',
            'house_number' => '12B', 'street' => 'Rizal Street', 'terms' => '1',
            'valid_id' => UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'),
        ], $over));
    }

    public function test_buyer_signs_up_and_confirms_the_email_code(): void
    {
        $this->signup(['contact_no' => '0917'])->assertStatus(422)
            ->assertJsonValidationErrors(['contact_no' => 'Please enter a valid mobile number: 11 digits starting with 09 (e.g. 09171234567).']);
        $this->assertNull(User::where('email', 'juan.app@example.com')->first());

        $res = $this->signup()->assertStatus(202)->assertJsonPath('status', 'otp_required');
        $user = User::where('email', 'juan.app@example.com')->sole();
        $this->assertSame(['buyer', 'unverified'], [$user->role, $user->approval_status]);
        $otpToken = $res->json('otp_token');

        $this->postJson('/api/v1/auth/otp/verify', ['otp_token' => $otpToken, 'otp' => '12345'])->assertStatus(422)
            ->assertJsonValidationErrors(['otp' => 'The code must be exactly 6 digits (numbers only).']);
        $this->postJson('/api/v1/auth/otp/verify', ['otp_token' => 'tampered', 'otp' => '123456'])->assertStatus(422)
            ->assertJsonValidationErrors(['email' => 'Your verification session has ended. Please log in again.']);

        $this->postJson('/api/v1/auth/otp/verify', ['otp_token' => $otpToken, 'otp' => $this->lastCode()])->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('user.home', 'buyer')
            ->assertJsonPath('message', 'Email verified! Welcome to ALVY, ' . ($user->first_name ?: $user->name) . '!');
        $this->assertSame('approved', $user->fresh()->approval_status);   // a buyer is active once the email is confirmed
    }

    public function test_unverified_account_signing_in_gets_the_code_step(): void
    {
        $this->buyer2->update(['approval_status' => 'unverified']);
        $this->login('anamarie.reyes@example.com', 'Suki#2026Ana')->assertStatus(202)
            ->assertJsonPath('status', 'otp_required')->assertJsonMissingPath('token');
        Mail::assertSent(LoginOtpMail::class, fn ($m) => $m->hasTo('anamarie.reyes@example.com'));

        // Resend respects the 60-second wait.
        $otpToken = $this->login('anamarie.reyes@example.com', 'Suki#2026Ana')->json('otp_token');
        $this->postJson('/api/v1/auth/otp/resend', ['otp_token' => $otpToken])->assertStatus(422)->assertJsonValidationErrors('otp');
    }

    private function person(array $over = []): array
    {
        return array_merge([
            'first_name' => 'Rosa', 'last_name' => 'Lim', 'sex' => 'female', 'birthday' => '1994-03-10',
            'region' => 'CALABARZON', 'province' => 'Laguna', 'province_code' => '043400000',
            'municipality' => 'Magdalena', 'municipality_code' => '043415000', 'barangay' => 'Poblacion',
            'house_number' => '7', 'street' => 'Mabini St', 'zip_code' => '4007',
            'password' => 'Secret@123', 'password_confirmation' => 'Secret@123', 'terms' => '1',
        ], $over);
    }

    public function test_seller_signs_up_confirms_the_code_and_sees_the_application_status(): void
    {
        $this->postJson('/api/v1/auth/register', $this->person([
            'role' => 'seller', 'email' => 'rosa.shop@example.com', 'contact_no' => '09175550001', 'password' => 'Rosa@2026x', 'password_confirmation' => 'Rosa@2026x',
            'shop_name' => 'Rosa Crafts', 'line_of_business' => 'Home & Garden', 'seller_type' => 'Corporation', 'tin' => '222-333-444-000',
            'government_id' => UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'),
            'bir_2303' => UploadedFile::fake()->create('bir.pdf', 100, 'application/pdf'),
        ]))->assertStatus(422)->assertJsonValidationErrors(['sec_certificate' => 'The SEC Certificate is required for corporations.']);

        $otpToken = $this->postJson('/api/v1/auth/register', $this->person([
            'role' => 'seller', 'email' => 'rosa.shop@example.com', 'contact_no' => '09175550001', 'password' => 'Rosa@2026x', 'password_confirmation' => 'Rosa@2026x',
            'shop_name' => 'Rosa Crafts', 'line_of_business' => 'Home & Garden', 'seller_type' => 'Sole Proprietorship', 'tin' => '222-333-444-000',
            'government_id' => UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'),
            'dti_certificate' => UploadedFile::fake()->create('dti.pdf', 100, 'application/pdf'),
            'bir_2303' => UploadedFile::fake()->create('bir.pdf', 100, 'application/pdf'),
        ]))->assertStatus(202)->assertJsonPath('status', 'otp_required')->json('otp_token');

        $user = User::where('email', 'rosa.shop@example.com')->sole();
        $this->assertSame(['seller', 'unverified'], [$user->role, $user->approval_status]);
        $this->assertSame('Rosa Crafts', $user->sellerApplication->shop_name);

        $this->postJson('/api/v1/auth/otp/verify', ['otp_token' => $otpToken, 'otp' => $this->lastCode()])->assertOk()
            ->assertJsonPath('status', 'ok')->assertJsonPath('user.home', 'seller_status')
            ->assertJsonPath('message', 'Email verified! Your seller application is now waiting for admin approval.');
        $this->assertSame('pending', $user->sellerApplication->fresh()->status);
    }

    public function test_courier_signs_up_to_a_hub(): void
    {
        $otpToken = $this->postJson('/api/v1/auth/register', $this->person([
            'role' => 'courier', 'email' => 'rosa.rider@example.com', 'contact_no' => '09175550002',
            'vehicle_type' => 'motorcycle', 'plate_number' => 'XYZ 789', 'logistics_id' => $this->logistics1->id,
            'or_cr_image' => UploadedFile::fake()->create('license.jpg', 100, 'image/jpeg'),
            'vehicle_reg_image' => UploadedFile::fake()->create('orcr.pdf', 100, 'application/pdf'),
        ]))->assertStatus(202)->json('otp_token');
        $this->assertSame('courier_pending', User::where('email', 'rosa.rider@example.com')->sole()->role);

        $this->postJson('/api/v1/auth/otp/verify', ['otp_token' => $otpToken, 'otp' => $this->lastCode()])->assertOk()
            ->assertJsonPath('status', 'pending_approval')
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'waiting for approval from'));
    }

    public function test_logistics_signs_up_and_waits_for_the_administrator(): void
    {
        $this->postJson('/api/v1/auth/register', $this->person([
            'role' => 'logistics', 'email' => 'rosa.hub@example.com', 'contact_no' => '09175550003', 'business_name' => 'Rosa Hub',
            'government_id' => UploadedFile::fake()->create('id.pdf', 50, 'application/pdf'),
        ]))->assertStatus(422)->assertJsonValidationErrors(['business_permit' => 'Please upload your business / DTI permit.']);

        $this->postJson('/api/v1/auth/register', $this->person([
            'role' => 'logistics', 'email' => 'rosa.hub@example.com', 'contact_no' => '09175550003', 'business_name' => 'Rosa Hub',
            'government_id' => UploadedFile::fake()->create('id.pdf', 50, 'application/pdf'),
            'business_permit' => UploadedFile::fake()->create('permit.pdf', 50, 'application/pdf'),
        ]))->assertStatus(202)->assertJsonPath('status', 'otp_required');
        // The email is confirmed with the code first, like every other sign-up.
        $code = null;
        Mail::assertSent(\App\Mail\LoginOtpMail::class, function ($m) use (&$code) { $code = $m->code; return $m->user->email === 'rosa.hub@example.com'; });
        $otpToken = $this->postJson('/api/v1/auth/login', ['email' => 'rosa.hub@example.com', 'password' => 'Secret@123'])->assertStatus(202)->json('otp_token');
        $this->postJson('/api/v1/auth/otp/verify', ['otp_token' => $otpToken, 'otp' => $code])->assertOk()
            ->assertJsonPath('status', 'pending_approval')->assertJsonPath('message', fn ($m) => str_contains($m, 'waiting for admin approval'));

        $this->assertSame('sorting_center_pending', User::where('email', 'rosa.hub@example.com')->sole()->role);
        $this->login('rosa.hub@example.com', 'Secret@123')->assertStatus(422)
            ->assertJsonValidationErrors(['email' => 'Your sorting center registration is pending administrator approval. You will be notified by email.']);
    }

    public function test_register_options_list_the_hubs(): void
    {
        $this->getJson('/api/v1/auth/register/options')->assertOk()->assertJsonStructure(['seller_categories', 'seller_types', 'hubs']);
    }
}
