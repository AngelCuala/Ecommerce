<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LoginOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/** When the verification email can't be sent (bad mail settings, TLS blocked…), nobody sees a crash page. */
class OtpEmailFailureTest extends TestCase
{
    use RefreshDatabase;

    private function unverifiedUser(): User
    {
        return User::forceCreate([
            'name' => 'Maria Santos', 'email' => 'maria@gmail.com', 'password' => Hash::make('Secret@123'),
            'role' => 'buyer', 'approval_status' => 'unverified',
        ]);
    }

    private function mailBreaks(): void
    {
        Mail::shouldReceive('to')->andThrow(new TransportException('Unable to connect with STARTTLS: certificate verify failed'));
    }

    public function test_resend_code_shows_a_message_instead_of_crashing(): void
    {
        $user = $this->unverifiedUser();
        $this->mailBreaks();

        $this->withSession(['otp.user_id' => $user->id])
            ->post(route('otp.resend'))
            ->assertRedirect(route('otp.show'))
            ->assertSessionHasErrors(['otp' => LoginOtp::SEND_FAILED]);

        // No code is left behind that the user never received, and they can retry right away.
        $user->refresh();
        $this->assertNull($user->otp_code);
        $this->assertSame(0, app(LoginOtp::class)->cooldownRemaining($user));
    }

    public function test_logging_in_to_an_unfinished_sign_up_does_not_crash_either(): void
    {
        $this->unverifiedUser();
        $this->mailBreaks();

        $this->post('/login', ['email' => 'maria@gmail.com', 'password' => 'Secret@123'])
            ->assertRedirect(route('otp.show'))
            ->assertSessionHasErrors(['otp' => LoginOtp::SEND_FAILED]);
    }

    public function test_resend_works_normally_when_mail_is_fine(): void
    {
        Mail::fake();
        $user = $this->unverifiedUser();

        $this->withSession(['otp.user_id' => $user->id])
            ->post(route('otp.resend'))
            ->assertRedirect(route('otp.show'))
            ->assertSessionHas('success');
        $this->assertNotNull($user->fresh()->otp_code);
    }
}
