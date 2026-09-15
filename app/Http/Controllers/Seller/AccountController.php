<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\SellerApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AccountController extends Controller
{
    public function index()
    {
        $user        = auth()->user();
        $application = SellerApplication::where('user_id', $user->id)->latest()->first();

        return view('seller.account', compact('user', 'application'));
    }

    public function update(Request $request)
    {
        $user        = auth()->user();
        $application = SellerApplication::where('user_id', $user->id)->latest()->first();

        $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email,' . $user->id,
            'phone'     => 'nullable|string|max:30',
            'address'   => 'nullable|string|max:255',
            'city'      => 'nullable|string|max:120',
            'zip'       => 'nullable|string|max:20',
            'country'   => 'nullable|string|max:120',
            'avatar'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'password'  => 'nullable|string|min:8|confirmed',
            'shop_name' => 'nullable|string|max:255',
        ]);

        // ── Profile photo ──────────────────────────────────────
        $userData = $request->only(['name', 'email', 'phone', 'address', 'city', 'zip', 'country']);

        if ($request->hasFile('avatar')) {
            // Delete old photo if it exists
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $userData['profile_photo_path'] = $request->file('avatar')->store('avatars', 'public');
        }

        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }

        $user->update($userData);

        // ── Shop name change (3x per calendar month) ──────────
        if ($application && $request->filled('shop_name')) {
            $newName = trim($request->shop_name);

            if ($newName !== $application->shop_name) {
                if (! $application->canChangeShopName()) {
                    return back()
                        ->withInput()
                        ->withErrors(['shop_name' =>
                            'You have used all 3 shop name changes this month. ' .
                            'You can change it again after ' .
                            $application->shopNameResetsAt()->format('M d, Y') . '.'
                        ]);
                }

                // Reset counter if we're in a new calendar month
                $changesThisMonth = $application->shop_name_changes_this_month ?? 0;
                if ($application->shop_name_last_changed_at &&
                    $application->shop_name_last_changed_at->month !== now()->month) {
                    $changesThisMonth = 0;
                }

                $application->update([
                    'shop_name'                    => $newName,
                    'shop_name_changes_this_month' => $changesThisMonth + 1,
                    'shop_name_last_changed_at'    => now(),
                ]);
            }
        }

        return back()->with('success', 'Account updated successfully.');
    }
}
