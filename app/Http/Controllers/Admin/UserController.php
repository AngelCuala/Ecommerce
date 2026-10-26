<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::withCount(['orders', 'products as books_count'])->latest();

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->search.'%')
                  ->orWhere('email', 'like', '%'.$request->search.'%')
                  ->orWhere('username', 'like', '%'.$request->search.'%');
            });
        }

        $users = $query->get();

        // Rider profiles (sorting center, barangay, deliveries) for courier accounts
        $riders = \App\Models\Rider::whereIn('user_id', $users->pluck('id'))
            ->with(['sortingCenter:id,name', 'area:id,name'])
            ->withCount([
                'parcelDeliveries as deliveries_count',
                'parcelDeliveries as delivered_count' => fn ($q) => $q->where('status', 'delivered'),
            ])
            ->get()
            ->keyBy('user_id');

        return view('admin.users.index', compact('users', 'riders'));
    }

    public function show(int $id)
    {
        $user = User::withCount(['orders', 'products as books_count'])
            ->with(['orders' => fn($q) => $q->latest()->take(5),
                    'sellerApplication'])
            ->findOrFail($id);

        return view('admin.users.show', compact('user'));
    }

    /** Activate — undo a suspension or deactivation */
    public function activate(int $id)
    {
        $user = User::findOrFail($id);
        if ($user->isAdmin()) return back()->with('error', 'Cannot modify an admin account.');
        if ($user->isActive()) return back()->with('error', $user->name . ' is already active.');

        $user->update(['role' => $this->previousRole($user)]);
        \App\Models\ActivityLog::record('user_activate', 'User Activated', 'Activated account: ' . $user->name . ' (ID ' . $user->id . ').', $user);
        return back()->with('success', $user->name . ' has been activated.');
    }

    /** Suspend — temporarily disable, can be restored */
    public function suspend(int $id)
    {
        $user = User::findOrFail($id);
        if ($user->isAdmin()) return back()->with('error', 'Cannot suspend an admin account.');
        if ($user->isSuspended()) return back()->with('error', $user->name . ' is already suspended.');

        $this->disable($user, 'suspended', 'user_suspend', 'User Suspended', 'Suspended account: ' . $user->name . ' (ID ' . $user->id . ').');
        return back()->with('success', $user->name . ' has been suspended.');
    }

    /** Restore — undo suspend */
    public function restore(int $id)
    {
        $user = User::findOrFail($id);
        if ($user->isAdmin()) return back()->with('error', 'Cannot modify an admin account.');
        if ($user->isActive()) return back()->with('error', $user->name . ' is already active.');

        $role = $this->previousRole($user);
        $user->update(['role' => $role]);
        \App\Models\ActivityLog::record('user_restore', 'User Restored', 'Restored account to ' . $role . ': ' . $user->name . ' (ID ' . $user->id . ').', $user);
        return back()->with('success', $user->name . ' has been restored.');
    }

    /** Deactivate — permanently disable the account */
    public function deactivate(int $id)
    {
        $user = User::findOrFail($id);
        if ($user->isAdmin()) return back()->with('error', 'Cannot deactivate an admin account.');
        if ($user->isDeactivated()) return back()->with('error', $user->name . '\'s account is already deactivated.');

        $this->disable($user, 'deactivated', 'user_deactivate', 'User Deactivated', 'Deactivated account: ' . $user->name . ' (ID ' . $user->id . ').');
        return back()->with('success', $user->name . '\'s account has been deactivated.');
    }

    /**
     * Suspend or deactivate: the user is signed out everywhere ("remember me" included), and the role they had
     * is kept in the activity log (old_value) so Activate can give it back.
     */
    private function disable(User $user, string $state, string $action, string $label, string $description): void
    {
        $was = $user->role;
        $user->forceFill(['role' => $state, 'remember_token' => \Illuminate\Support\Str::random(60)])->save();
        if (config('session.driver') === 'database') {
            \Illuminate\Support\Facades\DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
        \App\Models\ActivityLog::record($action, $label, $description, $user);
        \App\Models\ActivityLog::where('action', $action)->where('subject_type', 'User')->where('subject_id', $user->id)
            ->latest('id')->first()?->update(['old_value' => $was, 'new_value' => $state]);
    }

    /** The role the account had before it was suspended/deactivated (seller, rider, sorting center…); buyer if never recorded. */
    private function previousRole(User $user): string
    {
        return \App\Models\ActivityLog::whereIn('action', ['user_deactivate', 'user_suspend'])->where('subject_type', 'User')->where('subject_id', $user->id)
            ->whereNotNull('old_value')->whereNotIn('old_value', ['suspended', 'deactivated', 'admin'])
            ->latest('id')->value('old_value') ?? 'buyer';
    }

    /** Assign a sorting-center account to a single municipality. */
    public function assignMunicipality(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        if (! $user->isSortingCenter()) {
            return back()->with('error', 'Only sorting-center accounts can be assigned a municipality.');
        }

        $data = $request->validate([
            'province'          => 'required|string|max:120',
            'province_code'     => 'nullable|string|max:40',
            'municipality'      => 'required|string|max:120',
            'municipality_code' => 'required|string|max:40',
        ]);
        // Sorting centers only operate inside the service area (Laguna).
        \App\Support\ServiceArea::assert($data['municipality_code'], null, null, 'municipality', 'municipality');

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
