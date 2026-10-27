<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Controller;
use App\Models\SortingCenterApplication;
use App\Models\User;
use App\Services\LoginOtp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Sign-in, email code (OTP), sign-up and sign-out for the mobile app. The rules and messages are the
 * web's (AuthenticatedSessionController, the sorting-center LoginController, RegisteredUserController);
 * the app gets a token instead of a session.
 *
 * A successful sign-in answers {status: "ok", token, user}. user.home says which part of the app to open:
 * buyer, seller, seller_status, rider, courier, courier_status, logistics or admin (admin is web only).
 * An account that still has to confirm its email answers {status: "otp_required", otp_token, ...}.
 */
class AuthController extends Controller
{
    /** A22: an app login ends after this many minutes without use (see AppServiceProvider). */
    public const IDLE_MINUTES = 30;

    /** How long the code step may take before signing in again. */
    private const OTP_TOKEN_MINUTES = 30;

    // ── Sign in ──────────────────────────────────────────────

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'       => 'required|email',
            'password'    => 'required',
            'device_name' => 'nullable|string|max:100',
        ]);

        $user = User::where('email', mb_strtolower(trim($data['email'])))->first();
        // A rider or courier made by a logistics company has no password until the emailed link sets one.
        if ($user?->isPendingActivation()) {
            throw ValidationException::withMessages(['email' => 'This account is not activated yet. Open the activation link we emailed you to set your password, then log in.']);
        }
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        return $this->admit($request, $user);   // one login for every account, like the web's /login
    }

    /** The web's admit(): who may enter, who must confirm their email first. */
    private function admit(Request $request, User $user): JsonResponse
    {
        if ($user->isSuspended()) {
            throw ValidationException::withMessages(['email' => 'Your account has been suspended. Please contact support.']);
        }
        if ($user->isDeactivated()) {
            throw ValidationException::withMessages(['email' => 'Your account has been deactivated. Please contact support.']);
        }
        if ($user->isUnverified()) {
            return $this->otpStep($user, 'Please verify your email to finish signing up.');
        }

        // A logistics company that signed up and isn't approved yet: say which, with the reason (like the web).
        if ($user->role === 'sorting_center_pending') {
            throw ValidationException::withMessages(['email' => SortingCenterApplication::loginRefusal($user)]);
        }

        // Seller and courier applicants may sign in to watch their application.
        $applicant = ($user->isSeller() || $user->isCourierPending()) && ! $user->isApproved();
        if (! $applicant) {
            if ($user->isPending()) {
                throw ValidationException::withMessages(['email' => 'Your account is pending administrator approval. You will be notified once it has been reviewed.']);
            }
            if ($user->isRejected()) {
                throw ValidationException::withMessages(['email' => 'Your account application was rejected. Please contact support for more information.']);
            }
        }
        if ($user->isCourier() && $user->courier?->isSuspended()) {
            throw ValidationException::withMessages(['email' => 'Your courier account has been suspended. Please contact the Logistics team.']);
        }

        return $this->signedIn($request, $user);
    }

    private function signedIn(Request $request, User $user, ?string $message = null): JsonResponse
    {
        $previous = $user->last_login_at;
        $user->forceFill(['last_login_at' => now()])->saveQuietly();
        if ($user->isAdmin()) {
            \App\Models\ActivityLog::record('admin_login', 'Admin signed in', $user->name . ' signed in (mobile app).');
        }
        \App\Models\ActivityLog::note($user->name . ' signed in on the app', $user);

        $token = $user->createToken($request->input('device_name') ?: 'ALVY app')->plainTextToken;

        return response()->json([
            'status'  => 'ok',
            'message' => $message ?? 'Welcome back, ' . $user->name . '!',
            'token'   => $token,
            'user'    => self::userPayload($user) + ['previous_login_at' => $previous?->toIso8601String()],
        ]);
    }

    // ── Email code (OTP) ─────────────────────────────────────

    /** Send (or reuse) the 6-digit code, as the web does on its code page. */
    private function otpStep(User $user, string $intro): JsonResponse
    {
        $otp = app(LoginOtp::class);
        if (! $otp->hasActiveCode($user) || $otp->cooldownRemaining($user) === 0) {
            $otp->send($user);
        }

        return $this->otpRequired($user, trim($intro . ' We sent a 6-digit verification code to ' . LoginOtp::maskEmail($user->email) . '.'));
    }

    private function otpRequired(User $user, string $message): JsonResponse
    {
        return response()->json([
            'status'       => 'otp_required',
            'message'      => $message,
            'otp_token'    => Crypt::encryptString(json_encode(['uid' => $user->id, 'exp' => now()->addMinutes(self::OTP_TOKEN_MINUTES)->timestamp])),
            'masked_email' => LoginOtp::maskEmail($user->email),
            'cooldown'     => app(LoginOtp::class)->cooldownRemaining($user),
            'minutes'      => LoginOtp::TTL_MINUTES,
        ], 202);
    }

    private function otpUser(Request $request): User
    {
        $request->validate(['otp_token' => 'required|string']);
        try {
            $data = json_decode(Crypt::decryptString($request->input('otp_token')), true);
        } catch (\Throwable) {
            $data = null;
        }
        $user = ($data && ($data['exp'] ?? 0) >= now()->timestamp) ? User::find($data['uid'] ?? 0) : null;
        if (! $user) {
            throw ValidationException::withMessages(['email' => 'Your verification session has ended. Please log in again.']);
        }

        return $user;
    }

    public function verifyOtp(Request $request): JsonResponse
    {
        $user = $this->otpUser($request);
        $request->validate([
            'otp' => ['required', 'string', 'regex:/^\d{' . LoginOtp::LENGTH . '}$/'],
        ], [
            'otp.required' => 'Please enter the 6-digit code we emailed you.',
            'otp.regex'    => 'The code must be exactly 6 digits (numbers only).',
        ]);

        $error = match (app(LoginOtp::class)->verify($user, $request->input('otp'))) {
            LoginOtp::EXPIRED => 'This code has expired. Please request a new one.',
            LoginOtp::LOCKED  => 'Too many incorrect attempts. Please request a new code.',
            LoginOtp::MISSING => 'No active code was found. Please request a new one.',
            LoginOtp::INVALID => 'The code you entered is incorrect.',
            default           => null,
        };
        if ($error) {
            throw ValidationException::withMessages(['otp' => $error]);
        }

        if (! $user->isUnverified()) {
            return $this->admit($request, $user);
        }

        // Sign-up: email confirmed. A buyer is active and signed in right away; others go to their approval queue.
        if ($user->role === 'buyer') {
            $user->forceFill(['approval_status' => 'approved'])->save();
            return $this->signedIn($request, $user->fresh(), 'Email verified! Welcome to ALVY, ' . ($user->first_name ?: $user->name) . '!');
        }
        $user->forceFill(['approval_status' => 'pending'])->save();
        if ($user->isSeller()) {
            $user->sellerApplication()->where('status', 'unverified')->update(['status' => 'pending']);
            return $this->signedIn($request, $user->fresh(), 'Email verified! Your seller application is now waiting for admin approval.');
        }
        if ($user->isCourierPending() && $user->courier) {
            return response()->json(['status' => 'pending_approval', 'message' => 'Email verified! Your courier application is now waiting for approval from '
                . $user->courier->hubName() . ', the sorting center you applied to. We\'ll email you once it\'s reviewed.']);
        }

        return response()->json(['status' => 'pending_approval',
            'message' => 'Email verified! Your account is now waiting for admin approval. We\'ll email you once it\'s approved.']);
    }

    public function resendOtp(Request $request): JsonResponse
    {
        $user = $this->otpUser($request);
        $otp  = app(LoginOtp::class);
        if (($wait = $otp->cooldownRemaining($user)) > 0) {
            throw ValidationException::withMessages(['otp' => "Please wait {$wait} seconds before requesting a new code."]);
        }
        $otp->send($user);

        return $this->otpRequired($user, 'A new code was sent to ' . LoginOtp::maskEmail($user->email) . '. Your previous code no longer works.');
    }

    // ── Sign up ──────────────────────────────────────────────

    /**
     * The web's sign-up form (role buyer, courier (a rider), seller or logistics, same fields and uploads),
     * run through the web's own controller so every rule and message is the same.
     */
    public function register(Request $request): JsonResponse
    {
        // The web controllers keep the code step in a session; the app gets an otp_token instead.
        $session = app('session')->driver('array');
        $session->start();
        $request->setLaravelSession($session);

        $response = app(RegisteredUserController::class)->store($request);
        $flashed = method_exists($response, 'getSession') ? $response->getSession() : null;
        $flash = $flashed?->get('success');

        // A sign-up step that went back with an error instead of throwing one: report it, never as a success.
        if ($flashed && ($flashed->has('errors') || $flashed->has('error')) && ! $session->get('otp.user_id')) {
            $errors = $flashed->get('errors');
            $bag = $errors instanceof \Illuminate\Support\ViewErrorBag ? $errors->getBag('default')->toArray() : [];
            $message = $flashed->get('error') ?: (collect($bag)->flatten()->first() ?? 'The sign-up could not be completed.');
            return response()->json(['message' => $message, 'errors' => $bag ?: ['email' => [$message]]], 422);
        }

        if ($userId = $session->get('otp.user_id')) {
            $user = User::findOrFail($userId);
            return $this->otpRequired($user, $flash ?: 'Almost done! We sent a 6-digit verification code to ' . LoginOtp::maskEmail($user->email) . '.');
        }

        // A logistics (sorting center) sign-up waits for the administrator; no code step.
        return response()->json(['status' => 'submitted', 'message' => $flash ?: 'Registration submitted. Please wait for the administrator\'s approval.'], 201);
    }

    /** Lists the sign-up form needs (seller categories and types, sorting centers a courier can join). */
    public function registerOptions(): JsonResponse
    {
        return response()->json([
            'seller_categories' => \App\Support\SellerApplicationRules::categories(),
            'seller_types'      => \App\Models\SellerApplication::SELLER_TYPES,
            'hubs'              => \App\Support\SortingCenters::forCouriers(),
        ]);
    }

    // ── Signed in ────────────────────────────────────────────

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => self::userPayload($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            \App\Models\ActivityLog::record('admin_logout', 'Admin signed out', $user->name . ' signed out (mobile app).');
        }
        // Same as the web: a rider who signs out is Offline and gets no more job offers.
        \App\Models\Rider::where('user_id', $user->id)->update(['is_online' => false]);
        $user->currentAccessToken()?->delete();

        return response()->json(['message' => 'You have been signed out.']);
    }

    /** What the app needs to know about the signed-in account. */
    public static function userPayload(User $user): array
    {
        $rider = \App\Models\Rider::where('user_id', $user->id)->first();
        // Like the web's courier dashboard: an approved rider without an approved direct-courier profile works from Hub Jobs.
        $approvedRider = $rider?->isApproved() ?? false;
        $approvedCourier = $user->isCourier() && \App\Models\Courier::where('user_id', $user->id)->where('status', 'approved')->exists();
        // A courier made by a logistics company drives hub-to-hub trips (Add Courier).
        $linehaul = $approvedCourier && \App\Models\Courier::where('user_id', $user->id)->whereNotNull('home_hub_id')->exists();

        $home = match (true) {
            $user->role === 'sorting_center'                 => 'logistics',
            $user->isAdmin()                                 => 'admin',
            $user->isSeller()                                => $user->isApproved() ? 'seller' : 'seller_status',
            $linehaul                                        => 'linehaul',
            $user->isCourier() && $approvedRider && ! $approvedCourier => 'rider',
            $user->isCourier()                               => 'courier',
            $user->isCourierPending()                        => 'courier_status',
            default                                          => 'buyer',
        };

        return [
            'id'              => $user->id,
            'name'            => $user->name,
            'first_name'      => $user->first_name,
            'last_name'       => $user->last_name,
            'email'           => $user->email,
            'phone'           => $user->contact_no ?: $user->phone,
            'role'            => $user->role,
            'approval_status' => $user->approval_status,
            'home'            => $home,
            'photo_url'       => $user->profile_photo_path ? asset('storage/' . $user->profile_photo_path) : null,
            'rider_id'        => $rider?->id,
            'has_rider'       => $user->isCourier() && $approvedRider,     // Hub Jobs (courier/rider/*)
            'has_courier'     => $approvedCourier,                         // direct-courier pages (courier/*)
            'created_at'      => $user->created_at?->toIso8601String(),
        ];
    }
}
