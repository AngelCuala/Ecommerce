<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        // If user is already authenticated, redirect to appropriate dashboard
        if (auth()->check()) {
            $user = auth()->user();
            return $this->redirectToUserDashboard($user);
        }

        // Add cache control headers to prevent browser back button issues
        return response()
            ->view('auth.login')
            ->header('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
    }

    /**
     * Redirect user to their appropriate dashboard based on role
     */
    private function redirectToUserDashboard($user)
    {
        switch ($user->role) {
            case 'admin':
                return redirect()->route('admin.dashboard');
            case 'seller':
                return redirect()->route('seller.dashboard');
            case 'courier':
                return redirect()->route('courier.dashboard');
            case 'sorting_center':
                return redirect()->route('sc.dashboard');
            default:
                return redirect()->route('home');
        }
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        return $this->admit($request, Auth::user());
    }

    /**
     * Status checks shared by password and Google sign-in. The user is already
     * logged in here; they are logged back out if the account may not enter yet.
     */
    public function admit(Request $request, User $user)
    {
        // Block suspended or deactivated accounts
        if ($user->isSuspended()) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages([
                'email' => 'Your account has been suspended. Please contact support.',
            ]);
        }
        if ($user->isDeactivated()) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Please contact support.',
            ]);
        }

        // Sign-up never finished (email code not entered yet) → back to the code step, no access.
        if ($user->isUnverified()) {
            Auth::guard('web')->logout();
            return $this->sendToOtpStep($request, $user, 'Please verify your email to finish signing up.');
        }

        // Seller sign-ups that are pending or rejected → only the application status page.
        if ($user->isSeller() && ! $user->isApproved()) {
            $request->session()->regenerate();
            return redirect()->route('seller.status');
        }

        // Courier sign-ups waiting for (or turned down by) their sorting center → their
        // application status page, like sellers. Approval makes them a courier.
        if ($user->isCourierPending() && ! $user->isApproved()) {
            $request->session()->regenerate();
            return redirect()->route('courier.status')
                ->with('info', $this->courierWaitingMessage($user));
        }

        // Block accounts pending admin approval or rejected
        if ($user->isPending()) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages([
                'email' => 'Your account is pending administrator approval. You will be notified once it has been reviewed.',
            ]);
        }
        if ($user->isRejected()) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages([
                'email' => 'Your account application was rejected. Please contact support for more information.',
            ]);
        }

        $request->session()->regenerate();

        return $this->completeLogin($request, $user);
    }

    /**
     * Park the (logged-out) user on the code-entry page. A new code is emailed
     * unless one was sent moments ago and is still valid.
     */
    private function sendToOtpStep(Request $request, User $user, string $intro = ''): \Illuminate\Http\RedirectResponse
    {
        $otp = app(LoginOtp::class);
        $request->session()->put('otp.user_id', $user->id);
        $request->session()->put('otp.remember', $request->boolean('remember'));

        if (! $otp->hasActiveCode($user) || $otp->cooldownRemaining($user) === 0) {
            $otp->send($user);
        }

        return redirect()->route('otp.show')
            ->with('success', trim($intro . ' We sent a 6-digit verification code to ' . $this->maskEmail($user->email) . '.'));
    }

    /**
     * Second half of a successful login (after password and, if needed, OTP checks):
     * record the login and send the user to their dashboard.
     */
    public function completeLogin(Request $request, User $user)
    {
        $request->session()->forget('url.intended');

        // Record login timestamp. Keep the previous value in the session so the
        // profile page can show "last login" as the prior sign-in, not this one.
        $request->session()->put('previous_login_at', $user->last_login_at);
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        // Sorting center staff → SC panel
        if ($user->role === 'sorting_center') {
            return redirect()->route('sc.dashboard')
                ->with('success', 'Welcome back, ' . $user->name . '!');
        }

        // Approved couriers → courier portal (unless their courier record is suspended)
        if ($user->isCourier()) {
            $courier = $user->courier;
            if ($courier && $courier->isSuspended()) {
                Auth::guard('web')->logout();
                throw ValidationException::withMessages([
                    'email' => 'Your courier account has been suspended. Please contact the Logistics team.',
                ]);
            }
            return redirect()->route('courier.dashboard')
                ->with('success', 'Welcome back, ' . $user->name . '!');
        }

        // Courier applicants still awaiting review → status page with a clear message
        if ($user->isCourierPending()) {
            return redirect()->route('courier.status')
                ->with('info', $this->courierWaitingMessage($user));
        }

        // ALVY admins → ALVY admin panel
        if ($user->isAdmin()) {
            \App\Models\ActivityLog::record('admin_login', 'Admin signed in', $user->name . ' signed in.');

            // The main login always opens the admin panel. The logistics / sorting
            // panel has its own entrance at /logistics/login.
            return redirect()->route('admin.dashboard')
                ->with('success', 'Welcome back, ' . $user->name . '!');
        }

        // Sellers → seller dashboard
        if ($user->isSeller()) {
            return redirect()->route('seller.dashboard')
                ->with('success', 'Welcome back, ' . $user->name . '!');
        }

        return redirect()->intended(route('home'))->with('success', 'Welcome back, ' . Auth::user()->name . '!');
    }

    // ── One-time code (OTP) step ─────────────────────────────

    /** The account waiting on an OTP in this session, or null. */
    private function pendingOtpUser(Request $request): ?User
    {
        $id = $request->session()->get('otp.user_id');
        return $id ? User::find($id) : null;
    }

    private function maskEmail(string $email): string
    {
        return LoginOtp::maskEmail($email);
    }

    public function showOtp(Request $request)
    {
        $user = $this->pendingOtpUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        return response()
            ->view('auth.verify-otp', [
                'maskedEmail' => $this->maskEmail($user->email),
                'cooldown'    => app(LoginOtp::class)->cooldownRemaining($user),
                'minutes'     => LoginOtp::TTL_MINUTES,
            ])
            ->header('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate');
    }

    public function verifyOtp(Request $request)
    {
        $user = $this->pendingOtpUser($request);
        if (! $user) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Your verification session has ended. Please log in again.']);
        }

        $request->validate([
            'otp' => ['required', 'string', 'regex:/^\d{' . LoginOtp::LENGTH . '}$/'],
        ], [
            'otp.required' => 'Please enter the 6-digit code we emailed you.',
            'otp.regex'    => 'The code must be exactly 6 digits (numbers only).',
        ]);

        $result = app(LoginOtp::class)->verify($user, $request->input('otp'));

        $error = match ($result) {
            LoginOtp::EXPIRED => 'This code has expired. Please request a new one.',
            LoginOtp::LOCKED  => 'Too many incorrect attempts. Please request a new code.',
            LoginOtp::MISSING => 'No active code was found. Please request a new one.',
            LoginOtp::INVALID => 'The code you entered is incorrect.',
            default           => null,
        };
        if ($error) {
            throw ValidationException::withMessages(['otp' => $error]);
        }

        $remember = (bool) $request->session()->pull('otp.remember', false);
        $request->session()->forget('otp.user_id');

        // Sign-up: email confirmed → the account goes to the approval queue.
        if ($user->isUnverified()) {
            return $this->finishSignup($request, $user, $remember);
        }

        Auth::login($user, $remember);
        $request->session()->regenerate();

        return $this->completeLogin($request, $user);
    }

    /**
     * A new account's email is confirmed (by the emailed code, or by Google):
     * it moves from "unverified" to the approval queue.
     */
    public function finishSignup(Request $request, User $user, bool $remember = false, string $verified = 'Email verified!')
    {
        $user->forceFill(['approval_status' => 'pending'])->save();

        // Sellers can watch their application on the status page.
        if ($user->isSeller()) {
            $user->sellerApplication()->where('status', 'unverified')->update(['status' => 'pending']);

            Auth::login($user, $remember);
            $request->session()->regenerate();

            return redirect()->route('seller.status')
                ->with('success', $verified . ' Your seller application is now waiting for admin approval.');
        }

        if ($user->isCourierPending() && $user->courier) {
            return redirect()->route('login')->with('success', $verified . ' Your courier application is now waiting for approval from '
                . $user->courier->hubName() . ', the sorting center you applied to. We\'ll email you once it\'s reviewed.');
        }

        return redirect()->route('login')->with('success',
            $verified . ' Your account is now waiting for admin approval. We\'ll email you once it\'s approved.');
    }

    /** "Still waiting" message for a courier applicant, naming the sorting center they applied to. */
    private function courierWaitingMessage(User $user): string
    {
        $courier = $user->courier;
        if ($courier && $courier->isRejected()) {
            return 'Your courier application was not approved by ' . $courier->hubName() . '. See the details below.';
        }

        return 'Your courier application is still waiting for approval from '
            . ($courier ? $courier->hubName() : 'the Logistics team') . '.';
    }

    public function resendOtp(Request $request)
    {
        $user = $this->pendingOtpUser($request);
        if (! $user) {
            return redirect()->route('login');
        }

        $otp  = app(LoginOtp::class);
        $wait = $otp->cooldownRemaining($user);
        if ($wait > 0) {
            return redirect()->route('otp.show')
                ->withErrors(['otp' => "Please wait {$wait} seconds before requesting a new code."]);
        }

        $otp->send($user);

        return redirect()->route('otp.show')
            ->with('success', 'A new code was sent to ' . $this->maskEmail($user->email) . '. Your previous code no longer works.');
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        if ($user && $user->isAdmin()) {
            \App\Models\ActivityLog::record('admin_logout', 'Admin signed out', $user->name . ' signed out.');
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Add cache control headers to prevent accessing cached dashboard pages
        return response()
            ->redirectTo('/')
            ->with('success', 'You have been signed out.')
            ->header('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
    }
}
