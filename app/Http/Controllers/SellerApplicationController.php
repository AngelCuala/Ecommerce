<?php

namespace App\Http\Controllers;

use App\Models\SellerApplication;
use App\Support\SellerApplicationRules;
use Illuminate\Http\Request;

/** "Become a Seller": an existing (logged-in) buyer applies to open a shop. */
class SellerApplicationController extends Controller
{
    /** Show the application form */
    public function create()
    {
        $existing = SellerApplication::where('user_id', auth()->id())->latest()->first();

        return view('seller.apply', [
            'existing'    => $existing,
            'categories'  => SellerApplicationRules::categories(),
            'sellerTypes' => SellerApplication::SELLER_TYPES,
        ]);
    }

    /** Store new application */
    public function store(Request $request)
    {
        // Prevent duplicate pending/approved applications
        $existing = SellerApplication::where('user_id', auth()->id())
            ->whereIn('status', ['unverified', 'pending', 'approved'])
            ->first();

        if ($existing) {
            return back()->with('error', 'You already have an active or approved application.');
        }

        SellerApplicationRules::normalize($request);

        $data = $request->validate(
            SellerApplicationRules::rules($request->user()) + [
                // Confirm it's really the account owner applying
                'password' => ['required', 'string', 'current_password', 'confirmed'],
            ],
            SellerApplicationRules::messages(),
            SellerApplicationRules::attributes(),
        );

        SellerApplication::updateOrCreate(
            ['user_id' => auth()->id()],
            SellerApplicationRules::applicationData($request, $data) + [
                'status'           => 'pending',
                'rejection_reason' => null,
            ],
        );

        return redirect()->route('profile.show')
            ->with('success', 'Your seller application has been submitted! We will review it shortly.');
    }
}
