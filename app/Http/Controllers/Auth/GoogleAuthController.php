<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * "Continue with Google" on the login page.
 *
 * - An ALVY account with the same Google ID or email is signed in, after the same
 *   status checks as a password login (suspended, pending approval, seller status…).
 * - A Google email with no ALVY account is sent to the sign-up form with the name
 *   and email filled in, since sign-up still needs an ID, address and mobile number.
 */
class GoogleAuthController extends Controller
{
    public function redirect()
    {
        if (! $this->configured()) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Google sign-in is not set up yet. Please log in with your email and password.']);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        if (! $this->configured()) {
            return redirect()->route('login');
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            report($e);
            return redirect()->route('login')
                ->withErrors(['email' => 'Google sign-in was cancelled or failed. Please try again.']);
        }

        $email = strtolower((string) $google->getEmail());
        if ($email === '') {
            return redirect()->route('login')
                ->withErrors(['email' => 'Your Google account did not share an email address.']);
        }

        $user = User::where('google_id', $google->getId())->first()
            ?? User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $raw = $google->user ?? [];
            return redirect()->route('register')
                ->withInput([
                    'email'      => $email,
                    'first_name' => $raw['given_name'] ?? '',
                    'last_name'  => $raw['family_name'] ?? '',
                ])
                ->with('info', 'No ALVY account uses ' . $email . ' yet. Finish signing up below to create one.');
        }

        if (! $user->google_id) {
            $user->forceFill(['google_id' => $google->getId()])->save();
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

    private function configured(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }
}
