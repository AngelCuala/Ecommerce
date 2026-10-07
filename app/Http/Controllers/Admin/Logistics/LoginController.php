<?php

namespace App\Http\Controllers\Admin\Logistics;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create()
    {
        return view('admin.logistics.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        $user = Auth::user();

        // Only allow admin users to access logistics
        if (!$user->isAdmin()) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages([
                'email' => 'You do not have permission to access the logistics panel.',
            ]);
        }

        // Block suspended or deactivated accounts
        if ($user->isSuspended()) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages([
                'email' => 'Your account has been suspended. Please contact support.',
            ]);
        }
        if ($user->isDeactivated()) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated. Please contact support.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        // Record login timestamp
        $request->session()->put('previous_login_at', $user->last_login_at);
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        \App\Models\ActivityLog::record('logistics_login', 'Logistics admin signed in', $user->name . ' signed in to logistics panel.');
        
        return redirect()->route('logistics.dashboard')
            ->with('success', 'Welcome to Logistics Dashboard, ' . $user->name . '!');
    }
}
