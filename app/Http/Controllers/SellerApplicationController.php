<?php

namespace App\Http\Controllers;

use App\Models\SellerApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SellerApplicationController extends Controller
{
    /** Show the application form */
    public function create()
    {
        $existing = SellerApplication::where('user_id', auth()->id())->latest()->first();

        return view('seller.apply', compact('existing'));
    }

    /** Store new application */
    public function store(Request $request)
    {
        // Prevent duplicate pending/approved applications
        $existing = SellerApplication::where('user_id', auth()->id())
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existing) {
            return back()->with('error', 'You already have an active or approved application.');
        }

        $data = $request->validate([
            'full_name'       => 'nullable|string|max:255',
            'last_name'       => 'required|string|max:100',
            'first_name'      => 'required|string|max:100',
            'middle_initial'  => 'nullable|string|max:5',
            'sex'             => 'required|in:Male,Female,Other',
            'birthday'        => 'required|date|before:today',
            'shop_name'       => 'required|string|max:255',
            'business_name'   => 'nullable|string|max:255',
            'line_of_business'=> 'required|string|max:255',
            'phone'           => 'required|string|max:30',
            'province'        => 'required|string|max:120',
            'municipality'    => 'required|string|max:120',
            'barangay'        => 'required|string|max:120',
            'street'          => 'required|string|max:255',
            'house_number'    => 'required|string|max:50',
            'region'          => 'nullable|string|max:120',
            'zip_code'        => 'nullable|string|max:20',
            'government_id'   => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'business_permit' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
            'description'     => 'required|string|max:1000',
        ]);

        $data['age'] = \Carbon\Carbon::parse($data['birthday'])->age;
        $data['full_name'] = trim($data['first_name'] . ' ' . $data['last_name']);
        $data['address'] = implode(', ', array_filter([$data['street'], $data['house_number'], $data['barangay'], $data['municipality'], $data['province']]));

        $path = $request->file('government_id')->store('seller-ids', 'public');
        $businessPermitPath = $request->hasFile('business_permit')
            ? $request->file('business_permit')->store('seller-permits', 'public')
            : null;

        SellerApplication::create([
            'user_id'             => auth()->id(),
            'last_name'           => $data['last_name'],
            'first_name'          => $data['first_name'],
            'middle_initial'      => $data['middle_initial'] ?? null,
            'sex'                 => $data['sex'],
            'birthday'            => $data['birthday'],
            'age'                 => $data['age'],
            'full_name'           => $data['full_name'],
            'shop_name'           => $data['shop_name'],
            'business_name'       => $data['business_name'] ?? null,
            'line_of_business'    => $data['line_of_business'],
            'phone'               => $data['phone'],
            'province'            => $data['province'],
            'municipality'        => $data['municipality'],
            'barangay'            => $data['barangay'],
            'street'              => $data['street'],
            'house_number'        => $data['house_number'],
            'address'             => $data['address'],
            'government_id_path'  => $path,
            'business_permit_path'=> $businessPermitPath,
            'description'         => $data['description'],
            'status'              => 'pending',
        ]);

        return redirect()->route('profile.show')
            ->with('success', 'Your seller application has been submitted! We will review it shortly.');
    }
}
