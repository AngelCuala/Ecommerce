<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /** Overview / landing */
    public function show()
    {
        return view('profile.index', ['user' => auth()->user()]);
    }

    /** Personal Info page */
    public function personalInfo()
    {
        return view('profile.personal-info', ['user' => auth()->user()]);
    }

    /** Orders page */
    public function orders()
    {
        $orders = auth()->user()
            ->orders()
            ->with('items.book')
            ->latest()
            ->get();

        return view('profile.orders', compact('orders'));
    }

    /** Settings page */
    public function settings()
    {
        return view('profile.settings', ['user' => auth()->user()]);
    }

    /** Addresses page */
    public function addresses()
    {
        $addresses = auth()->user()->addresses()->orderByDesc('is_default')->oldest()->get();
        return view('profile.addresses', compact('addresses'));
    }

    /** Update personal info */
    public function update(Request $request)
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255|unique:users,email,' . auth()->id(),
            'phone'   => 'nullable|string|max:30',
            'address' => 'nullable|string|max:255',
            'city'    => 'nullable|string|max:120',
            'zip'     => 'nullable|string|max:20',
            'country' => 'nullable|string|max:120',
        ]);

        auth()->user()->update($data);

        return back()->with('success', 'Profile updated successfully.');
    }

    /** Change password */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password changed successfully.');
    }
}
