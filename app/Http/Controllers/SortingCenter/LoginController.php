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
        // Already logged-in sorting center staff go straight to dashboard
        if (auth()->check() && auth()->user()->role === 'sorting_center') {
            return redirect()->route('sc.dashboard');
        }

        // Admins have their own panel
        if (auth()->check() && auth()->user()->role === 'admin') {
            return redirect()->route('admin.dashboard');
        }

        return view('sorting-center.login');
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
