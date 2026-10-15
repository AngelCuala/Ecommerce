<?php

namespace Tests\Feature;

use App\Mail\LoginOtpMail;
use App\Models\User;
use App\Services\LoginOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Registration field rules (name, email, mobile, password) and the one-time code
 * an approved account must verify on its first login.
 */
class RegistrationRulesAndOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'role'         => 'buyer',
            'first_name'   => 'Juan',
            'last_name'    => 'Dela Cruz',
            'email'        => 'juan@gmail.com',
            'password'     => 'secret123',
            'password_confirmation' => 'secret123',
            'sex'          => 'male',
            'contact_no'   => '09171234567',
            'birthday'     => '2000-01-15',
            'region'       => 'CALABARZON',
            'province'     => 'Laguna',
            'province_code' => '043400000',
            'municipality' => 'Magdalena',
            'municipality_code' => '043415000',
            'barangay'     => 'Poblacion',
            'house_number' => '12B',
            'street'       => 'Rizal Street',
            'terms'        => '1',
            'valid_id'     => UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'),
        ], $overrides);
    }

    private function register(array $overrides = [])
    {
        return $this->from('/register')->post('/register', $this->payload($overrides));
    }

    private function makeUser(array $attrs = []): User
    {
        return User::create(array_merge([
            'name'            => 'Maria Santos',
            'first_name'      => 'Maria',
            'last_name'       => 'Santos',
            'email'           => 'maria@gmail.com',
            'password'        => Hash::make('secret123'),
            'role'            => 'buyer',
            'contact_no'      => '09998887777',
            'approval_status' => 'approved',
        ], $attrs));
    }

    // ── Names ───────────────────────────────────────────────

    public function test_names_with_numbers_or_symbols_are_rejected(): void
    {
        foreach (['Juan2', 'Ju@n', 'Juan!', 'Juan_Carlos', '123'] as $bad) {
            $this->register(['first_name' => $bad])->assertSessionHasErrors([
                'first_name' => 'First name may only contain letters and spaces — no numbers or symbols.',
            ]);
            $this->register(['last_name' => $bad])->assertSessionHasErrors('last_name');
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_name_length_1_and_51_rejected_with_length_message(): void
    {
        $this->register(['first_name' => 'J'])
            ->assertSessionHasErrors(['first_name' => 'First name must be at least 2 characters.']);
        $this->register(['last_name' => str_repeat('a', 51)])
            ->assertSessionHasErrors(['last_name' => 'Last name must not be longer than 50 characters.']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_name_length_2_and_50_accepted(): void
    {
        $this->register(['first_name' => 'Jo', 'last_name' => str_repeat('a', 50)])
            ->assertSessionDoesntHaveErrors(['first_name', 'last_name']);
    }

    // ── Email ───────────────────────────────────────────────

    public function test_invalid_emails_are_rejected_and_no_account_is_created(): void
    {
        foreach (['juan', 'juan@', '@gmail.com', 'juan@gmail', 'juan gmail.com', 'juan@@gmail.com', 'juan@gmail.'] as $bad) {
            $this->register(['email' => $bad])->assertSessionHasErrors([
                'email' => 'Please enter a valid email address (e.g. juan@gmail.com).',
            ]);
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_duplicate_email_blocks_registration_case_insensitively(): void
    {
        $this->makeUser(['email' => 'juan@gmail.com']);

        $this->register(['email' => 'JUAN@Gmail.com'])->assertSessionHasErrors([
            'email' => 'This email address is already registered. Please log in or use a different email.',
        ]);
        $this->assertDatabaseCount('users', 1);
    }

    // ── Mobile number ───────────────────────────────────────

    public function test_invalid_mobile_numbers_are_rejected(): void
    {
        foreach (['0917123456', '091712345678', '08171234567', '9171234567', '+639171234567', '0917-123-4567', '0917abc4567'] as $bad) {
            $this->register(['contact_no' => $bad])->assertSessionHasErrors([
                'contact_no' => 'Please enter a valid mobile number: 11 digits starting with 09 (e.g. 09171234567).',
            ]);
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_mobile_number_already_in_use_blocks_registration(): void
    {
        $this->makeUser(['contact_no' => '09171234567']);

        $this->register(['contact_no' => '09171234567'])->assertSessionHasErrors([
            'contact_no' => 'This mobile number is already in use by another account.',
        ]);
        $this->assertDatabaseCount('users', 1);
    }

    // ── Password ────────────────────────────────────────────

    public function test_password_7_rejected_8_accepted(): void
    {
        $this->register(['password' => '1234567'])
            ->assertSessionHasErrors(['password' => 'Password must be at least 8 characters.']);
        $this->register(['password' => '12345678'])
            ->assertSessionDoesntHaveErrors('password');
    }

    // ── Sign-up → email code → pending approval ─────────────

    public function test_valid_registration_creates_an_unverified_account_and_emails_a_code(): void
    {

        $this->register()->assertRedirect(route('otp.show'))->assertSessionHasNoErrors();

        $user = User::where('email', 'juan@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('unverified', $user->approval_status);
        $this->assertNull($user->email_verified_at);
        Mail::assertSent(LoginOtpMail::class, fn ($m) => $m->hasTo('juan@gmail.com') && preg_match('/^\d{6}$/', $m->code));
        $this->get(route('otp.show'))->assertOk()->assertSee('ju••@gmail.com', false);
    }

    public function test_entering_the_sign_up_code_moves_the_account_to_pending_approval(): void
    {

        $this->register();
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function ($m) use (&$code) { $code = $m->code; return true; });

        $this->post(route('otp.verify'), ['otp' => $code])
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', "Email verified! Your account is now waiting for admin approval. We'll email you once it's approved.");

        $user = User::where('email', 'juan@gmail.com')->first();
        $this->assertSame('pending', $user->approval_status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertGuest();
    }

    // ── Logging in never asks for a code ────────────────────

    public function test_approved_account_logs_in_without_a_code(): void
    {
        $user = $this->makeUser();   // approved; older account whose email was never verified

        $this->post('/login', ['email' => 'maria@gmail.com', 'password' => 'secret123'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        Mail::assertNothingSent();
    }

    public function test_admins_log_in_without_a_code(): void
    {
        $this->makeUser(['role' => 'admin', 'email' => 'admin@alvy.test', 'contact_no' => null]);

        $this->post('/login', ['email' => 'admin@alvy.test', 'password' => 'secret123'])->assertRedirect();
        $this->assertAuthenticated();
        Mail::assertNothingSent();
    }

    public function test_pending_account_cannot_log_in_and_gets_no_code(): void
    {
        $this->makeUser(['approval_status' => 'pending', 'email_verified_at' => now()]);

        $this->from('/login')->post('/login', ['email' => 'maria@gmail.com', 'password' => 'secret123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        Mail::assertNothingSent();
    }

    public function test_unfinished_sign_up_is_sent_back_to_the_code_step(): void
    {
        $this->makeUser(['approval_status' => 'unverified']);

        $this->post('/login', ['email' => 'maria@gmail.com', 'password' => 'secret123'])->assertRedirect(route('otp.show'));
        $this->assertGuest();
        Mail::assertSent(LoginOtpMail::class);
    }

    // ── Code rules (wrong / format / expired / locked / resend) ──

    /** An unverified sign-up sitting on the code page. */
    private function startOtp(): array
    {
        $user = $this->makeUser(['approval_status' => 'unverified']);
        $this->post('/login', ['email' => 'maria@gmail.com', 'password' => 'secret123']);
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function ($m) use (&$code) { $code = $m->code; return true; });
        return [$user, $code];
    }

    public function test_otp_in_wrong_format_is_rejected(): void
    {
        [$user] = $this->startOtp();

        foreach (['12345', '1234567', '12a456', 'abcdef', '12 345', ''] as $bad) {
            $this->from(route('otp.show'))->post(route('otp.verify'), ['otp' => $bad])
                ->assertSessionHasErrors('otp');
        }
        $this->post(route('otp.verify'), ['otp' => '12a456'])
            ->assertSessionHasErrors(['otp' => 'The code must be exactly 6 digits (numbers only).']);
        $this->assertSame('unverified', $user->fresh()->approval_status);
        $this->assertSame(0, $user->fresh()->otp_attempts, 'Format errors do not use up attempts.');
    }

    public function test_wrong_otp_is_rejected(): void
    {
        [$user, $code] = $this->startOtp();
        $wrong = $code === '000000' ? '111111' : '000000';

        $this->post(route('otp.verify'), ['otp' => $wrong])
            ->assertSessionHasErrors(['otp' => 'The code you entered is incorrect.']);
        $this->assertSame('unverified', $user->fresh()->approval_status);
    }

    public function test_expired_otp_is_rejected(): void
    {
        [$user, $code] = $this->startOtp();

        $this->travel(LoginOtp::TTL_MINUTES + 1)->minutes();

        $this->post(route('otp.verify'), ['otp' => $code])
            ->assertSessionHasErrors(['otp' => 'This code has expired. Please request a new one.']);
        $this->assertSame('unverified', $user->fresh()->approval_status);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_too_many_wrong_attempts_locks_the_code(): void
    {
        [, $code] = $this->startOtp();
        $wrong = $code === '000000' ? '111111' : '000000';

        for ($i = 0; $i < LoginOtp::MAX_ATTEMPTS; $i++) {
            $this->post(route('otp.verify'), ['otp' => $wrong]);
        }
        $this->post(route('otp.verify'), ['otp' => $code])
            ->assertSessionHasErrors(['otp' => 'Too many incorrect attempts. Please request a new code.']);
    }

    public function test_resend_otp_sends_a_new_code_and_old_one_stops_working(): void
    {
        [$user, $oldCode] = $this->startOtp();

        // Cooldown: an immediate resend is refused.
        $this->post(route('otp.resend'))->assertSessionHasErrors('otp');
        Mail::assertSentCount(1);

        $this->travel(LoginOtp::RESEND_COOLDOWN + 1)->seconds();
        $this->post(route('otp.resend'))->assertRedirect(route('otp.show'))->assertSessionHasNoErrors();
        Mail::assertSentCount(2);

        $newCode = null;
        Mail::assertSent(LoginOtpMail::class, function ($m) use (&$newCode) { $newCode = $m->code; return true; });

        if ($newCode !== $oldCode) {
            $this->post(route('otp.verify'), ['otp' => $oldCode])
                ->assertSessionHasErrors(['otp' => 'The code you entered is incorrect.']);
        }
        $this->post(route('otp.verify'), ['otp' => $newCode])->assertRedirect(route('login'));
        $this->assertSame('pending', $user->fresh()->approval_status);
    }

    public function test_resend_after_expiry_allows_verification(): void
    {
        [$user] = $this->startOtp();
        $this->travel(LoginOtp::TTL_MINUTES + 1)->minutes();

        $this->post(route('otp.resend'))->assertSessionHasNoErrors();
        $newCode = null;
        Mail::assertSent(LoginOtpMail::class, function ($m) use (&$newCode) { $newCode = $m->code; return true; });

        $this->post(route('otp.verify'), ['otp' => $newCode])->assertRedirect(route('login'));
        $this->assertSame('pending', $user->fresh()->approval_status);
    }

    public function test_otp_page_without_a_pending_sign_up_redirects_to_login(): void
    {
        $this->get(route('otp.show'))->assertRedirect(route('login'));
        $this->post(route('otp.verify'), ['otp' => '123456'])->assertRedirect(route('login'));
    }
}
