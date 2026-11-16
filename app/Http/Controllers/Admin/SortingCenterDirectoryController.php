<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SortingCenterApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** People → Sorting centers: every logistics company account, with its town, hubs, barangays and riders. */
class SortingCenterDirectoryController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => 'nullable|in:active,pending,rejected,suspended',
            'search' => 'nullable|string|max:100',
        ]);

        // The newest application of each account (business name and its review status).
        $latestApp = fn (string $column) => SortingCenterApplication::select($column)
            ->whereColumn('sorting_center_applications.user_id', 'users.id')
            ->latest('id')->limit(1);

        $query = $this->centers()
            ->select('users.*')
            ->addSelect([
                'business_name'      => $latestApp('business_name'),
                'application_id'     => $latestApp('id'),
                'application_status' => $latestApp('status'),
                'hubs_count'         => DB::table('logistics_hubs')->selectRaw('COUNT(*)')->whereColumn('sorting_center_id', 'users.id'),
            ])
            ->withCount([
                'deliveryAreas',
                'riders',
                'riders as active_riders_count' => fn ($q) => $q->where('application_status', 'approved')->where('is_active', true),
            ])
            ->latest('users.created_at');

        if ($request->filled('search')) {
            $term = '%' . $request->search . '%';
            $query->where(fn ($q) => $q->where('users.name', 'like', $term)
                ->orWhere('users.email', 'like', $term)
                ->orWhere('users.assigned_municipality', 'like', $term)
                ->orWhereHas('sortingCenterApplications', fn ($a) => $a->where('business_name', 'like', $term)));
        }

        if ($request->filled('status')) {
            $this->whereStatus($query, $request->status);
        }

        $centers = $query->paginate(20)->withQueryString();

        $stats = ['total' => $this->centers()->count()];
        foreach (['active', 'pending', 'suspended'] as $status) {
            $stats[$status] = $this->whereStatus($this->centers(), $status)->count();
        }

        return view('admin.people-sorting-centers.index', compact('centers', 'stats'));
    }

    /** What a sorting center row shows: [label, pl-tag class]. */
    public static function statusOf(User $center): array
    {
        return match (true) {
            in_array($center->role, ['suspended', 'deactivated'], true) => [ucfirst($center->role), 'cancelled'],
            $center->role === 'sorting_center'                         => ['Active', 'delivered'],
            $center->application_status === 'rejected'                 => ['Rejected', 'cancelled'],
            default                                                     => ['Pending', 'pending'],
        };
    }

    /** Sorting center accounts, including ones waiting for review and ones suspended after approval. */
    private function centers(): Builder
    {
        return User::query()->where(fn ($q) => $q->whereIn('users.role', ['sorting_center', 'sorting_center_pending'])
            ->orWhereHas('sortingCenterApplications'));
    }

    private function whereStatus(Builder $query, string $status): Builder
    {
        $app = fn (string $value) => fn ($q) => $q->whereHas('sortingCenterApplications', fn ($a) => $a->where('status', $value))
            ->whereNotIn('users.role', ['sorting_center', 'suspended', 'deactivated']);

        return match ($status) {
            'active'    => $query->where('users.role', 'sorting_center'),
            'suspended' => $query->whereIn('users.role', ['suspended', 'deactivated']),
            'rejected'  => $query->where($app('rejected')),
            'pending'   => $query->where(fn ($q) => $q->where('users.role', 'sorting_center_pending')
                ->whereDoesntHave('sortingCenterApplications', fn ($a) => $a->where('status', 'rejected'))),
        };
    }
}
