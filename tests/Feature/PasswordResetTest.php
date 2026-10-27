<?php

namespace Tests\Feature;

use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** "Forgot password?": request a link, get the email, set a new password, log in with it. */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'secret123';
    private const NEW = 'NewPass#2026';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['app.url' => 'https://alvy.example']);
        $this->user = User::forceCreate(['name' => 'Juan Dela Cruz', 'first_name' => 'Juan', 'email' => 'juan@gmail.com',
            'password' => Hash::make(self::OLD), 'role' => 'buyer', 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    private function requestLink(string $email = 'juan@gmail.com')
    {
        return $this->from(route('password.request'))->post(route('password.email'), ['email' => $email]);
    }

    /** Ask for a link and pull the URL / token out of the email that was sent. */
    private function link(): array
    {
        $this->requestLink();
        $mail = null;
        Mail::assertSent(PasswordResetMail::class, function ($m) use (&$mail) { $mail = $m; return true; });
        preg_match('#/reset-password/([^?]+)\?email=([^&]+)#', $mail->url, $m);
        return ['url' => $mail->url, 'token' => $m[1], 'email' => urldecode($m[2])];
    }

    private function reset(string $token, array $over = [])
    {
        return $this->from(route('password.reset', ['token' => $token, 'email' => 'juan@gmail.com']))
            ->post(route('password.store'), $over + ['token' => $token, 'email' => 'juan@gmail.com',
                'password' => self::NEW, 'password_confirmation' => self::NEW]);
    }

    // 1, 2
    public function test_forgot_password_page_loads_and_the_login_link_opens_it(): void
    {
        $this->get(route('password.request'))->assertOk()
            ->assertSee('Forgot Your Password?')->assertSee('Send Reset Link')->assertSee('Back to Login')
            ->assertSee("Enter the email address associated with your account, and we'll send you a link to reset your password.");
        $this->get(route('login'))->assertOk()->assertSee('href="' . route('password.request') . '"', false);
        $this->get(route('sc.login'))->assertSee('href="' . route('password.request') . '"', false);
    }

    // 3
    public function test_registered_email_gets_a_branded_reset_email_with_a_safe_absolute_link(): void
    {
        $this->requestLink('JUAN@gmail.com ')->assertRedirect(route('password.request'))
            ->assertSessionHas('status', PasswordResetLinkController::SENT);

        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $m) {
            $html = $m->render();
            return $m->hasTo('juan@gmail.com')
                && str_starts_with($m->url, 'https://alvy.example/reset-password/')   // APP_URL, not the request host
                && str_contains($html, 'Hi Juan') && str_contains($html, 'Reset Password')
                && str_contains($html, '60 minutes') && str_contains($html, 'safely ignore');
        });
        $this->assertDatabaseCount('password_reset_tokens', 1);
        $stored = DB::table('password_reset_tokens')->value('token');
        $this->assertStringNotContainsString($this->link()['token'], $stored);   // stored hashed
    }

    // 4
    public function test_unregistered_email_gets_the_same_answer_and_no_email(): void
    {
        $this->requestLink('nobody@gmail.com')->assertSessionHas('status', PasswordResetLinkController::SENT);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    // 5
    public function test_invalid_email_formats_are_rejected(): void
    {
        foreach (['', 'juan', 'juan@', 'juan@gmail', 'ju an@gmail.com'] as $bad) {
            $this->requestLink($bad)->assertSessionHasErrors('email');
        }
        Mail::assertNothingSent();
    }

    // 6
    public function test_repeated_requests_are_rate_limited_without_revealing_the_account(): void
    {
        foreach (range(1, PasswordResetLinkController::PER_EMAIL) as $i) {
            $this->requestLink()->assertSessionHas('status');
        }
        $this->requestLink()->assertSessionHasErrors(['email' => 'Too many reset requests. Please wait 15 minutes and try again.']);
        // An unknown address hits the same limit, so the limit says nothing about who has an account.
        foreach (range(1, PasswordResetLinkController::PER_EMAIL) as $i) {
            RateLimiter::clear('password-reset:ip:127.0.0.1');
            $this->requestLink('ghost@gmail.com');
        }
        RateLimiter::clear('password-reset:ip:127.0.0.1');
        $this->requestLink('ghost@gmail.com')->assertSessionHasErrors('email');
        // Only one email went out: the broker also waits 60 seconds between links for the same account.
        Mail::assertSent(PasswordResetMail::class, 1);
    }

    // 7, 13, 14
    public function test_valid_link_sets_a_hashed_new_password_and_only_the_new_one_logs_in(): void
    {
        $link = $this->link();
        $this->get($link['url'])->assertOk()->assertSee('Set a New Password')->assertSee('value="juan@gmail.com"', false)
            ->assertSee('Confirm New Password')->assertSee('data-toggle-password', false);

        $this->reset($link['token'])->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Your password has been reset successfully. Please log in with your new password.');

        $hash = $this->user->fresh()->password;
        $this->assertNotSame(self::NEW, $hash);
        $this->assertTrue(Hash::check(self::NEW, $hash));
        $this->assertGuest();   // must log in again

        $this->post('/login', ['email' => 'juan@gmail.com', 'password' => self::OLD])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => 'juan@gmail.com', 'password' => self::NEW])->assertRedirect();
        $this->assertAuthenticatedAs($this->user->fresh());
    }

    // 8
    public function test_expired_link_is_rejected(): void
    {
        $link = $this->link();
        $this->travel(61)->minutes();
        $this->get($link['url'])->assertOk()->assertSee('This password reset link is invalid or has expired.')->assertSee('Request a New Link');
        $this->reset($link['token'])->assertSessionHasErrors(['email' => NewPasswordController::INVALID]);
        $this->assertTrue(Hash::check(self::OLD, $this->user->fresh()->password));
    }

    // 9
    public function test_invalid_token_or_wrong_email_is_rejected(): void
    {
        $link = $this->link();
        $this->get(route('password.reset', ['token' => 'not-a-real-token', 'email' => 'juan@gmail.com']))->assertSee('Link Expired');
        $this->reset('not-a-real-token')->assertSessionHasErrors(['email' => NewPasswordController::INVALID]);
        $this->reset($link['token'], ['email' => 'someone@gmail.com'])->assertSessionHasErrors(['email' => NewPasswordController::INVALID]);
        $this->assertTrue(Hash::check(self::OLD, $this->user->fresh()->password));
    }

    // 10, 11
    public function test_mismatch_and_weak_passwords_are_rejected(): void
    {
        $token = $this->link()['token'];
        $this->reset($token, ['password_confirmation' => 'Different#2026'])
            ->assertSessionHasErrors(['password' => 'The passwords do not match. Please type the same password in both fields.']);
        foreach (['Sh#1a', 'alllowercase#1', 'NoNumbers#here', 'NoSymbols123'] as $weak) {
            $this->reset($token, ['password' => $weak, 'password_confirmation' => $weak])->assertSessionHasErrors('password');
        }
        $this->assertTrue(Hash::check(self::OLD, $this->user->fresh()->password));
        $this->assertDatabaseCount('password_reset_tokens', 1);   // still usable after a failed try
    }

    // 12
    public function test_a_used_link_cannot_be_used_again(): void
    {
        $link = $this->link();
        $this->reset($link['token'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('password_reset_tokens', 0);

        $this->get($link['url'])->assertSee('Link Expired');
        $this->reset($link['token'], ['password' => 'Another#2026', 'password_confirmation' => 'Another#2026'])
            ->assertSessionHasErrors(['email' => NewPasswordController::INVALID]);
        $this->assertTrue(Hash::check(self::NEW, $this->user->fresh()->password));
    }

    public function test_reset_signs_the_account_out_everywhere_and_revokes_remember_me(): void
    {
        $this->user->forceFill(['remember_token' => 'old-remember-token'])->save();
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $this->user->id, 'ip_address' => '1.2.3.4', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        DB::table('sessions')->insert(['id' => 'someone-else', 'user_id' => 999, 'ip_address' => '1.2.3.4', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);

        $token = $this->link()['token'];
        config(['session.driver' => 'database']);
        $this->reset($token)->assertSessionHasNoErrors();

        $this->assertNotSame('old-remember-token', $this->user->fresh()->remember_token);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $this->user->id)->count());   // signed out everywhere
        $this->assertTrue(DB::table('sessions')->where('id', 'someone-else')->exists());          // other accounts untouched
    }

    public function test_a_mail_failure_is_logged_safely_and_the_user_sees_the_usual_message(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Connection refused'));
        Log::spy();

        $this->requestLink()->assertSessionHas('status', PasswordResetLinkController::SENT);
        Log::shouldHaveReceived('warning')->withArgs(fn ($msg) => str_contains($msg, 'Password reset email to user #' . $this->user->id)
            && str_contains($msg, 'Connection refused') && ! str_contains($msg, 'reset-password/'));
    }

    // 15
    public function test_registration_and_normal_login_still_work(): void
    {
        $this->get(route('register'))->assertOk();
        $this->post('/login', ['email' => 'juan@gmail.com', 'password' => self::OLD])->assertRedirect();
        $this->assertAuthenticatedAs($this->user);
        $this->get(route('password.request'))->assertRedirect();   // already signed in: not needed
    }
}
