<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::withCount(['orders', 'books'])->latest();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                  ->orWhere('email', 'like', '%'.$request->search.'%')
                  ->orWhere('username', 'like', '%'.$request->search.'%');
            });
        }

        $users = $query->get();
        return view('admin.users.index', compact('users'));
    }

    public function show(int $id)
    {
        $user = User::withCount(['orders','books'])
            ->with(['orders' => fn($q) => $q->latest()->take(5),
                    'sellerApplication'])
            ->findOrFail($id);

        return view('admin.users.show', compact('user'));
    }

    /** Activate — set role back to buyer (from deactivated/pending) */
    public function activate(int $id)
    {
        $user = User::findOrFail($id);
        if ($user->isAdmin()) return back()->with('error', 'Cannot modify an admin account.');

        $user->update(['role' => 'buyer']);
        \App\Models\ActivityLog::record('user_activate', 'User Activated', 'Activated account: ' . $user->name . ' (ID ' . $user->id . ').', $user);
        return back()->with('success', $user->name . ' has been activated.');
    }

    /** Suspend — temporarily disable, can be restored */
    public function suspend(int $id)
    {
        $user = User::findOrFail($id);
        if ($user->isAdmin()) return back()->with('error', 'Cannot suspend an admin account.');

        $user->update(['role' => 'suspended']);
        \App\Models\ActivityLog::record('user_suspend', 'User Suspended', 'Suspended account: ' . $user->name . ' (ID ' . $user->id . ').', $user);
        return back()->with('success', $user->name . ' has been suspended.');
    }

    /** Restore — undo suspend, back to buyer */
    public function restore(int $id)
    {
        $user = User::findOrFail($id);
        $user->update(['role' => 'buyer']);
        \App\Models\ActivityLog::record('user_restore', 'User Restored', 'Restored account to buyer: ' . $user->name . ' (ID ' . $user->id . ').', $user);
        return back()->with('success', $user->name . ' has been restored as a buyer.');
    }

    /** Deactivate — permanently disable the account */
    public function deactivate(int $id)
    {
        $user = User::findOrFail($id);
        if ($user->isAdmin()) return back()->with('error', 'Cannot deactivate an admin account.');

        $user->update(['role' => 'deactivated']);
        \App\Models\ActivityLog::record('user_deactivate', 'User Deactivated', 'Deactivated account: ' . $user->name . ' (ID ' . $user->id . ').', $user);
        return back()->with('success', $user->name . '\'s account has been deactivated.');
    }

    /** Assign a sorting-center account to a single municipality. */
    public function assignMunicipality(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        if (! $user->isSortingCenter()) {
            return back()->with('error', 'Only sorting-center accounts can be assigned a municipality.');
        }

        $request->validate([
            'province'          => 'nullable|string|max:120',
            'municipality'      => 'required|string|max:120',
            'municipality_code' => 'required',
        ] + \App\Services\PsgcDirectory::codeRules());

        // Must be a real PSA city/municipality under the selected province, with a 9-digit
        // Correspondence Code (that code is what delivery routing compares).
        $addr = app(\App\Services\PsgcDirectory::class)->resolve($request->all(), false);
        if (! $addr['municipality_code']) {
            return back()->with('error', "{$addr['municipality']} has no PSA correspondence code yet, so it cannot be assigned.");
        }
        $data = [
            'province'          => $addr['province_display'],
            'province_code'     => $addr['province_code'],
            'municipality'      => $addr['municipality'],
            'municipality_code' => $addr['municipality_code'],
        ];

        // If the municipality changed, detach delivery areas/riders tied to the old one
        $changed = $user->assigned_municipality_code !== $data['municipality_code'];

        $user->update([
            'assigned_province'          => $data['province'],
            'assigned_province_code'     => $data['province_code'] ?? null,
            'assigned_municipality'      => $data['municipality'],
            'assigned_municipality_code' => $data['municipality_code'],
        ]);

        \App\Models\ActivityLog::record(
            'sc_municipality_assigned',
            'Sorting Center Assigned',
            'Assigned ' . $user->name . ' to ' . $data['municipality'] . ', ' . $data['province'] . '.',
            $user
        );

        $msg = $user->name . ' is now assigned to ' . $data['municipality'] . '.';
        if ($changed) {
            $msg .= ' Existing delivery areas remain but should be reviewed for the new municipality.';
        }

        return back()->with('success', $msg);
    }
}
