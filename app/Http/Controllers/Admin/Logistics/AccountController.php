<?php

namespace App\Http\Controllers\Admin\Logistics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function edit()
    {
        $view = request()->routeIs('admin.*') ? 'admin.account.edit' : 'logistics.account.edit';
        return view($view, ['user' => auth()->user()]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $user->update($request->only('name', 'email'));

        // Handle password change in the same form if provided
        if ($request->filled('current_password') || $request->filled('password')) {
            $request->validate([
                'current_password' => 'required|current_password',
                'password'         => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(8)],
            ]);
            $user->update(['password' => \Illuminate\Support\Facades\Hash::make($request->password)]);
        }

        return back()->with('success', 'Account details updated.');
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password'         => ['required', 'confirmed', Password::min(8)],
        ]);

        auth()->user()->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password changed successfully.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success', 'You have been logged out.');
    }
}
