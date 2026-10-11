<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\GovernmentId;
use App\Services\LoginOtp;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class RegisteredUserController extends Controller
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
            ->view('auth.register', [
                // Seller fields live on this same form (shown when "Seller" is picked).
                'categories'  => \App\Support\SellerApplicationRules::categories(),
                'sellerTypes' => \App\Models\SellerApplication::SELLER_TYPES,
            ])
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
        // Get the role from the request
        $role = $request->input('role', 'buyer');

        // Sellers use the same form but go through the seller sign-up (shop details,
        // TIN, business documents, email OTP). Map this form's field names to it.
        if ($role === 'seller') {
            $request->merge([
                'phone'  => $request->input('contact_no'),
                'street' => trim($request->input('house_number', '') . ' ' . $request->input('street', '')),
            ]);

            return app(\App\Http\Controllers\Seller\RegistrationController::class)->store($request);
        }

        // Emails are compared case-insensitively for duplicates.
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }

        $psgcAvailable = class_exists(\App\Services\PsgcDirectory::class);

        // Letters (any language, e.g. ñ) and single spaces between words only — no numbers or symbols.
        $nameRule = ['required', 'string', 'min:2', 'max:50', 'regex:/^\pL+( \pL+)*$/u'];

        // Base validation rules
        $rules = [
            // Sellers sign up through /seller/register (shop details + email verification).
            'role'           => 'required|in:buyer,courier,logistics',
            'first_name'     => $nameRule,
            'last_name'      => $nameRule,
            'middle_initial' => ['nullable', 'string', 'max:3', 'regex:/^\pL{1,2}\.?$/u'],
            'email'          => ['required', 'string', 'max:255', 'email:rfc', 'regex:/^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/', 'unique:users,email'],
            'password'       => 'required|string|min:8',
            'sex'            => 'required|in:male,female', // users.sex is enum('Male','Female')
            'terms'          => 'accepted',
            // Fields now required for all roles
            'contact_no'   => ['required', 'string', 'regex:/^09\d{9}$/', 'unique:users,contact_no'],
            'birthday'     => 'required|date|before:today',
            'province'     => 'nullable|string|max:120',
            'municipality' => 'required|string|max:120',
            'barangay'     => 'required|string|max:120',
            'house_number' => 'required|string|max:50',
            'street'       => 'required|string|max:255',
            'zip_code'     => 'nullable|string|max:20',
        ] + ($psgcAvailable ? \App\Services\PsgcDirectory::codeRules() : []);

        // Role-specific validation rules
        if ($role === 'buyer') {
            $rules['valid_id'] = ['bail', 'required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120', new GovernmentId];
        } elseif ($role === 'seller') {
            $rules['id_image'] = 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120';
            $rules['business_permit'] = 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120';
        } elseif ($role === 'courier') {
            $rules['vehicle_type'] = 'required|in:motorcycle,tricycle,car,truck';
            $rules['plate_number'] = 'required|string|max:50';
            // couriers.id_license_path / or_cr_path are NOT NULL, so both documents are required.
            $rules['or_cr_image'] = ['bail', 'required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120', new GovernmentId]; // driver's license
            $rules['vehicle_reg_image'] = 'required|file|mimes:jpg,jpeg,png,pdf|max:5120';
            $rules['logistics_id'] = 'nullable|exists:users,id';
        } elseif ($role === 'logistics') {
            $rules['business_name'] = 'required|string|max:255';
            $rules['province'] = 'required|string|max:255';
            $rules['municipality'] = 'required|string|max:255';
            $rules['barangay'] = 'required|string|max:255';
            $rules['street'] = 'nullable|string|max:255';
            $rules['house_number'] = 'nullable|string|max:50';
            $rules['dti_permit'] = 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120';
        }

        $validated = $request->validate($rules, [
            'terms.accepted'       => 'You must agree to the Terms and Conditions and Privacy Policy to create an account.',
            'valid_id.required'    => 'Please upload a photo of a valid government-issued ID.',
            'valid_id.mimes'       => 'Your ID must be a JPG, PNG or PDF file.',
            'valid_id.max'         => 'Your ID file must be 5 MB or smaller.',

            'first_name.regex'     => 'First name may only contain letters and spaces — no numbers or symbols.',
            'first_name.min'       => 'First name must be at least 2 characters.',
            'first_name.max'       => 'First name must not be longer than 50 characters.',
            'last_name.regex'      => 'Last name may only contain letters and spaces — no numbers or symbols.',
            'last_name.min'        => 'Last name must be at least 2 characters.',
            'last_name.max'        => 'Last name must not be longer than 50 characters.',
            'middle_initial.regex' => 'Middle initial may only contain letters (e.g. "M" or "M.").',
            'middle_initial.max'   => 'Middle initial may only contain letters (e.g. "M" or "M.").',

            'email.email'          => 'Please enter a valid email address (e.g. juan@gmail.com).',
            'email.regex'          => 'Please enter a valid email address (e.g. juan@gmail.com).',
            'email.unique'         => 'This email address is already registered. Please log in or use a different email.',

            'contact_no.regex'     => 'Please enter a valid mobile number: 11 digits starting with 09 (e.g. 09171234567).',
            'contact_no.unique'    => 'This mobile number is already in use by another account.',

            'password.min'         => 'Password must be at least 8 characters.',
        ]);

        // Resolve the address against the PSA PSGC dataset (official names + codes)
        // when that service is installed; otherwise use the submitted names as-is.
        if ($psgcAvailable) {
            $addr = app(\App\Services\PsgcDirectory::class)->resolve($request->all(), true, $validated['barangay']);
        } else {
            $addr = [
                'province_display' => $validated['province'] ?? null,
                'municipality'     => $validated['municipality'],
                'barangay'         => $validated['barangay'],
            ];
        }

        // Server-side age calculation — never trust the client value.
        $age = \Carbon\Carbon::parse($validated['birthday'])->age;

        // Create the user with basic info
        $userData = [
            'name'            => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'first_name'      => $validated['first_name'],
            'last_name'       => $validated['last_name'],
            'middle_initial'  => $validated['middle_initial'] ?? null,
            'email'           => $validated['email'],
            'password'        => Hash::make($validated['password']),
            'role'            => $role,
            'sex'             => ucfirst($validated['sex']),
            'contact_no'      => $validated['contact_no'],
            'birthday'        => $validated['birthday'],
            'age'             => $age,
            'province'        => $addr['province_display'],
            'municipality'    => $addr['municipality'],
            'barangay'        => $addr['barangay'],
            'street'          => $validated['street'],
            'house_number'    => $validated['house_number'],
            'approval_status' => 'unverified', // → 'pending' once the email code is entered
        ];

        // Buyer: save the uploaded ID to the private disk.
        if ($role === 'buyer' && $request->hasFile('valid_id')) {
            $userData['valid_id_path'] = $request->file('valid_id')->store('valid-ids', 'local');
            $userData += GovernmentId::columnsFor('valid_id');
        }
        if ($role === 'courier') {
            $userData += GovernmentId::columnsFor('or_cr_image');
        }

        // Seller: save the uploaded ID
        if ($role === 'seller' && $request->hasFile('id_image')) {
            $userData['valid_id_path'] = $request->file('id_image')->store('seller-ids', 'public');
        }

        $user = User::create($userData);

        // Create role-specific records
        if ($role === 'seller') {
            $sellerData = [
                'user_id'         => $user->id,
                'last_name'       => $validated['last_name'],
                'first_name'      => $validated['first_name'],
                'middle_initial'  => $validated['middle_initial'] ?? null,
                'full_name'       => $user->name,
                'shop_name'       => $validated['first_name'] . "'s Store",
                'business_name'   => $validated['first_name'] . "'s Store",
                'line_of_business'=> 'General',
                'sex'             => ucfirst($validated['sex']),
                'birthday'        => '2000-01-01',
                'age'             => 24,
                'status'          => 'pending',
                'province'        => '',
                'municipality'    => '',
                'barangay'        => '',
            ];

            if ($request->hasFile('id_image')) {
                $sellerData['government_id_path'] = $request->file('id_image')->store('seller-ids', 'public');
            }
            if ($request->hasFile('business_permit')) {
                $sellerData['business_permit_path'] = $request->file('business_permit')->store('seller-permits', 'public');
            }

            \App\Models\SellerApplication::create($sellerData);
        } elseif ($role === 'courier') {
            $courierData = [
                'user_id'        => $user->id,
                'last_name'      => $validated['last_name'],
                'first_name'     => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'] ?? null,
                'sex'            => ucfirst($validated['sex']),
                'birthday'       => $validated['birthday'],
                'age'            => $age,
                'vehicle_type'   => $validated['vehicle_type'],
                'plate_number'   => strtoupper($validated['plate_number']),
                'status'         => 'pending',
                'province'       => $addr['province_display'] ?? '',
                'municipality'   => $addr['municipality'],
                'barangay'       => $addr['barangay'],
                'street'         => trim($validated['house_number'] . ' ' . $validated['street']),
                'contact_no'     => $validated['contact_no'],
                // "or_cr_image" is the driver's license upload; "vehicle_reg_image" is the OR/CR.
                'id_license_path' => $request->file('or_cr_image')->store('courier-licenses', 'public'),
                'or_cr_path'      => $request->file('vehicle_reg_image')->store('courier-vehicle-regs', 'public'),
            ];

            \App\Models\Courier::create($courierData);
        }

        event(new Registered($user));

        // Verify the email with a one-time code before the account goes for approval.
        try {
            app(LoginOtp::class)->send($user);
        } catch (\Throwable $e) {
            // The account exists; the user can press "Resend code" on the next page.
            Log::warning('Sign-up OTP email failed: ' . $e->getMessage());
        }

        $request->session()->put('otp.user_id', $user->id);
        $request->session()->put('otp.remember', false);

        return redirect()->route('otp.show')->with('success',
            'Almost done! We sent a 6-digit verification code to ' . LoginOtp::maskEmail($user->email) . '.');
    }
}
