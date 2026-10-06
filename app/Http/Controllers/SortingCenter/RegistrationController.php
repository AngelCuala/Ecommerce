<?php

namespace App\Http\Controllers\SortingCenter;

use App\Http\Controllers\Controller;
use App\Models\SortingCenterApplication;
use App\Models\User;
use App\Services\PsgcDirectory;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Logistics / Sorting Center registration (ERP). Creates a login that stays locked
 * (role `sorting_center_pending`, approval_status `pending`) until an administrator
 * approves it. The registered PSA municipality becomes the center's coverage area.
 */
class RegistrationController extends Controller
{
    public function create()
    {
        return view('sorting-center.register');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'last_name'       => 'required|string|max:100',
            'first_name'      => 'required|string|max:100',
            'middle_initial'  => 'nullable|string|max:5',
            'sex'             => 'required|in:Male,Female',
            'email'           => 'required|string|email|max:255|unique:users,email',
            'contact_no'      => ['required', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'birthday'        => 'required|date|before:today',
            'password'        => 'required|string|min:8|confirmed',

            'business_name'   => 'required|string|max:150',

            'province'        => 'nullable|string|max:120', // not applicable for NCR / highly urbanized cities
            'municipality'    => 'required|string|max:120',
            'barangay'        => 'required|string|max:120',
            'street'          => 'required|string|max:255',
            'house_number'    => 'required|string|max:50',
            'zip_code'        => 'nullable|string|max:20',

            'government_id'   => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'business_permit' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',

            'terms'           => 'accepted',
        ] + PsgcDirectory::codeRules(), [
            'contact_no.regex'      => 'Enter a valid contact number.',
            'business_permit.required' => 'Please upload your business / DTI permit.',
        ]);

        // Address must be a real PSA chain; official names and codes come from the dataset.
        $addr = app(PsgcDirectory::class)->resolve($request->all(), true, $data['barangay']);
        if (! $addr['municipality_code']) {
            throw ValidationException::withMessages([
                'municipality' => "{$addr['municipality']} has no PSA correspondence code yet, so a sorting center cannot be registered there.",
            ]);
        }

        $age = Carbon::parse($data['birthday'])->age; // server-side; never trust the client value

        $idPath     = $request->file('government_id')->store('sorting-center-ids', 'local');
        $permitPath = $request->file('business_permit')->store('sorting-center-permits', 'local');

        $address = implode(', ', array_filter([
            $data['house_number'], $data['street'], $addr['barangay'], $addr['municipality'], $addr['province_display'],
        ]));

        DB::transaction(function () use ($data, $addr, $age, $idPath, $permitPath, $address) {
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
        });

        return redirect()->route('sc.login')->with('success',
            'Registration submitted. Please wait for the administrator\'s approval, which will be sent to ' . $data['email'] . '.');
    }
}
