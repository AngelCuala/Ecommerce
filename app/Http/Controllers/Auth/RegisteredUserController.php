<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
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
            ->view('auth.register')
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

        // Base validation rules
        $rules = [
            'role'           => 'required|in:buyer,seller,courier,logistics',
            'first_name'     => 'required|string|max:255',
            'last_name'      => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:5',
            'email'          => 'required|string|email|max:255|unique:users,email',
            'password'       => 'required|string|min:8',
            'sex'            => 'required|in:male,female,other',
        ];

        // Role-specific validation rules
        if ($role === 'seller') {
            $rules['id_image'] = 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120';
            $rules['business_permit'] = 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120';
        } elseif ($role === 'courier') {
            $rules['vehicle_type'] = 'required|in:motorcycle,tricycle,car';
            $rules['plate_number'] = 'required|string|max:50';
            $rules['or_cr_image'] = 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120';
            $rules['vehicle_reg_image'] = 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120';
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

        $validated = $request->validate($rules);

        // Create the user with basic info
        $userData = [
            'name'            => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'first_name'      => $validated['first_name'],
            'last_name'       => $validated['last_name'],
            'middle_initial'  => $validated['middle_initial'] ?? null,
            'email'           => $validated['email'],
            'password'        => Hash::make($validated['password']),
            'role'            => $role,
            'sex'             => ucfirst($validated['sex']), // Capitalize first letter
            'approval_status' => 'pending',
            'birthday'        => '2000-01-01', // Default birthday
            'contact_no'      => '', // Default empty
            'province'        => '',
            'municipality'    => '',
            'barangay'        => '',
        ];

        // Handle file uploads for sellers
        if ($role === 'seller' && $request->hasFile('id_image')) {
            $userData['valid_id_path'] = $request->file('id_image')->store('seller-ids', 'public');
        }

        // Handle logistics-specific data
        if ($role === 'logistics') {
            $userData['province'] = $validated['province'];
            $userData['municipality'] = $validated['municipality'];
            $userData['barangay'] = $validated['barangay'];
            $userData['street'] = $validated['street'] ?? '';
            $userData['house_number'] = $validated['house_number'] ?? '';
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
                'birthday'       => '2000-01-01',
                'age'            => 24,
                'vehicle_type'   => $validated['vehicle_type'],
                'plate_number'   => strtoupper($validated['plate_number']),
                'status'         => 'pending',
                'province'       => '',
                'municipality'   => '',
                'barangay'       => '',
                'contact_no'     => '',
            ];

            if ($request->hasFile('or_cr_image')) {
                $courierData['or_cr_path'] = $request->file('or_cr_image')->store('courier-licenses', 'public');
            }
            if ($request->hasFile('vehicle_reg_image')) {
                $courierData['id_license_path'] = $request->file('vehicle_reg_image')->store('courier-vehicle-regs', 'public');
            }

            \App\Models\Courier::create($courierData);
        }

        event(new Registered($user));

        // Success message based on role
        $message = $role === 'courier'
            ? 'Thank you for registering. Please wait for the Logistics/Sorting Center\'s approval — you will be notified via email once your account is approved.'
            : 'Thank you for registering. Please wait for admin approval — you will be notified via email once your account is approved.';

        return redirect()->route('login')->with('success', $message);
    }
}
