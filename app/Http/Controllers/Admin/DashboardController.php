<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\SellerApplication;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // ── Account buckets ──────────────────────────────────────
        // "Suspended" users lose their original role (role becomes 'suspended'
        // or 'deactivated'), so we count the disabled bucket separately.
        $suspendedRoles = ['suspended', 'deactivated'];

        $totalUsers      = User::whereNotIn('role', ['admin'])->count();
        $suspendedUsers  = User::whereIn('role', $suspendedRoles)->count();
        $activeUsers     = $totalUsers - $suspendedUsers;

        $totalBuyers   = User::where('role', 'buyer')->count();
        $activeBuyers  = User::where('role', 'buyer')->where('approval_status', '!=', 'rejected')->count();

        $totalSellers  = User::where('role', 'seller')->count();
        $activeSellers = User::where('role', 'seller')->count(); // sellers are active once approved; suspended ones move to 'suspended' role

        // ── New registrations ────────────────────────────────────
        $newToday = User::whereDate('created_at', today())->count();
        $newWeek  = User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();
        $newMonth = User::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count();

        // ── Pending platform requests (NO courier applications) ───
        $pendingSellerApps = SellerApplication::where('status', 'pending')->count();
        $pendingBuyerVerif = User::where('role', 'buyer')->where('approval_status', 'pending')->count();
        $pendingRequests   = $pendingSellerApps + $pendingBuyerVerif;

        // A short list of items needing attention for the "Pending Requests" card.
        $pendingList = SellerApplication::where('status', 'pending')
            ->with('user')->latest()->take(5)->get();

        // ── User growth chart (last 6 months) ─────────────────────
        $growth = collect(range(5, 0))->map(function ($back) {
            $month = now()->subMonths($back);
            return (object) [
                'label' => $month->format('M'),
                'count' => User::whereMonth('created_at', $month->month)
                    ->whereYear('created_at', $month->year)->count(),
            ];
        });

        // ── Recent admin activity ─────────────────────────────────
        $recentActivity = ActivityLog::with('admin')->latest()->take(6)->get();

        // ── Admin notifications (attention items, NOT courier events) ─
        $notifications = [
            $pendingSellerApps > 0
                ? ['title' => "{$pendingSellerApps} new seller application" . ($pendingSellerApps === 1 ? '' : 's'), 'type' => 'info',    'link' => route('admin.seller-applications.index')]
                : null,
            $pendingBuyerVerif > 0
                ? ['title' => "{$pendingBuyerVerif} buyer verification" . ($pendingBuyerVerif === 1 ? '' : 's') . ' pending', 'type' => 'info', 'link' => route('admin.customers.index')]
                : null,
            $suspendedUsers > 0
                ? ['title' => "{$suspendedUsers} account" . ($suspendedUsers === 1 ? '' : 's') . ' currently disabled', 'type' => 'warning', 'link' => route('admin.users.index')]
                : null,
        ];
        $notifications = array_values(array_filter($notifications));

        // ── System status (configured, not live-monitored) ───────
        $systemStatus = [
            'Authentication' => true,
            'Database'       => true,   // if this page rendered, the DB query above succeeded
            'Email Service'  => config('mail.default') !== null,
            'Notifications'  => true,
        ];

        return view('admin.dashboard', compact(
            'totalUsers', 'activeUsers', 'suspendedUsers',
            'totalBuyers', 'activeBuyers',
            'totalSellers', 'activeSellers',
            'newToday', 'newWeek', 'newMonth',
            'pendingRequests', 'pendingSellerApps', 'pendingBuyerVerif', 'pendingList',
            'growth', 'recentActivity', 'notifications', 'systemStatus'
        ));
    }
}
