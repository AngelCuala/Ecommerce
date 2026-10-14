<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

/** Everything important anyone did on ALVY (buyers, sellers, couriers, sorting centers, admins), with IP. */
class ActivityLogController extends Controller
{
    public const ROLES = [
        'buyer' => 'Buyers', 'seller' => 'Sellers', 'courier' => 'Couriers', 'courier_pending' => 'Courier applicants',
        'sorting_center' => 'Sorting centers', 'admin' => 'Admins', 'guest' => 'Not signed in',
    ];

    public function index(Request $request)
    {
        $query = ActivityLog::latest();

        if ($request->filled('role')) {
            $query->where('user_role', $request->role);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from')->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to')->endOfDay());
        }
        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(fn ($q) => $q->where('admin_name', 'like', $term)
                ->orWhere('ip_address', 'like', $term)
                ->orWhere('description', 'like', $term)
                ->orWhere('action_label', 'like', $term));
        }
        if ($request->filled('user')) {       // "this user's activity" links
            $query->where('admin_id', $request->integer('user'));
        }

        $logs = $query->paginate(30)->withQueryString();

        $actionOptions = ActivityLog::actionOptions();
        $roles = self::ROLES;
        $today = ActivityLog::where('created_at', '>=', now()->startOfDay())->count();
        $failedToday = ActivityLog::where('created_at', '>=', now()->startOfDay())->where('status', 'failed')->count();

        return view('admin.activity.index', compact('logs', 'actionOptions', 'roles', 'today', 'failedToday'));
    }
}
