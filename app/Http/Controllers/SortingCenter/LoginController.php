<?php

namespace App\Http\Controllers\SortingCenter;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create()
    {
        // If user is already authenticated, redirect to appropriate dashboard
        if (auth()->check()) {
            $user = auth()->user();
            if ($user->role === 'sorting_center') {
                return redirect()->route('sc.dashboard');
            }
            if ($user->role === 'admin') {
                return redirect()->route('admin.dashboard');
            }
            // Redirect other roles to their dashboards
            return $this->redirectToUserDashboard($user);
        }

        // Add cache control headers to prevent browser back button issues
        return response()
            ->view('sorting-center.login')
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

        // Only sorting_center role may access this portal
        if ($user->role !== 'sorting_center') {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'You do not have access to the Sorting Center portal.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->forget('url.intended');

        return redirect()->route('sc.dashboard')
            ->with('success', 'Welcome back, ' . $user->name . '!');
    }
}
