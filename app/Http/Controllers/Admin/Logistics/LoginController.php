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
        // If user is already authenticated and is admin, redirect to logistics dashboard
        if (auth()->check()) {
            $user = auth()->user();
            if ($user->isAdmin()) {
                return redirect()->route('admin.logistics.dashboard');
            }
            // If authenticated but not admin, redirect to their appropriate dashboard
            return $this->redirectToUserDashboard($user);
        }

        // Add cache control headers to prevent browser back button issues
        return response()
            ->view('admin.logistics.login')
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
        
        return redirect()->route('admin.logistics.dashboard')
            ->with('success', 'Welcome to Logistics Dashboard, ' . $user->name . '!');
    }
}
