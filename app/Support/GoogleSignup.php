<?php

namespace App\Support;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * A sign-up that started with "Continue with Google" for an email that has no
 * ALVY account yet. The Google identity is kept in the session (never trusted
 * from the form), the sign-up form's email is locked to it, and because Google
 * has already confirmed the address the email code (OTP) step is skipped.
 */
class GoogleSignup
{
    private const KEY = 'google_signup';

    public static function remember(Request $request, string $googleId, string $email): void
    {
        $request->session()->put(self::KEY, ['id' => $googleId, 'email' => mb_strtolower($email)]);
    }

    /** @return array{id: string, email: string}|null */
    public static function pending(Request $request): ?array
    {
        return $request->hasSession() ? $request->session()->get(self::KEY) : null;
    }

    public static function forget(Request $request): void
    {
        $request->session()->forget(self::KEY);
    }

    /** Whatever the form sent, a Google sign-up uses the Google email. */
    public static function lockEmail(Request $request): void
    {
        if ($google = self::pending($request)) {
            $request->merge(['email' => $google['email']]);
        }
    }

    /**
     * For a Google sign-up: link the account, mark the email verified and send it
     * on to approval. Returns null for a normal sign-up (which still gets an OTP).
     */
    public static function finish(Request $request, User $user)
    {
        $google = self::pending($request);
        if (! $google || mb_strtolower($user->email) !== $google['email']) {
            return null;
        }

        $user->forceFill(['google_id' => $google['id'], 'email_verified_at' => now()])->save();
        self::forget($request);

        return app(AuthenticatedSessionController::class)
            ->finishSignup($request, $user, false, 'Signed up with Google!');
    }
}
