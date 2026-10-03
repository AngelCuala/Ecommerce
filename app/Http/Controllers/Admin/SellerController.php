<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

class SellerController extends Controller
{
    /** Roles considered "seller" accounts (active + suspended sellers). */
    private const SELLER_ROLES = ['seller'];

    /**
     * List all seller accounts with their shop / business information.
     * A "seller" is a user whose role is seller, OR a suspended/deactivated
     * account that still has an approved seller application.
     */
    public function index(Request $request)
    {
        $query = User::query()
            ->withCount('books')
            ->with('sellerApplication')
            ->where(function ($q) {
                $q->whereIn('role', self::SELLER_ROLES)
                  ->orWhereHas('sellerApplication', fn($sa) => $sa->where('status', 'approved'));
            })
            ->latest();

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('username', 'like', "%{$term}%")
                  ->orWhereHas('sellerApplication', function ($sa) use ($term) {
                      $sa->where('shop_name', 'like', "%{$term}%")
                         ->orWhere('business_name', 'like', "%{$term}%");
                  });
            });
        }

        // Status filter: active | suspended
        if ($request->filled('status')) {
            if ($request->status === 'suspended') {
                $query->whereIn('role', ['suspended', 'deactivated']);
            } elseif ($request->status === 'active') {
                $query->where('role', 'seller');
            }
        }

        $sellers = $query->get();

        $stats = [
            'total'     => $sellers->count(),
            'active'    => $sellers->where('role', 'seller')->count(),
            'suspended' => $sellers->whereIn('role', ['suspended', 'deactivated'])->count(),
        ];

        return view('admin.sellers.index', compact('sellers', 'stats'));
    }

    /**
     * Show a single seller: shop/business details, verification, product count.
     * Does NOT list individual seller orders (out of admin scope).
     */
    public function show(int $id)
    {
        $seller = User::withCount('books')
            ->with('sellerApplication')
            ->findOrFail($id);

        return view('admin.sellers.show', compact('seller'));
    }

    /** Suspend a seller account (reversible). */
    public function suspend(int $id)
    {
        $seller = User::findOrFail($id);
        if ($seller->isAdmin()) {
            return back()->with('error', 'Cannot suspend an admin account.');
        }

        $seller->update(['role' => 'suspended']);

        ActivityLog::record(
            'seller_suspend',
            'Seller Suspended',
            "Suspended seller account: {$seller->name} (ID {$seller->id}).",
            $seller
        );

        return back()->with('success', $seller->name . ' has been suspended.');
    }

    /** Re-activate a suspended seller (restore to seller role). */
    public function activate(int $id)
    {
        $seller = User::findOrFail($id);
        if ($seller->isAdmin()) {
            return back()->with('error', 'Cannot modify an admin account.');
        }

        $seller->update(['role' => 'seller']);

        ActivityLog::record(
            'seller_activate',
            'Seller Reactivated',
            "Reactivated seller account: {$seller->name} (ID {$seller->id}).",
            $seller
        );

        return back()->with('success', $seller->name . ' has been reactivated as a seller.');
    }
}
