<?php

namespace App\Services;

use App\Mail\PasswordChangeCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * A password change confirmed by a 6-digit code emailed to the account. The new password waits in the
 * session, hashed (never in plain text), together with the hashed code; it only becomes the password when
 * the right code is entered in time. Nothing is stored in the database until then.
 */
class PasswordChangeCode
{
    public const KEY       = 'password_change';
    public const SENT_KEY  = 'password_change_sent_at';   // survives cancel, so cancel + retry can't flood the inbox
    public const MINUTES   = 10;   // a code is valid for 10 minutes
    public const ATTEMPTS  = 5;    // wrong codes before a new one is needed
    public const COOLDOWN  = 60;   // seconds between sends

    public const OK = 'ok', NONE = 'none', EXPIRED = 'expired', LOCKED = 'locked', WRONG = 'wrong';

    public const SEND_FAILED = 'We couldn\'t send the verification email right now. Nothing was changed. Please try again in a moment.';

    /** Hold the new password and email a code. False when the email could not be sent (nothing is kept then). */
    public function start(User $user, string $newPassword): bool
    {
        session()->put(self::KEY, ['password' => Hash::make($newPassword)]);
        return $this->send($user);
    }

    /** A fresh code for the waiting change (the old one stops working). */
    public function send(User $user): bool
    {
        $pending = session(self::KEY);
        if (! $pending) {
            return false;
        }
        $code = (string) random_int(100000, 999999);
        session()->put(self::KEY, ['password' => $pending['password'], 'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(self::MINUTES)->timestamp, 'attempts' => 0]);
        session()->put(self::SENT_KEY, now()->timestamp);

        try {
            Mail::to($user->email)->send(new PasswordChangeCodeMail($user, $code, self::MINUTES, request()->ip()));
        } catch (\Throwable $e) {
            Log::warning('Password change code for user #' . $user->id . ' could not be sent: ' . $e->getMessage());
            session()->forget([self::KEY, self::SENT_KEY]);
            return false;
        }
        return true;
    }

    /** The waiting change, or null. */
    public function pending(): ?array
    {
        return session(self::KEY);
    }

    public function cooldown(): int
    {
        $sent = session(self::SENT_KEY);
        return $sent ? max(0, self::COOLDOWN - (now()->timestamp - $sent)) : 0;
    }

    public function cancel(): void
    {
        session()->forget(self::KEY);
    }

    /**
     * Check the code; with the right one the new password is saved, every other session of the account is
     * signed out and "remember me" is revoked. Returns one of the constants.
     */
    public function confirm(User $user, string $code): string
    {
        $p = $this->pending();
        if (! $p || empty($p['code'])) {
            return self::NONE;
        }
        if (now()->timestamp > $p['expires_at']) {
            return self::EXPIRED;
        }
        if ($p['attempts'] >= self::ATTEMPTS) {
            return self::LOCKED;
        }
        if (! Hash::check($code, $p['code'])) {
            $p['attempts']++;
            session()->put(self::KEY, $p);
            return $p['attempts'] >= self::ATTEMPTS ? self::LOCKED : self::WRONG;
        }

        $user->forceFill(['password' => $p['password'], 'remember_token' => Str::random(60)])->save();
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->where('id', '!=', session()->getId())->delete();
        }
        $this->cancel();
        \App\Models\ActivityLog::note('Password changed (confirmed with an email code)', $user);

        return self::OK;
    }
}
