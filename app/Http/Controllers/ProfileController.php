<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
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

    /** Orders page */
    public function orders()
    {
        $orders = auth()->user()
            ->orders()
            ->with('items.book')
            ->latest()
            ->get();

        return view('profile.orders', compact('orders'));
    }

    /** Notifications page — admin announcements + order status updates */
    public function notifications()
    {
        $user = auth()->user();

        // ── Stored notifications (e.g. admin announcements) ──────
        $stored = $user->notifications()->latest()->get()->map(function ($n) {
            $icon = match ($n->type) {
                'warning'     => 'x',
                'maintenance' => 'bell',
                'promo'       => 'bell',
                default       => 'bell',
            };

            return (object) [
                'kind'       => 'announcement',
                'order_id'   => null,
                'title'      => $n->title,
                'body'       => $n->body,
                'icon'       => $icon,
                'is_unread'  => is_null($n->read_at),
                'thumbnail'  => null,
                'item_name'  => null,
                'at'         => $n->created_at,
            ];
        });

        // ── Order status updates ─────────────────────────────────
        $orders = $user->orders()->with('items.book')->latest('updated_at')->get();

        $orderNotes = $orders->map(function ($order) {
            $meta = match (strtolower($order->status)) {
                'pending'    => ['title' => 'Order placed',      'body' => 'We have received your order and it is awaiting processing.',       'icon' => 'clock'],
                'processing' => ['title' => 'Order confirmed',   'body' => 'Your order is being prepared by the seller.',                     'icon' => 'box'],
                'shipped'    => ['title' => 'Order shipped',     'body' => 'Your order is on its way. Track its progress in My Purchases.',   'icon' => 'truck'],
                'delivered'  => ['title' => 'Order delivered',   'body' => 'Your order has been delivered. Enjoy!',                           'icon' => 'check'],
                'cancelled'  => ['title' => 'Order cancelled',   'body' => $order->cancellation_reason ?: 'Your order has been cancelled.',   'icon' => 'x'],
                default      => ['title' => 'Order update',      'body' => 'There is an update on your order.',                               'icon' => 'bell'],
            };

            $firstItem = $order->items->first();

            return (object) [
                'kind'       => 'order',
                'order_id'   => $order->id,
                'title'      => $meta['title'],
                'body'       => $meta['body'],
                'icon'       => $meta['icon'],
                'is_unread'  => false,
                'thumbnail'  => $firstItem?->book?->image,
                'item_name'  => $firstItem?->book?->title,
                'at'         => $order->updated_at,
            ];
        });

        // Merge, newest first
        $notifications = $stored->concat($orderNotes)
            ->sortByDesc('at')
            ->values();

        // Mark stored notifications as read now that they've been viewed
        $user->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return view('profile.notifications', compact('notifications'));
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
     * Update profile info: profile picture, bio, gender (sex),
     * birthday, phone, email. (Address fields intentionally excluded —
     * addresses are managed on the dedicated Addresses page.)
     */
    public function update(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'    => 'nullable|string|max:30',
            'bio'      => 'nullable|string|max:500',
            'sex'      => 'nullable|in:Male,Female',
            'birthday' => 'nullable|date|before:today',
            'avatar'   => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        // Handle profile picture upload
        if ($request->hasFile('avatar')) {
            // Remove the previous photo if it exists
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $data['profile_photo_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        // avatar is not a DB column
        unset($data['avatar']);

        $user->update($data);

        return back()->with('success', 'Profile updated successfully.');
    }

    /**
     * Update account details: username, phone, email.
     * (Password handled separately by changePassword.)
     */
    public function updateAccount(Request $request)
    {
        $user = auth()->user();

        $data = $request->validate([
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'phone'    => 'nullable|string|max:30',
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $user->update($data);

        return back()->with('success', 'Account details updated successfully.');
    }

    /** Change password */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password'         => 'required|string|min:8|confirmed',
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
