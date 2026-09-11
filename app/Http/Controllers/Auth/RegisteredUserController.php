<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class RegisteredUserController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'last_name'      => 'required|string|max:255',
            'first_name'     => 'required|string|max:255',
            'middle_initial' => 'nullable|string|max:5',
            'username'       => 'required|string|max:50|unique:users,username',
            'password'       => 'required|string|min:8|confirmed',

            'sex'            => 'required|in:Male,Female',
            'email'          => 'required|string|email|max:255|unique:users,email',
            'contact_no'     => 'required|string|max:20',

            'birthday'       => 'required|date|before:today',

            'province'       => 'required|string|max:255',
            'municipality'   => 'required|string|max:255',
            'barangay'       => 'required|string|max:255',
            'street'         => 'required|string|max:255',
            'house_number'   => 'required|string|max:50',
            'zip_code'       => 'nullable|string|max:20',

            'valid_id'       => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB

            'terms'          => 'accepted',
        ]);

        // Server-side age calculation — never trust the client's JS value.
        $age = Carbon::parse($validated['birthday'])->age;

        $validIdPath = $request->file('valid_id')->store('valid-ids', 'local');

        $user = User::create([
            'name'            => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'first_name'      => $validated['first_name'],
            'last_name'       => $validated['last_name'],
            'middle_initial'  => $validated['middle_initial'] ?? null,
            'username'        => $validated['username'],
            'email'           => $validated['email'],
            'password'        => Hash::make($validated['password']),
            'role'            => 'buyer',

            'sex'             => $validated['sex'],
            'contact_no'      => $validated['contact_no'],
            'birthday'        => $validated['birthday'],
            'age'             => $age,

            'province'        => $validated['province'],
            'municipality'    => $validated['municipality'],
            'barangay'        => $validated['barangay'],
            'street'          => $validated['street'],
            'house_number'    => $validated['house_number'],

            'valid_id_path'   => $validIdPath,
            'approval_status' => 'pending',
        ]);

        event(new Registered($user));

        // Don't auto-login — account is pending admin approval.
        return redirect()->route('login')
            ->with('success', 'Registration submitted successfully. Your account is currently pending administrator approval. Please wait for an email regarding your registration status.');
    }
}