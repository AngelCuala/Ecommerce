<?php

namespace App\Services;

use App\Mail\LoginOtpMail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * Issues and checks the 6-digit one-time code that verifies a new account's
 * email at sign-up. Codes are stored hashed and expire.
 */
class LoginOtp
{
    public const LENGTH           = 6;
    public const TTL_MINUTES      = 10;
    public const RESEND_COOLDOWN  = 60;  // seconds between sends
    public const MAX_ATTEMPTS     = 5;

    public const OK        = 'ok';
    public const INVALID   = 'invalid';
    public const EXPIRED   = 'expired';
    public const LOCKED    = 'locked';
    public const MISSING   = 'missing';

    /** "maria@gmail.com" → "ma•••@gmail.com" for on-screen messages. */
    public static function maskEmail(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));
        return $visible . str_repeat('•', max(1, mb_strlen($local) - mb_strlen($visible))) . '@' . $domain;
    }

    /** Seconds left before another code may be sent (0 = can send now). */
    public function cooldownRemaining(User $user): int
    {
        if (! $user->otp_sent_at) {
            return 0;
        }
        $elapsed = $user->otp_sent_at->diffInSeconds(now(), true);
        return max(0, self::RESEND_COOLDOWN - (int) floor($elapsed));
    }

    /** True while the last code sent is still usable. */
    public function hasActiveCode(User $user): bool
    {
        return $user->otp_code !== null
            && $user->otp_expires_at !== null
            && $user->otp_expires_at->isFuture()
            && $user->otp_attempts < self::MAX_ATTEMPTS;
    }

    /** Generate a fresh code (invalidating any previous one) and email it. */
    public function send(User $user): void
    {
        $code = str_pad((string) random_int(0, 10 ** self::LENGTH - 1), self::LENGTH, '0', STR_PAD_LEFT);

        $user->forceFill([
            'otp_code'       => Hash::make($code),
            'otp_expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'otp_sent_at'    => now(),
            'otp_attempts'   => 0,
        ])->save();

        Mail::to($user->email)->send(new LoginOtpMail($user, $code, self::TTL_MINUTES));
    }

    /** Check a code. On success the account is marked verified and the code is cleared. */
    public function verify(User $user, string $code): string
    {
        if ($user->otp_code === null || $user->otp_expires_at === null) {
            return self::MISSING;
        }
        if ($user->otp_attempts >= self::MAX_ATTEMPTS) {
            return self::LOCKED;
        }
        if ($user->otp_expires_at->isPast()) {
            return self::EXPIRED;
        }
        if (! Hash::check($code, $user->otp_code)) {
            $user->forceFill(['otp_attempts' => $user->otp_attempts + 1])->save();
            return $user->otp_attempts >= self::MAX_ATTEMPTS ? self::LOCKED : self::INVALID;
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'otp_code'          => null,
            'otp_expires_at'    => null,
            'otp_attempts'      => 0,
        ])->save();

        return self::OK;
    }
}
