<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    /**
     * Admin profile page — personal / account information only.
     * No password or security data is shown here.
     */
    public function edit()
    {
        $user = auth()->user();

        // "Last login" shows the sign-in *before* the current one, captured at
        // login time. Falls back to the stored timestamp if the session is missing.
        $lastLogin = session('previous_login_at', $user->last_login_at);

        return view('admin.account.edit', [
            'user'          => $user,
            'maskedEmail'   => $this->maskEmail($user->email),
            'lastLogin'     => $lastLogin,
        ]);
    }

    /**
     * Update the admin's personal information (not password).
     */
    public function update(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'username' => 'nullable|string|max:255|alpha_dash|unique:users,username,' . $user->id,
            'email'    => 'required|email|max:255|unique:users,email,' . $user->id,
            'avatar'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $payload = [
            'name'     => $data['name'],
            'username' => $data['username'] ?? $user->username,
            'email'    => $data['email'],
        ];

        if ($request->hasFile('avatar')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $payload['profile_photo_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($payload);

        return redirect()->route('admin.account.edit')
            ->with('success', 'Profile updated successfully.');
    }

    /**
     * Security page — password change (+ 2FA / active sessions placeholders).
     */
    public function security()
    {
        return view('admin.account.security', [
            'user' => auth()->user(),
        ]);
    }

    /**
     * Update the admin's password. The current password must be verified and
     * the actual password / hash is never exposed to the UI.
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.current_password' => 'The current password you entered is incorrect.',
        ]);

        auth()->user()->update([
            'password' => Hash::make($request->password),
        ]);

        // Record the event only — never the password itself.
        \App\Models\ActivityLog::record(
            'admin_password_changed',
            'Password Changed',
            'Admin account password was changed.'
        );

        return redirect()->route('admin.account.security')
            ->with('success', 'Your password has been changed successfully.');
    }

    /**
     * Mask an email so only the first character, the "@", and the TLD portion
     * remain visible. Example: john@example.com -> j********@****.com
     */
    private function maskEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) {
            return '********';
        }

        [$local, $domain] = explode('@', $email, 2);

        $firstChar   = mb_substr($local, 0, 1);
        $maskedLocal = $firstChar . str_repeat('*', max(1, mb_strlen($local) - 1));

        // Split the domain into name + extension (e.g. "example" + ".com").
        $dotPos = strrpos($domain, '.');
        if ($dotPos === false) {
            return $maskedLocal . '@' . str_repeat('*', mb_strlen($domain));
        }

        $domainName = substr($domain, 0, $dotPos);
        $tld        = substr($domain, $dotPos); // includes the leading dot, e.g. ".com"

        $maskedDomain = str_repeat('*', max(1, mb_strlen($domainName)));

        return $maskedLocal . '@' . $maskedDomain . $tld;
    }
}
