<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

/**
 * "Forgot password?": emails a one-time reset link (Laravel's password broker: a random token,
 * stored hashed in password_reset_tokens, valid for auth.passwords.users.expire minutes).
 *
 * The answer is always the same generic message, so nobody can find out which emails have an
 * account. Requests are limited per IP and per email address.
 */
class PasswordResetLinkController extends Controller
{
    public const SENT = 'If an account exists with that email address, we\'ll send you a password reset link shortly. Please check your inbox.';

    /** Requests allowed per IP address per minute, and per email address per 15 minutes. */
    public const PER_IP = 5;
    public const PER_EMAIL = 3;

    public function create()
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'max:255', 'email:rfc', 'regex:/^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/'],
        ], [
            'email.required' => 'Please enter your email address.',
            'email.email'    => 'Please enter a valid email address (e.g. juan@gmail.com).',
            'email.regex'    => 'Please enter a valid email address (e.g. juan@gmail.com).',
        ]);
        $email = strtolower(trim($data['email']));

        $ipKey = 'password-reset:ip:' . $request->ip();
        $emailKey = 'password-reset:email:' . sha1($email);
        if (RateLimiter::tooManyAttempts($ipKey, self::PER_IP) || RateLimiter::tooManyAttempts($emailKey, self::PER_EMAIL)) {
            $wait = max(RateLimiter::availableIn($ipKey), RateLimiter::availableIn($emailKey));
            $minutes = max(1, (int) ceil($wait / 60));

            return back()->withInput(['email' => $email])->withErrors([
                'email' => "Too many reset requests. Please wait {$minutes} " . ($minutes === 1 ? 'minute' : 'minutes') . ' and try again.',
            ]);
        }
        RateLimiter::hit($ipKey, 60);
        RateLimiter::hit($emailKey, 15 * 60);

        // Sent, unknown email, or asked again too soon: the same answer either way.
        Password::broker()->sendResetLink(['email' => $email], function (User $user, string $token) {
            $this->sendEmail($user, $token);
        });

        return back()->with('status', self::SENT);
    }

    /** Never throws: a mail problem is logged (without the link) and the user sees the usual message. */
    private function sendEmail(User $user, string $token): void
    {
        // Built from APP_URL, never from the request's Host header.
        $url = rtrim((string) config('app.url'), '/') . route('password.reset', ['token' => $token, 'email' => $user->email], false);
        $minutes = (int) config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);

        try {
            Mail::to($user->email)->send(new PasswordResetMail($user, $url, $minutes));
        } catch (\Throwable $e) {
            Log::warning('Password reset email to user #' . $user->id . ' could not be sent: ' . get_class($e) . ': ' . $e->getMessage());
        }
    }
}
