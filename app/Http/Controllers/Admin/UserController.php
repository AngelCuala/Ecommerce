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
        return back()->with('success', $user->name . ' has been activated.');
    }

    /** Suspend — temporarily disable, can be restored */
    public function suspend(int $id)
    {
        $user = User::findOrFail($id);
        if ($user->isAdmin()) return back()->with('error', 'Cannot suspend an admin account.');

        $user->update(['role' => 'suspended']);
        return back()->with('success', $user->name . ' has been suspended.');
    }

    /** Restore — undo suspend, back to buyer */
    public function restore(int $id)
    {
        $user = User::findOrFail($id);
        $user->update(['role' => 'buyer']);
        return back()->with('success', $user->name . ' has been restored as a buyer.');
    }

    /** Deactivate — permanently disable the account */
    public function deactivate(int $id)
    {
        $user = User::findOrFail($id);
        if ($user->isAdmin()) return back()->with('error', 'Cannot deactivate an admin account.');

        $user->update(['role' => 'deactivated']);
        return back()->with('success', $user->name . '\'s account has been deactivated.');
    }
}
