<?php

namespace App\Http\Controllers;

use App\Mail\ContactChangeMail;
use App\Models\Order;
use App\Models\UserNotification;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /** Minutes a code for an email / phone change is valid. */
    private const CODE_MINUTES = 5;   // a contact-change code expires after 5 minutes
    private const CODE_ATTEMPTS = 5;

    /** Overview / landing */
    public function show()
    {
        return view('profile.index', ['user' => auth()->user()]);
    }

    /** Personal Info now merged into My Profile — redirect for backward compatibility */
    public function personalInfo()
    {
        return redirect()->route('profile.show');
    }

    /** My Purchases, by tab (To Pay, To Ship, To Receive, Completed, Cancelled, Return/Refund). */
    public function orders(Request $request)
    {
        $tab = array_key_exists($request->query('tab'), Order::TABS) ? $request->query('tab') : 'all';

        $base = auth()->user()->orders();
        $counts = collect(Order::TABS)->map(fn ($t) => $t['statuses'] ? (clone $base)->whereIn('status', $t['statuses'])->count() : 0);

        $orders = (clone $base)
            ->when($tab !== 'all', fn ($q) => $q->whereIn('status', Order::TABS[$tab]['statuses'] ?: ['—']))
            ->with(['items.product', 'items.review', 'seller.sellerApplication', 'returnRequests'])
            ->latest()
            ->paginate(10)->withQueryString();

        return view('profile.orders', compact('orders', 'tab', 'counts'));
    }

    /** One order: items, totals, timeline and the actions the buyer can take. */
    public function orderShow(int $id)
    {
        $order = auth()->user()->orders()
            ->with(['items.product', 'items.review', 'events', 'seller.sellerApplication', 'returnRequests', 'platformVoucher', 'shippingVoucher'])
            ->findOrFail($id);

        return view('profile.order-show', compact('order'));
    }

    /** Notifications: newest first. Opening one marks only that one as read. */
    public function notifications()
    {
        $notifications = auth()->user()->notifications()->latest()->paginate(20);

        return view('profile.notifications', compact('notifications'));
    }

    /** Open a notification: mark it read and go to what it is about. */
    public function openNotification(int $id)
    {
        $note = UserNotification::where('user_id', auth()->id())->findOrFail($id);
        if ($note->isUnread()) {
            $note->update(['read_at' => now()]);
        }

        return $note->link ? redirect($note->link) : redirect()->route('profile.notifications');
    }

    public function readAllNotifications()
    {
        auth()->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    /** Settings page */
    public function settings()
    {
        return view('profile.settings', ['user' => auth()->user()]);
    }

    /** Addresses page */
    public function addresses()
    {
        $addresses = auth()->user()->addresses()->orderByDesc('is_default')->oldest()->get();
        return view('profile.addresses', compact('addresses'));
    }

    /** Accounts & Security page */
    public function security()
    {
        return view('profile.security', ['user' => auth()->user()]);
    }

    /**
     * Update profile info: name, photo, bio, sex, birthday.
     * The email address and phone number change on Accounts & Security, with a code.
     */
    public function update(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            // Letters (any language), spaces and . - ' only, at least 2 characters (e.g. "Juan Miguel Jr.").
            'name'     => ['required', 'string', 'min:2', 'max:100', "regex:/^\pL[\pL\s.'\-]*$/u"],
            'bio'      => 'nullable|string|max:500',
            'sex'      => 'nullable|in:Male,Female',
            'birthday' => 'nullable|date|before:today',
            'avatar'   => 'nullable|file|mimes:jpg,jpeg,png|mimetypes:image/jpeg,image/png|max:5120',
        ], [
            'name.required'    => 'Please enter your name.',
            'name.min'         => 'Your name must be at least 2 characters.',
            'name.regex'       => 'Your name may only contain letters, spaces and . - \' (no numbers or symbols).',
            'avatar.mimes'     => 'The profile photo must be a JPG or PNG image.',
            'avatar.mimetypes' => 'The profile photo must be a real JPG or PNG image.',
            'avatar.max'       => 'The profile photo can be at most 5 MB.',
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $data['profile_photo_path'] = $request->file('avatar')->store('avatars', 'public');
        }
        unset($data['avatar']);

        $user->update($data);

        return back()->with('success', 'Profile updated successfully.');
    }

    /** Username (the email and phone have their own verified flows). */
    public function updateAccount(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
        ]);

        $user->update($data);

        return back()->with('success', 'Account details updated successfully.');
    }

    // ── Email / phone change (confirmed with a code) ─────────

    /** Step 1: the new email gets a code; the old email keeps working until it is entered. */
    public function changeEmail(Request $request)
    {
        $user = auth()->user();
        $request->merge(['email' => strtolower(trim((string) $request->email))]);

        $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id), Rule::notIn([strtolower($user->email)])],
        ], [
            'email.unique' => 'That email address is already in use by another account.',
            'email.not_in' => 'That is already your email address.',
        ]);

        $user->forceFill(['pending_email' => $request->email, 'pending_phone' => null])->save();

        return $this->sendContactCode($request->email, 'email address', $request->email);
    }

    /** Step 1: a new phone number is confirmed with a code sent to the account's email. */
    public function changePhone(Request $request)
    {
        $user = auth()->user();
        $request->merge(['phone' => PhoneNumber::normalize($request->input('phone'))]);

        $request->validate([
            'phone' => ['required', 'regex:' . PhoneNumber::PATTERN, Rule::notIn([$user->phone]),
                function ($attr, $value, $fail) use ($user) {
                    $taken = \App\Models\User::whereKeyNot($user->id)->where(fn ($q) => $q->where('phone', $value)->orWhere('contact_no', $value))->exists();
                    if ($taken) {
                        $fail('That mobile number is already used by another account.');
                    }
                }],
        ], [
            'phone.regex'  => 'Enter a valid PH mobile number: 09XXXXXXXXX (11 digits) or +639XXXXXXXXX.',
            'phone.not_in' => 'That is already your phone number.',
        ]);

        $user->forceFill(['pending_phone' => $request->phone, 'pending_email' => null])->save();

        return $this->sendContactCode($user->email, 'phone number', $request->phone);
    }

    /** Step 2: the code makes the change. */
    public function verifyContact(Request $request)
    {
        $request->validate(['code' => 'required|digits:6']);
        $user = auth()->user();

        if (! $user->pending_email && ! $user->pending_phone) {
            return back()->with('error', 'There is no change waiting for a code.');
        }
        if (! $user->contact_otp || ! $user->contact_otp_expires_at || $user->contact_otp_expires_at->isPast()) {
            return back()->withErrors(['code' => 'This code has expired. Send a new one.']);
        }
        if ($user->contact_otp_attempts >= self::CODE_ATTEMPTS) {
            return back()->withErrors(['code' => 'Too many wrong codes. Send a new one.']);
        }
        if (! Hash::check($request->code, $user->contact_otp)) {
            $user->increment('contact_otp_attempts');
            return back()->withErrors(['code' => 'That code is not correct.']);
        }

        if ($user->pending_email) {
            // Someone may have taken the address while the code was on its way.
            if (\App\Models\User::where('email', $user->pending_email)->whereKeyNot($user->id)->exists()) {
                $user->forceFill(['pending_email' => null, 'contact_otp' => null])->save();
                return back()->withErrors(['email' => 'That email address is already in use by another account.']);
            }
            $message = 'Your email address is now ' . $user->pending_email . '.';
            $user->forceFill(['email' => $user->pending_email, 'email_verified_at' => now()]);
        } else {
            $message = 'Your phone number is now ' . $user->pending_phone . '.';
            $user->forceFill(['phone' => $user->pending_phone, 'contact_no' => $user->pending_phone]);
        }

        $user->forceFill(['pending_email' => null, 'pending_phone' => null, 'contact_otp' => null,
            'contact_otp_expires_at' => null, 'contact_otp_attempts' => 0])->save();

        return back()->with('success', $message);
    }

    public function resendContactCode()
    {
        $user = auth()->user();
        if ($user->pending_email) {
            return $this->sendContactCode($user->pending_email, 'email address', $user->pending_email);
        }
        if ($user->pending_phone) {
            return $this->sendContactCode($user->email, 'phone number', $user->pending_phone);
        }
        return back();
    }

    public function cancelContactChange()
    {
        auth()->user()->forceFill(['pending_email' => null, 'pending_phone' => null, 'contact_otp' => null,
            'contact_otp_expires_at' => null, 'contact_otp_attempts' => 0])->save();

        return back()->with('success', 'Change cancelled. Nothing was changed.');
    }

    private function sendContactCode(string $to, string $what, string $newValue)
    {
        $user = auth()->user();
        $code = (string) random_int(100000, 999999);

        $user->forceFill([
            'contact_otp'            => Hash::make($code),
            'contact_otp_expires_at' => now()->addMinutes(self::CODE_MINUTES),
            'contact_otp_attempts'   => 0,
        ])->save();

        try {
            Mail::to($to)->send(new ContactChangeMail($user, $code, $what, $newValue, self::CODE_MINUTES));
        } catch (\Throwable $e) {
            Log::error('Contact change code could not be sent: ' . $e->getMessage());
            return back()->with('error', 'We could not send the code right now. Please try again in a moment.');
        }

        return back()->with('success', "We sent a 6-digit code to {$to}. Enter it below to confirm your new {$what}.");
    }

    /** Change password */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => ['required', 'string', 'confirmed', 'different:current_password',
                                   Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ], [
            'password.confirmed' => 'The new password and its confirmation do not match.',
            'password.different' => 'The new password must be different from your current one.',
        ]);

        $user = auth()->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.'])
                ->with('password_error', true);
        }

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password changed successfully.');
    }
}
