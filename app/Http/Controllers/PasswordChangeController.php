<?php

namespace App\Http\Controllers;

use App\Mail\PasswordChangeMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Services\Sms;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Every account's password change (buyer, seller, rider, logistics, admin) is confirmed with a code
 * sent to the account — by email, or by text to its mobile number when SMS is set up (code_via=sms):
 * the form checks the passwords and calls start(), and the new password is used only after the code
 * is entered here. Until then the current password keeps working.
 */
class PasswordChangeController extends Controller
{
    public const CODE_MINUTES = 10;
    public const CODE_ATTEMPTS = 5;

    /** Step 1 (from each account's password form, after its own checks): hold the new password and send a code. */
    public static function start(User $user, string $newPassword, ?RedirectResponse $redirect = null): RedirectResponse
    {
        $user->forceFill(['pending_password' => Hash::make($newPassword)])->save();
        // Where the code goes (the form's "Send the code to" choice); resending uses the same.
        Cache::put(self::viaKey($user), self::smsPossible($user) && request('code_via') === 'sms' ? 'sms' : 'email', now()->addHour());

        return self::sendCode($user, $redirect ?? back());
    }

    /** Whether this account can get the code by text (SMS is set up and it has a mobile number). */
    public static function smsPossible(User $user): bool
    {
        return Sms::enabled() && Sms::numberOf($user) !== null;
    }

    /** Where the waiting code went: ['via' => 'email'|'sms', 'to' => shown address/number]. */
    public static function sentTo(User $user): array
    {
        $via = Cache::get(self::viaKey($user), 'email');
        $number = $via === 'sms' ? Sms::numberOf($user) : null;

        return $number ? ['via' => 'sms', 'to' => Sms::mask($number)] : ['via' => 'email', 'to' => $user->email];
    }

    private static function viaKey(User $user): string
    {
        return 'password-code-via:' . $user->id;
    }

    /** Step 2: the code makes the change. */
    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|digits:6'], ['code.digits' => 'Enter the 6-digit code we sent you.']);
        $user = auth()->user();

        if (! $user->pending_password) {
            return back()->with('error', 'There is no password change waiting for a code.');
        }
        if (! $user->password_otp || ! $user->password_otp_expires_at || $user->password_otp_expires_at->isPast()) {
            return back()->withErrors(['code' => 'This code has expired. Send a new one.']);
        }
        if ($user->password_otp_attempts >= self::CODE_ATTEMPTS) {
            return back()->withErrors(['code' => 'Too many wrong codes. Send a new one.']);
        }
        if (! Hash::check($request->code, $user->password_otp)) {
            $user->increment('password_otp_attempts');
            return back()->withErrors(['code' => 'That code is not correct.']);
        }

        // The pending value is already a hash; the "hashed" cast keeps a hash as it is.
        $user->forceFill(['password' => $user->pending_password, 'pending_password' => null, 'password_otp' => null,
            'password_otp_expires_at' => null, 'password_otp_attempts' => 0])->save();

        if ($user->isAdmin()) {
            // Record the event only — never the password itself.
            \App\Models\ActivityLog::record('admin_password_changed', 'Password Changed', 'Admin account password was changed.');
        }

        return back()->with('success', 'Your password has been changed successfully.');
    }

    public function resend()
    {
        $user = auth()->user();

        return $user->pending_password ? self::sendCode($user, back()) : back();
    }

    public function cancel()
    {
        auth()->user()->forceFill(['pending_password' => null, 'password_otp' => null, 'password_otp_expires_at' => null, 'password_otp_attempts' => 0])->save();

        return back()->with('success', 'Password change cancelled. Your password was not changed.');
    }

    private static function sendCode(User $user, RedirectResponse $redirect): RedirectResponse
    {
        $code = (string) random_int(100000, 999999);
        $user->forceFill([
            'password_otp'            => Hash::make($code),
            'password_otp_expires_at' => now()->addMinutes(self::CODE_MINUTES),
            'password_otp_attempts'   => 0,
        ])->save();

        $to = self::sentTo($user);
        try {
            if ($to['via'] === 'sms') {
                Sms::send(Sms::numberOf($user), "ALVY: {$code} is your code to change your password. It expires in " . self::CODE_MINUTES
                    . " minutes. Don't share it with anyone.");
            } else {
                Mail::to($user->email)->send(new PasswordChangeMail($user, $code, self::CODE_MINUTES));
            }
        } catch (\Throwable $e) {
            Log::error('Password change code could not be sent (' . $to['via'] . '): ' . $e->getMessage());
            return $redirect->with('error', $to['via'] === 'sms'
                ? 'We could not text the code right now. Try again, or choose email.'
                : 'We could not send the code right now. Please try again in a moment.');
        }

        return $redirect->with('success', 'We sent a 6-digit code to ' . $to['to'] . '. Enter it to confirm your new password.');
    }
}
