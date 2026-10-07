<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
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
            ->view('auth.login')
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

        // Block suspended or deactivated accounts
        $user = Auth::user();
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

        // Block accounts pending admin approval or rejected
        if ($user->isPending()) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages([
                'email' => 'Your account is pending administrator approval. You will be notified once it has been reviewed.',
            ]);
        }
        if ($user->isRejected()) {
            Auth::guard('web')->logout();
            throw ValidationException::withMessages([
                'email' => 'Your account application was rejected. Please contact support for more information.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        $user = Auth::user();

        // Record login timestamp. Keep the previous value in the session so the
        // profile page can show "last login" as the prior sign-in, not this one.
        $request->session()->put('previous_login_at', $user->last_login_at);
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        // Sorting center staff → SC panel
        if ($user->role === 'sorting_center') {
            return redirect()->route('sc.dashboard')
                ->with('success', 'Welcome back, ' . $user->name . '!');
        }

        // Approved couriers → courier portal (unless their courier record is suspended)
        if ($user->isCourier()) {
            $courier = $user->courier;
            if ($courier && $courier->isSuspended()) {
                Auth::guard('web')->logout();
                throw ValidationException::withMessages([
                    'email' => 'Your courier account has been suspended. Please contact the Logistics team.',
                ]);
            }
            return redirect()->route('courier.dashboard')
                ->with('success', 'Welcome back, ' . $user->name . '!');
        }

        // Courier applicants still awaiting review → status page with a clear message
        if ($user->isCourierPending()) {
            return redirect()->route('courier.status')
                ->with('info', 'Your courier registration is still awaiting approval from the Logistics team.');
        }

        // ALVY admins → ALVY admin panel
        if ($user->isAdmin()) {
            \App\Models\ActivityLog::record('admin_login', 'Admin signed in', $user->name . ' signed in.');
            
            // Check if this admin should go to logistics dashboard
            // You can modify this logic based on your user model or add a logistics role field
            if ($request->input('logistics') || $user->hasLogisticsRole()) {
                return redirect()->route('admin.logistics.dashboard')
                    ->with('success', 'Welcome to Logistics Dashboard, ' . $user->name . '!');
            }
            
            return redirect()->route('admin.dashboard')
                ->with('success', 'Welcome back, ' . $user->name . '!');
        }

        // Sellers → seller dashboard
        if ($user->isSeller()) {
            return redirect()->route('seller.dashboard')
                ->with('success', 'Welcome back, ' . $user->name . '!');
        }

        return redirect()->intended(route('home'))->with('success', 'Welcome back, ' . Auth::user()->name . '!');
    }

    public function destroy(Request $request)
    {
        $user = Auth::user();
        if ($user && $user->isAdmin()) {
            \App\Models\ActivityLog::record('admin_logout', 'Admin signed out', $user->name . ' signed out.');
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Add cache control headers to prevent accessing cached dashboard pages
        return response()
            ->redirectTo('/')
            ->with('success', 'You have been signed out.')
            ->header('Cache-Control', 'no-cache, no-store, max-age=0, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', 'Fri, 01 Jan 1990 00:00:00 GMT');
    }
}
