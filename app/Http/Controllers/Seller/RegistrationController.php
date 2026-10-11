<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerApplication;
use App\Models\User;
use App\Services\LoginOtp;
use App\Support\SellerApplicationRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Seller sign-up (new account + shop application).
 * Flow: register → email OTP → Pending Approval → admin approves/rejects.
 */
class RegistrationController extends Controller
{
    public function create()
    {
        if (auth()->check()) {
            return redirect()->route(auth()->user()->isBuyer() ? 'seller.apply' : 'home');
        }

        // Seller sign-up now lives on the main registration form.
        return redirect()->route('register', ['role' => 'seller']);
    }

    public function store(Request $request)
    {
        SellerApplicationRules::normalize($request);
        $email = (string) $request->input('email');

        // One sign-up per email at a time, so a double-clicked submit can't create two accounts.
        return Cache::lock('seller-register:' . sha1($email), 20)->block(15, function () use ($request, $email) {

            // The same form arriving twice (double click / resubmit): continue the first
            // sign-up instead of showing a "duplicate email" error, and don't send a second code.
            if ($repeat = $this->recentUnverifiedSignup($email, (string) $request->input('password'))) {
                return $this->toOtpStep($request, $repeat);
            }

            $data = $request->validate(
                SellerApplicationRules::rules() + SellerApplicationRules::accountRules(),
                SellerApplicationRules::messages(),
                SellerApplicationRules::attributes(),
            );

            $user = DB::transaction(function () use ($request, $data) {
                $user = User::create([
                    'name'            => trim($data['first_name'] . ' ' . $data['last_name']),
                    'first_name'      => $data['first_name'],
                    'last_name'       => $data['last_name'],
                    'email'           => $data['email'],
                    'password'        => Hash::make($data['password']),
                    'role'            => 'seller',
                    'contact_no'      => $data['phone'],
                    'province'        => $data['province'],
                    'municipality'    => $data['municipality'],
                    'barangay'        => $data['barangay'],
                    'street'          => $data['street'],
                    'zip'             => $data['zip_code'],
                    'approval_status' => 'unverified',
                ]);

                SellerApplication::create(
                    ['user_id' => $user->id, 'status' => 'unverified']
                    + SellerApplicationRules::applicationData($request, $data)
                );

                return $user;
            });

            try {
                app(LoginOtp::class)->send($user);
            } catch (\Throwable $e) {
                // The account exists; the user can press "Resend code" on the next page.
                Log::warning('Seller sign-up OTP email failed: ' . $e->getMessage());
            }

            return $this->toOtpStep($request, $user);
        });
    }

    /** An unverified seller account created moments ago with this email and password. */
    private function recentUnverifiedSignup(string $email, string $password): ?User
    {
        if ($email === '' || $password === '') {
            return null;
        }

        $user = User::where('email', $email)
            ->where('role', 'seller')
            ->where('approval_status', 'unverified')
            ->where('created_at', '>=', now()->subMinutes(10))
            ->first();

        return $user && Hash::check($password, $user->password) ? $user : null;
    }

    private function toOtpStep(Request $request, User $user)
    {
        $request->session()->put('otp.user_id', $user->id);
        $request->session()->put('otp.remember', false);

        return redirect()->route('otp.show')->with('success',
            'Almost done! We sent a 6-digit verification code to ' . LoginOtp::maskEmail($user->email) . '.');
    }
}
