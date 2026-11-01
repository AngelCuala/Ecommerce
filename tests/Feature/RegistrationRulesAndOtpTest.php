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
            'password'     => 'Secret@123',
            'password_confirmation' => 'Secret@123',
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
            'password'        => Hash::make('Secret@123'),
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
                'first_name' => "First name may only contain letters, spaces, hyphens (-), apostrophes (') and periods (.) — no numbers or other symbols.",
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

    /** Names with ñ, hyphens, apostrophes and periods (TC-BUY-005). */
    public function test_names_with_hyphen_apostrophe_and_period_are_accepted(): void
    {
        $this->register(['first_name' => 'Niño Angelo', 'last_name' => "Peñaflor-O'Hara"])->assertSessionHasNoErrors();
        $this->assertSame("Peñaflor-O'Hara", User::where('email', 'juan@gmail.com')->value('last_name'));
        $this->register(['first_name' => 'Ma. Cristina', 'email' => 'ma@gmail.com', 'contact_no' => '09171234500'])->assertSessionHasNoErrors();
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
        foreach (['0917123456', '091712345678', '08171234567', '9171234567', '0917abc4567'] as $bad) {
            $this->register(['contact_no' => $bad])->assertSessionHasErrors([
                'contact_no' => 'Please enter a valid mobile number: 11 digits starting with 09 (e.g. 09171234567).',
            ]);
        }
        $this->assertDatabaseCount('users', 0);
    }

    public function test_mobile_number_already_in_use_blocks_registration(): void
    {
        $this->makeUser(['contact_no' => '09171234567']);

        // +63 and spaces are the same number (TC-BUY-016).
        foreach (['+639171234567', '0917 123 4567', '0917-123-4567'] as $same) {
            $this->register(['contact_no' => $same])->assertSessionHasErrors(['contact_no' => 'This mobile number is already in use by another account.']);
        }

        $this->register(['contact_no' => '09171234567'])->assertSessionHasErrors([
            'contact_no' => 'This mobile number is already in use by another account.',
        ]);
        $this->assertDatabaseCount('users', 1);
    }

    // ── Password ────────────────────────────────────────────

    public function test_password_7_rejected_8_accepted(): void
    {
        $this->register(['password' => 'Bili@26', 'password_confirmation' => 'Bili@26'])->assertSessionHasErrors('password');
        $this->register(['password' => 'Bili@202', 'password_confirmation' => 'Bili@202'])->assertSessionDoesntHaveErrors('password');
    }

    /** Uppercase, lowercase, number and symbol, typed twice (TC-BUY-018, TC-BUY-019). */
    public function test_password_needs_every_character_type_and_a_matching_confirmation(): void
    {
        foreach (['mamimili@2026', 'MAMIMILI@2026', 'Mamimili@abcd', 'Mamimili2026'] as $weak) {
            $this->register(['password' => $weak, 'password_confirmation' => $weak])->assertSessionHasErrors('password');
        }
        $this->register(['password' => 'Mamimili@2026', 'password_confirmation' => 'Mamimili@2027'])
            ->assertSessionHasErrors(['password' => 'The passwords do not match.']);
        $this->assertDatabaseCount('users', 0);
    }

    /** At least 18 years old (TC-BUY-007, TC-BUY-008). */
    public function test_age_must_be_at_least_18(): void
    {
        $this->register(['birthday' => now()->subYears(18)->addDay()->toDateString()])
            ->assertSessionHasErrors(['birthday' => 'You must be at least 18 years old.']);
        $this->register(['birthday' => now()->addDay()->toDateString()])
            ->assertSessionHasErrors(['birthday' => 'Please enter a valid date of birth (it cannot be today or in the future).']);
        $this->register(['birthday' => now()->subYears(18)->toDateString()])->assertSessionDoesntHaveErrors('birthday');
    }

    // ── Sign-up → email code → shopping (buyers need no admin approval) ─────────────

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

    /** A buyer is active and signed in as soon as the email code is entered (TC-BUY-023). */
    public function test_entering_the_sign_up_code_moves_the_account_to_pending_approval(): void
    {

        $this->register();
        $code = null;
        Mail::assertSent(LoginOtpMail::class, function ($m) use (&$code) { $code = $m->code; return true; });

        $this->post(route('otp.verify'), ['otp' => $code])
            ->assertRedirect(route('home'))
            ->assertSessionHas('success', 'Email verified! Welcome to ALVY, Juan!');

        $user = User::where('email', 'juan@gmail.com')->first();
        $this->assertSame('approved', $user->approval_status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);

        // No admin step: signing out and back in works straight away.
        auth()->logout();
        $this->post('/login', ['email' => 'juan@gmail.com', 'password' => 'Secret@123'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_a_buyer_left_pending_before_this_change_can_log_in(): void
    {
        $user = $this->makeUser(['approval_status' => 'pending', 'email_verified_at' => now()]);

        $this->post('/login', ['email' => 'maria@gmail.com', 'password' => 'Secret@123'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('approved', $user->fresh()->approval_status);
    }

    // ── Logging in never asks for a code ────────────────────

    public function test_approved_account_logs_in_without_a_code(): void
    {
        $user = $this->makeUser();   // approved; older account whose email was never verified

        $this->post('/login', ['email' => 'maria@gmail.com', 'password' => 'Secret@123'])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
        Mail::assertNothingSent();
    }

    public function test_admins_log_in_without_a_code(): void
    {
        $this->makeUser(['role' => 'admin', 'email' => 'admin@alvy.test', 'contact_no' => null]);

        $this->post('/login', ['email' => 'admin@alvy.test', 'password' => 'Secret@123'])->assertRedirect();
        $this->assertAuthenticated();
        Mail::assertNothingSent();
    }

    public function test_pending_account_cannot_log_in_and_gets_no_code(): void
    {
        // Accounts other than buyers still wait for approval.
        $this->makeUser(['role' => 'sorting_center_pending', 'approval_status' => 'pending', 'email_verified_at' => now()]);

        $this->from('/login')->post('/login', ['email' => 'maria@gmail.com', 'password' => 'Secret@123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        Mail::assertNothingSent();
    }

    public function test_unfinished_sign_up_is_sent_back_to_the_code_step(): void
    {
        $this->makeUser(['approval_status' => 'unverified']);

        $this->post('/login', ['email' => 'maria@gmail.com', 'password' => 'Secret@123'])->assertRedirect(route('otp.show'));
        $this->assertGuest();
        Mail::assertSent(LoginOtpMail::class);
    }

    // ── Code rules (wrong / format / expired / locked / resend) ──

    /** An unverified sign-up sitting on the code page. */
    private function startOtp(): array
    {
        $user = $this->makeUser(['approval_status' => 'unverified']);
        $this->post('/login', ['email' => 'maria@gmail.com', 'password' => 'Secret@123']);
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
        $this->post(route('otp.verify'), ['otp' => $newCode])->assertRedirect(route('home'));
        $this->assertSame('approved', $user->fresh()->approval_status);
    }

    public function test_resend_after_expiry_allows_verification(): void
    {
        [$user] = $this->startOtp();
        $this->travel(LoginOtp::TTL_MINUTES + 1)->minutes();

        $this->post(route('otp.resend'))->assertSessionHasNoErrors();
        $newCode = null;
        Mail::assertSent(LoginOtpMail::class, function ($m) use (&$newCode) { $newCode = $m->code; return true; });

        $this->post(route('otp.verify'), ['otp' => $newCode])->assertRedirect(route('home'));
        $this->assertSame('approved', $user->fresh()->approval_status);
    }

    public function test_otp_page_without_a_pending_sign_up_redirects_to_login(): void
    {
        $this->get(route('otp.show'))->assertRedirect(route('login'));
        $this->post(route('otp.verify'), ['otp' => '123456'])->assertRedirect(route('login'));
    }
}
