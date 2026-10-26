<?php

namespace Tests\Feature\Buyer;

use App\Mail\PasswordChangeCodeMail;
use App\Models\User;
use App\Services\PasswordChangeCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Changing the password from Accounts & Security needs the 6-digit code emailed to the account. */
class PasswordChangeCodeTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'Mamimili@2026';
    private const NEW = 'Mamimili@2027';

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->buyer = User::forceCreate(['name' => 'Juan Dela Cruz', 'first_name' => 'Juan', 'email' => 'juan@gmail.com', 'password' => Hash::make(self::OLD),
            'role' => 'buyer', 'approval_status' => 'approved', 'email_verified_at' => now(), 'remember_token' => 'old-token']);
    }

    private function as() { return $this->actingAs($this->buyer)->from(route('profile.security')); }

    private function request(string $new = self::NEW)
    {
        return $this->as()->patch(route('profile.change-password'), ['current_password' => self::OLD, 'password' => $new, 'password_confirmation' => $new]);
    }

    private function sentCode(): string
    {
        $code = null;
        Mail::assertSent(PasswordChangeCodeMail::class, function ($m) use (&$code) { $code = $m->code; return true; });
        return $code;
    }

    private function wrong(string $code): string
    {
        return $code === '000000' ? '111111' : '000000';
    }

    public function test_the_request_emails_a_code_and_changes_nothing_yet(): void
    {
        $this->request()->assertSessionHas('success', fn ($m) => str_contains($m, 'We sent a 6-digit code to ju••@gmail.com'));

        Mail::assertSent(PasswordChangeCodeMail::class, function (PasswordChangeCodeMail $m) {
            $html = $m->render();
            return $m->hasTo('juan@gmail.com') && preg_match('/^\d{6}$/', $m->code)
                && str_contains($html, $m->code) && str_contains($html, '10 minutes') && str_contains($html, "Didn't ask for this?");
        });
        $this->assertTrue(Hash::check(self::OLD, $this->buyer->fresh()->password));
        // The new password waits hashed, never in plain text.
        $this->assertStringNotContainsString(self::NEW, json_encode(session(PasswordChangeCode::KEY)));

        $this->get(route('profile.security'))->assertOk()->assertSee('data-password-code-step', false)
            ->assertSee('Check your email')->assertSee(route('profile.password.confirm'), false);
    }

    public function test_the_right_code_changes_the_password_and_signs_out_other_devices(): void
    {
        config(['session.driver' => 'database']);   // sessions live in the database, as on the live site
        DB::table('sessions')->insert(['id' => 'other-phone', 'user_id' => $this->buyer->id, 'ip_address' => '1.2.3.4', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
        $this->request();
        $code = $this->sentCode();

        $this->as()->post(route('profile.password.confirm'), ['code' => $code])
            ->assertSessionHas('success', 'Your password has been changed. Other devices signed in to your account were signed out.');

        $user = $this->buyer->fresh();
        $this->assertTrue(Hash::check(self::NEW, $user->password));
        $this->assertNotSame('old-token', $user->remember_token);
        $this->assertFalse(DB::table('sessions')->where('id', 'other-phone')->exists());
        $this->assertNull(session(PasswordChangeCode::KEY));
        // The code can't be used twice.
        $this->as()->post(route('profile.password.confirm'), ['code' => $code])->assertSessionHas('error');
    }

    public function test_wrong_expired_and_too_many_codes_are_refused(): void
    {
        $this->request();
        $code = $this->sentCode();

        $this->as()->post(route('profile.password.confirm'), ['code' => 'abc'])->assertSessionHasErrors('code');
        $this->as()->post(route('profile.password.confirm'), ['code' => $this->wrong($code)])->assertSessionHasErrors(['code' => 'That code is not correct.']);

        $this->travel(PasswordChangeCode::MINUTES + 1)->minutes();
        $this->as()->post(route('profile.password.confirm'), ['code' => $code])->assertSessionHasErrors(['code' => 'This code has expired. Send a new one.']);
        $this->travelBack();

        // A fresh code; five wrong tries lock it, even the right code then.
        $this->travel(2)->minutes();
        Mail::fake();
        $this->as()->post(route('profile.password.resend'))->assertSessionHas('success');
        $code = $this->sentCode();
        foreach (range(1, PasswordChangeCode::ATTEMPTS) as $i) {
            $this->as()->post(route('profile.password.confirm'), ['code' => $this->wrong($code)]);
        }
        $this->as()->post(route('profile.password.confirm'), ['code' => $code])->assertSessionHasErrors(['code' => 'Too many wrong codes. Send a new one.']);
        $this->assertTrue(Hash::check(self::OLD, $this->buyer->fresh()->password));
    }

    public function test_resend_waits_a_minute_and_replaces_the_old_code(): void
    {
        $this->request();
        $first = $this->sentCode();
        $this->as()->post(route('profile.password.resend'))->assertSessionHasErrors('code');   // too soon

        $this->travel(61)->seconds();
        Mail::fake();
        $this->as()->post(route('profile.password.resend'))->assertSessionHas('success');
        $second = $this->sentCode();

        if ($first !== $second) {
            $this->as()->post(route('profile.password.confirm'), ['code' => $first])->assertSessionHasErrors('code');
        }
        $this->as()->post(route('profile.password.confirm'), ['code' => $second])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check(self::NEW, $this->buyer->fresh()->password));
    }

    public function test_cancel_keeps_the_old_password(): void
    {
        $this->request();
        $code = $this->sentCode();
        $this->as()->post(route('profile.password.cancel'))->assertSessionHas('success', 'Password change cancelled. Your password was not changed.');
        $this->as()->post(route('profile.password.confirm'), ['code' => $code])->assertSessionHas('error');
        $this->assertTrue(Hash::check(self::OLD, $this->buyer->fresh()->password));
        $this->get(route('profile.security'))->assertDontSee('data-password-code-step', false)->assertSee('id="pw-form"', false);

        // Cancelling doesn't reset the one-email-a-minute limit, so cancel + retry can't flood the inbox.
        Mail::fake();
        $this->request()->assertSessionHasErrors('password');
        $this->assertStringContainsString('Please wait', session('errors')->first('password'));
        Mail::assertNothingSent();
        $this->travel(61)->seconds();
        $this->request()->assertSessionHas('success');
        Mail::assertSent(PasswordChangeCodeMail::class, 1);
    }

    public function test_wrong_current_password_sends_no_email_and_a_mail_failure_changes_nothing(): void
    {
        $this->as()->patch(route('profile.change-password'), ['current_password' => 'Wrong@2026', 'password' => self::NEW, 'password_confirmation' => self::NEW])
            ->assertSessionHasErrors(['current_password' => 'Current password is incorrect.']);
        Mail::assertNothingSent();

        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));
        $this->request()->assertSessionHas('error', PasswordChangeCode::SEND_FAILED);
        $this->assertNull(session(PasswordChangeCode::KEY));
        $this->assertTrue(Hash::check(self::OLD, $this->buyer->fresh()->password));
    }
}
