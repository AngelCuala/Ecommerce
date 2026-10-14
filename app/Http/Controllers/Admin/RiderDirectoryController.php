<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Http\Request;

/** People → Riders: every rider account, with its sorting center, barangay and deliveries. */
class RiderDirectoryController extends Controller
{
    public function index(Request $request)
    {
        $query = Rider::with(['user:id,name,email,role', 'sortingCenter:id,name', 'area:id,name'])
            ->withCount([
                'parcelDeliveries as deliveries_count',
                'parcelDeliveries as delivered_count' => fn ($q) => $q->where('status', 'delivered'),
                'parcelDeliveries as open_count' => fn ($q) => $q->whereIn('status', ['assigned', 'out_for_delivery']),
            ])
            ->latest();

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(fn ($q) => $q->where('full_name', 'like', $term)
                ->orWhere('phone', 'like', $term)
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', $term)));
        }

        match ($request->status) {
            'pending'  => $query->where('application_status', 'pending'),
            'rejected' => $query->where('application_status', 'rejected'),
            'active'   => $query->where('application_status', 'approved')->where('is_active', true),
            'inactive' => $query->where('application_status', 'approved')->where('is_active', false),
            default    => null,
        };

        if ($request->filled('center')) {
            $query->where('sorting_center_id', (int) $request->center);
        }

        $riders = $query->paginate(20)->withQueryString();

        $stats = [
            'total'    => Rider::count(),
            'active'   => Rider::where('application_status', 'approved')->where('is_active', true)->count(),
            'pending'  => Rider::where('application_status', 'pending')->count(),
            'inactive' => Rider::where('application_status', 'approved')->where('is_active', false)->count(),
        ];

        $centers = User::where('role', 'sorting_center')->orderBy('name')->get(['id', 'name']);

        return view('admin.people-riders.index', compact('riders', 'stats', 'centers'));
    }
}
