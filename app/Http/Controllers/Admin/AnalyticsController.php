<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    /** Roles treated as disabled (suspended/deactivated lose their original role). */
    private const DISABLED_ROLES = ['suspended', 'deactivated'];

    public function index(Request $request)
    {
        // ── Account composition ───────────────────────────────
        $accountStats = [
            'total'     => User::count(),
            'buyers'    => User::where('role', 'buyer')->count(),
            'sellers'   => User::where('role', 'seller')->count(),
            'couriers'  => User::whereIn('role', ['courier', 'courier_pending'])->count(),
            'admins'    => User::where('role', 'admin')->count(),
            'disabled'  => User::whereIn('role', self::DISABLED_ROLES)->count(),
        ];

        // ── Registration totals ───────────────────────────────
        $registrationTrends = [
            'today'     => User::whereDate('created_at', Carbon::today())->count(),
            'week'      => User::where('created_at', '>=', Carbon::now()->startOfWeek())->count(),
            'month'     => User::where('created_at', '>=', Carbon::now()->startOfMonth())->count(),
            'year'      => User::where('created_at', '>=', Carbon::now()->startOfYear())->count(),
        ];

        // ── Daily growth (last 14 days) ───────────────────────
        $daily = collect(range(13, 0))->map(function ($daysAgo) {
            $date = Carbon::today()->subDays($daysAgo);
            return [
                'label' => $date->format('M j'),
                'count' => User::whereDate('created_at', $date)->count(),
            ];
        });

        // ── Weekly growth (last 8 weeks) ──────────────────────
        $weekly = collect(range(7, 0))->map(function ($weeksAgo) {
            $start = Carbon::now()->startOfWeek()->subWeeks($weeksAgo);
            $end   = (clone $start)->endOfWeek();
            return [
                'label' => $start->format('M j'),
                'count' => User::whereBetween('created_at', [$start, $end])->count(),
            ];
        });

        // ── Monthly growth (last 6 months) ────────────────────
        $monthly = collect(range(5, 0))->map(function ($monthsAgo) {
            $month = Carbon::now()->subMonths($monthsAgo);
            return [
                'label' => $month->format('M Y'),
                'count' => User::whereYear('created_at', $month->year)
                               ->whereMonth('created_at', $month->month)
                               ->count(),
            ];
        });

        return view('admin.analytics.index', compact(
            'accountStats', 'registrationTrends', 'daily', 'weekly', 'monthly'
        ));
    }
}
