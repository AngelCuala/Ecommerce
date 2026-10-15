<?php

namespace App\Http\Controllers\SortingCenter;

use App\Http\Controllers\Controller;
use App\Models\SortingCenterApplication;
use App\Models\User;
use App\Services\PsgcDirectory;
use App\Support\GoogleSignup;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Public Logistics / Sorting Center registration.
 * Creates an account with role `sorting_center_pending` (locked) until an admin approves.
 */
class RegistrationController extends Controller
{
    public function create()
    {
        // Sorting center sign-up now lives on the main registration form ("Logistics").
        return redirect()->route('register', ['role' => 'logistics']);
    }

    public function store(Request $request)
    {
        GoogleSignup::lockEmail($request);
        $psgcAvailable = class_exists(PsgcDirectory::class);
        if ($psgcAvailable) PsgcDirectory::normalizeCodes($request);

        $data = $request->validate([
            'last_name'       => 'required|string|max:100',
            'first_name'      => 'required|string|max:100',
            'middle_initial'  => 'nullable|string|max:5',
            'sex'             => 'required|in:Male,Female',
            'email'           => 'required|string|email|max:255|unique:users,email',
            'contact_no'      => ['required', 'string', 'regex:/^09\d{9}$/'],
            'birthday'        => 'required|date|before:today',
            'password'        => 'required|string|min:8|confirmed',
            'business_name'   => 'required|string|max:150',
            'province'        => 'nullable|string|max:120',
            'municipality'    => 'required|string|max:120',
            'barangay'        => 'required|string|max:120',
            'street'          => 'required|string|max:255',
            'house_number'    => 'required|string|max:50',
            'zip_code'        => 'nullable|string|max:20',
            'government_id'   => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'business_permit' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'terms'           => 'accepted',
        ] + ($psgcAvailable ? PsgcDirectory::codeRules() : [
            'province_code'     => ['nullable', 'regex:/^\d{9,10}$/'],
            'municipality_code' => ['nullable', 'regex:/^\d{9,10}$/'],
        ]), [
            'business_permit.required' => 'Please upload your business / DTI permit.',
            'contact_no.regex'         => 'Please enter a valid mobile number: 11 digits starting with 09 (e.g. 09171234567).',
        ]);

        // Validate the full address chain against the PSA PSGC dataset when that service is
        // installed; otherwise use the names and codes picked in the address dropdowns.
        $addr = $psgcAvailable
            ? app(PsgcDirectory::class)->resolve($request->all(), true, $data['barangay'])
            : [
                'region'            => $request->input('region'),
                'region_code'       => null,
                'province_display'  => $data['province'] ?? null,
                'province_code'     => $data['province_code'] ?? null,
                'municipality'      => $data['municipality'],
                'municipality_code' => $data['municipality_code'] ?? null,
                'barangay'          => $data['barangay'],
                'barangay_code'     => null,
            ];
        if (! $addr['municipality_code']) {
            throw ValidationException::withMessages([
                'municipality' => "{$addr['municipality']} has no PSA correspondence code yet.",
            ]);
        }

        $age        = Carbon::parse($data['birthday'])->age;
        $idPath     = $request->file('government_id')->store('sorting-center-ids', 'local');
        $permitPath = $request->file('business_permit')->store('sorting-center-permits', 'local');
        $address    = implode(', ', array_filter([
            $data['house_number'], $data['street'],
            $addr['barangay'], $addr['municipality'], $addr['province_display'],
        ]));

        $user = DB::transaction(function () use ($data, $addr, $age, $idPath, $permitPath, $address) {
            $user = User::create([
                'name'            => $data['business_name'],
                'first_name'      => $data['first_name'],
                'last_name'       => $data['last_name'],
                'middle_initial'  => $data['middle_initial'] ?? null,
                'email'           => $data['email'],
                'password'        => Hash::make($data['password']),
                'role'            => 'sorting_center_pending',
                'approval_status' => 'pending',
                'sex'             => $data['sex'],
                'contact_no'      => $data['contact_no'],
                'birthday'        => $data['birthday'],
                'age'             => $age,
                'province'        => $addr['province_display'],
                'municipality'    => $addr['municipality'],
                'barangay'        => $addr['barangay'],
                'street'          => $data['street'],
                'house_number'    => $data['house_number'],
                'valid_id_path'   => $idPath,
            ]);

            SortingCenterApplication::create([
                'user_id'              => $user->id,
                'last_name'            => $data['last_name'],
                'first_name'           => $data['first_name'],
                'middle_initial'       => $data['middle_initial'] ?? null,
                'sex'                  => $data['sex'],
                'birthday'             => $data['birthday'],
                'age'                  => $age,
                'contact_no'           => $data['contact_no'],
                'business_name'        => $data['business_name'],
                'region'               => $addr['region'],
                'region_code'          => $addr['region_code'],
                'province'             => $addr['province_display'],
                'province_code'        => $addr['province_code'],
                'municipality'         => $addr['municipality'],
                'municipality_code'    => $addr['municipality_code'],
                'barangay'             => $addr['barangay'],
                'barangay_code'        => $addr['barangay_code'],
                'street'               => $data['street'],
                'house_number'         => $data['house_number'],
                'zip_code'             => $data['zip_code'] ?? null,
                'address'              => $address,
                'government_id_path'   => $idPath,
                'business_permit_path' => $permitPath,
                'status'               => 'pending',
            ]);

            return $user;
        });

        // Came from "Continue with Google": link it (the email is already verified).
        if ($google = GoogleSignup::pending($request)) {
            $user->forceFill(['google_id' => $google['id'], 'email_verified_at' => now()])->save();
            GoogleSignup::forget($request);
        }

        return redirect()->route('sc.login')->with('success',
            'Registration submitted. Please wait for the administrator\'s approval, which will be sent to your email at ' . $data['email'] . '.');
    }
}
