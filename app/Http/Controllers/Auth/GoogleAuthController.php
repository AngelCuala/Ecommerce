<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\GoogleSignup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * "Continue with Google" on the login page.
 *
 * - An ALVY account with the same Google ID or email is signed in, after the same
 *   status checks as a password login (suspended, pending approval, seller status…).
 * - A Google email with no ALVY account is sent to the sign-up form with the name
 *   filled in and the email locked, since sign-up still needs an ID, address and
 *   mobile number. The email code step is skipped (see App\Support\GoogleSignup).
 * - Started from the mobile app (?app=1): the same Google sign-in, but the answer goes back to
 *   the app (alvyshop://google-auth) as a one-time code the app exchanges for its login.
 */
class GoogleAuthController extends Controller
{
    /** Where the app waits for the answer of a Google sign-in it started. */
    public const APP_RETURN = 'alvyshop://google-auth';

    public function redirect(Request $request)
    {
        // Remember an app sign-in for the callback (it comes back in this same browser session).
        $request->session()->put('google_app', $request->boolean('app'));
        if (! $this->configured()) {
            if ($request->boolean('app')) {
                return $this->toApp(['error' => 'Google sign-in is not set up yet. Please log in with your email and password.']);
            }
            return redirect()->route('login')
                ->withErrors(['email' => 'Google sign-in is not set up yet. Please log in with your email and password.']);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        $app = (bool) $request->session()->pull('google_app', false);
        if (! $this->configured()) {
            return $app ? $this->toApp(['error' => 'Google sign-in is not set up yet.']) : redirect()->route('login');
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);
            return $app ? $this->toApp(['error' => 'Google sign-in was cancelled or failed. Please try again.'])
                : redirect()->route('login')->withErrors(['email' => 'Google sign-in was cancelled or failed. Please try again.']);
        }

        $email = strtolower((string) $google->getEmail());
        if ($email === '') {
            return $app ? $this->toApp(['error' => 'Your Google account did not share an email address.'])
                : redirect()->route('login')->withErrors(['email' => 'Your Google account did not share an email address.']);
        }

        $user = User::where('google_id', $google->getId())->first()
            ?? User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($app) {
            return $this->backToApp($google, $email, $user);
        }

        if (! $user) {
            // Google has confirmed this email: the sign-up form locks it and skips the email code.
            GoogleSignup::remember($request, (string) $google->getId(), $email);

            $raw = $google->user ?? [];
            return redirect()->route('register')
                ->withInput([
                    'email'      => $email,
                    'first_name' => $raw['given_name'] ?? '',
                    'last_name'  => $raw['family_name'] ?? '',
                ])
                ->with('info', 'No ALVY account uses ' . $email . ' yet. Finish signing up below — no email code needed, Google has already verified your email.');
        }

        if (! $user->google_id) {
            $user->forceFill(['google_id' => $google->getId()])->save();
        }

        // Signed up earlier but never entered the email code: Google proves the email.
        if ($user->isUnverified()) {
            $user->forceFill(['email_verified_at' => now()])->save();
            return app(AuthenticatedSessionController::class)
                ->finishSignup($request, $user, false, 'Email verified with Google!');
        }

        Auth::login($user, true);

        try {
            return app(AuthenticatedSessionController::class)->admit($request, $user);
        } catch (ValidationException $e) {
            // Blocked account (suspended, pending, rejected…): show why on the login page,
            // not "back", which would be Google.
            return redirect()->route('login')->withErrors($e->errors());
        }
    }

    /**
     * The app's answer: a one-time code (10 minutes) the app exchanges at POST /api/v1/auth/google/exchange.
     * An existing account signs in there (with the same checks as a password login); a new Google email
     * goes to the app's sign-up form, email locked and no email code (as on the website).
     */
    private function backToApp($google, string $email, ?User $user)
    {
        $code = Str::random(48);
        if (! $user) {
            $raw = $google->user ?? [];
            Cache::put('google-app:' . $code, ['kind' => 'signup', 'google_id' => (string) $google->getId(), 'email' => $email,
                'first_name' => $raw['given_name'] ?? '', 'last_name' => $raw['family_name'] ?? ''], now()->addMinutes(30));
        } else {
            if (! $user->google_id) {
                $user->forceFill(['google_id' => $google->getId()])->save();
            }
            // Signed up earlier but never entered the email code: Google proves the email.
            if ($user->isUnverified()) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }
            Cache::put('google-app:' . $code, ['kind' => 'login', 'user_id' => $user->id], now()->addMinutes(10));
        }

        return $this->toApp(['code' => $code]);
    }

    private function toApp(array $query)
    {
        return redirect()->away(self::APP_RETURN . '?' . http_build_query($query));
    }

    private function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }
}
