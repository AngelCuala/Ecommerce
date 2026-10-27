<?php

namespace App\Http\Controllers\Courier;

use App\Http\Controllers\Controller;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    /** Profile page — personal / vehicle info (no password). */
    public function index()
    {
        $user    = auth()->user();
        $courier = DeliveryController::currentCourier();

        return view('courier.account', [
            'user'        => $user,
            'courier'     => $courier,
            'maskedEmail' => $this->maskEmail($user->email),
        ]);
    }

    /** Update the editable profile fields (contact no. + avatar). */
    public function update(Request $request)
    {
        $user    = auth()->user();
        $courier = $user->courier;

        $request->merge(['contact_no' => PhoneNumber::normalize($request->input('contact_no'))]);
        $data = $request->validate([
            'contact_no' => ['required', 'string', 'regex:' . PhoneNumber::PATTERN],
            'avatar'     => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ], ['contact_no.regex' => PhoneNumber::MESSAGE]);

        if ($request->hasFile('avatar')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $user->update(['profile_photo_path' => $request->file('avatar')->store('avatars', 'public')]);
        }

        // Contact number is duplicated on both records for convenience.
        $user->update(['phone' => $data['contact_no']]);
        if ($courier) {
            $courier->update(['contact_no' => $data['contact_no']]);
        }

        return redirect()->route('courier.account')->with('success', 'Profile updated successfully.');
    }

    /** Security page — password change. */
    public function security()
    {
        return view('courier.security', ['user' => auth()->user()]);
    }

    /** Update password (verifies current password; hash never exposed). */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.current_password' => 'The current password you entered is incorrect.',
        ]);

        // The new password is used once the code emailed to the account is entered.
        return \App\Http\Controllers\PasswordChangeController::start(auth()->user(), $request->password, redirect()->route('courier.account.security'));
    }

    /** Mask an email: j********@****.com */
    private function maskEmail(?string $email): string
    {
        if (! $email || ! str_contains($email, '@')) return '********';

        [$local, $domain] = explode('@', $email, 2);
        $maskedLocal = mb_substr($local, 0, 1) . str_repeat('*', max(1, mb_strlen($local) - 1));

        $dot = strrpos($domain, '.');
        if ($dot === false) {
            return $maskedLocal . '@' . str_repeat('*', mb_strlen($domain));
        }
        $tld = substr($domain, $dot);
        return $maskedLocal . '@' . str_repeat('*', max(1, $dot)) . $tld;
    }
}
