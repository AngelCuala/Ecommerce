<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/**
 * The page the reset email links to. The token must exist for that email, be unexpired and
 * unused; after a successful reset it is deleted (single use), "remember me" is revoked, and
 * every signed-in session of the account is ended, so the user logs in again with the new password.
 */
class NewPasswordController extends Controller
{
    public const INVALID = 'This password reset link is invalid or has expired. Please request a new one.';

    /** Same rule as changing the password in Profile → Security. */
    public static function rule(): PasswordRule
    {
        return PasswordRule::min(8)->letters()->mixedCase()->numbers()->symbols();
    }

    public function create(Request $request, string $token)
    {
        $email = (string) $request->query('email', '');
        $user = $email !== '' ? User::where('email', $email)->first() : null;
        $valid = $user && Password::broker()->tokenExists($user, $token);

        return view('auth.reset-password', ['token' => $token, 'email' => $email, 'valid' => $valid]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', self::rule()],
        ], [
            'password.required'  => 'Please enter a new password.',
            'password.confirmed' => 'The passwords do not match. Please type the same password in both fields.',
        ]);

        $status = Password::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password'       => Hash::make($password),
                    'remember_token' => Str::random(60),   // "remember me" cookies stop working
                ])->save();
                // Sign the account out everywhere (database sessions, every portal).
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
                }
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            // Wrong, expired, already-used token, or no such account: one answer for all.
            return back()->withInput($request->only('email'))->withErrors(['email' => self::INVALID]);
        }

        \App\Models\ActivityLog::note('Password reset with an emailed link', User::where('email', $request->email)->first());

        return redirect()->route('login')->with('success', 'Your password has been reset successfully. Please log in with your new password.');
    }
}
