<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AccountController extends Controller
{
    public function index()
    {
        $user        = auth()->user();
        $application = SellerApplication::where('user_id', $user->id)->latest()->first();

        return view('seller.account', compact('user', 'application'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email,'.$user->id,
            'phone'        => 'nullable|string|max:30',
            'address'      => 'nullable|string|max:255',
            'city'         => 'nullable|string|max:120',
            'zip'          => 'nullable|string|max:20',
            'country'      => 'nullable|string|max:120',
            'avatar'       => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'password'     => 'nullable|string|min:8|confirmed',
        ]);

        if ($request->hasFile('avatar')) {
            $data['profile_photo_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        if ($request->filled('password')) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return back()->with('success', 'Account updated successfully.');
    }
}
