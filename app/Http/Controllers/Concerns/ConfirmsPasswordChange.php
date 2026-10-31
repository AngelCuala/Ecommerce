<?php

namespace App\Http\Controllers\Concerns;

use App\Services\LoginOtp;
use App\Services\PasswordChangeCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Changing the password from an account page, in two steps: (1) current password + a new one that meets
 * the rules, then (2) the 6-digit code emailed to the account. Nothing changes until the code is entered.
 * Used by the buyer profile and the seller account.
 */
trait ConfirmsPasswordChange
{
    /** The rules a new password must meet (the pages show the same list). */
    public static function newPasswordRule(): Password
    {
        return Password::min(8)->letters()->mixedCase()->numbers()->symbols();
    }

    /** Step 1 of 2: check the current password and the new one, then email a code. */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => ['required', 'string', 'confirmed', 'different:current_password', self::newPasswordRule()],
        ], [
            'password.confirmed' => 'The new password and its confirmation do not match.',
            'password.different' => 'The new password must be different from your current one.',
        ]);

        $user = auth()->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        // Nothing changes yet. The new password waits (hashed) until the emailed code is entered.
        $codes = app(PasswordChangeCode::class);
        if ($wait = $codes->cooldown()) {   // one email a minute, however often the form is sent
            return back()->withErrors(['password' => "We just emailed you a code. Please wait {$wait} seconds before asking again."]);
        }
        if (! $codes->start($user, $request->password)) {
            return back()->with('error', PasswordChangeCode::SEND_FAILED);
        }

        return back()->with('success', 'We sent a 6-digit code to ' . LoginOtp::maskEmail($user->email)
            . '. Enter it below to confirm your new password.');
    }

    /** Step 2 of 2: the emailed code makes the new password the account's password. */
    public function confirmPasswordChange(Request $request)
    {
        $request->validate(['code' => 'required|digits:6'], [
            'code.required' => 'Enter the 6-digit code from the email.',
            'code.digits'   => 'The code is the 6 digits from the email.',
        ]);
        $codes = app(PasswordChangeCode::class);

        return match ($codes->confirm(auth()->user(), $request->code)) {
            $codes::OK      => back()->with('success', 'Your password has been changed. Other devices signed in to your account were signed out.'),
            $codes::NONE    => back()->with('error', 'There is no password change waiting for a code. Start again below.'),
            $codes::EXPIRED => back()->withErrors(['code' => 'This code has expired. Send a new one.']),
            $codes::LOCKED  => back()->withErrors(['code' => 'Too many wrong codes. Send a new one.']),
            default         => back()->withErrors(['code' => 'That code is not correct.']),
        };
    }

    public function resendPasswordCode()
    {
        $codes = app(PasswordChangeCode::class);
        if (! $codes->pending()) {
            return back()->with('error', 'There is no password change waiting for a code. Start again below.');
        }
        if ($wait = $codes->cooldown()) {
            return back()->withErrors(['code' => "Please wait {$wait} seconds before asking for a new code."]);
        }
        if (! $codes->send(auth()->user())) {
            return back()->with('error', PasswordChangeCode::SEND_FAILED);
        }

        return back()->with('success', 'We sent a new code to ' . LoginOtp::maskEmail(auth()->user()->email) . '. The old code no longer works.');
    }

    public function cancelPasswordChange()
    {
        app(PasswordChangeCode::class)->cancel();

        return back()->with('success', 'Password change cancelled. Your password was not changed.');
    }
}
