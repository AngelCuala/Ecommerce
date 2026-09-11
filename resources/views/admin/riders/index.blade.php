@extends('admin.layouts.app')

@section('title', 'Rider Management')
@section('topbar-icon')
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="opacity:.6"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
@endsection

@section('topbar-actions')
    <div class="search-input-wrap" style="width:220px;">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <form method="GET" id="search-form">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search name or ID…" onchange="document.getElementById('search-form').submit()">
        </form>
    </div>
@endsection

@section('content')

{{-- Tab bar --}}
@php
    $allCount      = \App\Models\Rider::count();
    $pendingCount  = \App\Models\Rider::where('application_status','pending')->count();
    $activeCount   = \App\Models\Rider::where('application_status','approved')->where('is_active',true)->count();
    $inactiveCount = \App\Models\Rider::where('application_status','approved')->where('is_active',false)->count();
    $rejectedCount = \App\Models\Rider::where('application_status','rejected')->count();
    $currentStatus = request('status','');
@endphp

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px;">
    <div class="tab-bar">
        <a href="{{ route('admin.riders.index') }}"
           class="tab {{ $currentStatus==='' ? 'active' : '' }}">All <span class="tab-count">{{ $allCount }}</span></a>
        <a href="{{ route('admin.riders.index', ['status'=>'pending']) }}"
           class="tab {{ $currentStatus==='pending' ? 'active' : '' }}">Pending <span class="tab-count">{{ $pendingCount }}</span></a>
        <a href="{{ route('admin.riders.index', ['status'=>'active']) }}"
           class="tab {{ $currentStatus==='active' ? 'active' : '' }}">Active <span class="tab-count">{{ $activeCount }}</span></a>
        <a href="{{ route('admin.riders.index', ['status'=>'inactive']) }}"
           class="tab {{ $currentStatus==='inactive' ? 'active' : '' }}">Inactive <span class="tab-count">{{ $inactiveCount }}</span></a>
        <a href="{{ route('admin.riders.index', ['status'=>'rejected']) }}"
           class="tab {{ $currentStatus==='rejected' ? 'active' : '' }}">Rejected <span class="tab-count">{{ $rejectedCount }}</span></a>
    </div>
</div>

<div class="panel">
    <table>
        <thead>
            <tr>
                <th>Rider ID</th>
                <th>Name</th>
                <th>Contact</th>
                <th>Area</th>
                <th>Applied</th>
                <th>Deliveries</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse($riders as $rider)
            @php
                $isActive  = $rider->application_status === 'approved' && $rider->is_active;
                $isPending = $rider->application_status === 'pending';
                $isReject  = $rider->application_status === 'rejected';
                $isInactive= $rider->application_status === 'approved' && !$rider->is_active;

                $statusClass = $isActive  ? 'badge--active'  :
                               ($isPending ? 'badge--pending' :
                               ($isReject  ? 'badge--failed'  : 'badge--inactive'));
                $statusLabel = $isActive   ? 'Active'   :
                               ($isPending ? 'Pending'  :
                               ($isReject  ? 'Rejected' : 'Inactive'));
            @endphp
            <tr>
                <td><span class="tracking-no">RDR-{{ str_pad($rider->id,3,'0',STR_PAD_LEFT) }}</span></td>
                <td>
                    <a href="{{ route('admin.riders.show', $rider) }}" style="font-weight:600;color:var(--text-primary);">{{ $rider->full_name }}</a>
                    <div class="td-sub">{{ $rider->user->email ?? '' }}</div>
                </td>
                <td class="text-secondary">{{ $rider->phone ?? '—' }}</td>
                <td><span style="color:var(--amber);font-weight:500;">{{ $rider->area->name ?? '—' }}</span></td>
                <td class="text-muted">{{ $rider->created_at->format('Y-m-d') }}</td>
                <td class="font-bold text-secondary">{{ $rider->parcelDeliveries()->where('status','delivered')->count() }}</td>
                <td><span class="badge {{ $statusClass }}"><span class="badge__dot"></span>{{ $statusLabel }}</span></td>
                <td>
                    <div style="display:flex;gap:6px;flex-wrap:wrap;">
                        @if($isPending)
                            <form method="POST" action="{{ route('admin.riders.approve',$rider) }}">@csrf
                                <button class="btn btn--primary btn--sm">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.riders.disapprove',$rider) }}">@csrf
                                <button class="btn btn--danger btn--sm">Reject</button>
                            </form>
                        @elseif($isActive)
                            <form method="POST" action="{{ route('admin.riders.toggle-active',$rider) }}">@csrf
                                <button class="btn btn--danger btn--sm">Deactivate</button>
                            </form>
                        @elseif($isInactive)
                            <form method="POST" action="{{ route('admin.riders.toggle-active',$rider) }}">@csrf
                                <button class="btn btn--primary btn--sm">Activate</button>
                            </form>
                        @elseif($isReject)
                            <form method="POST" action="{{ route('admin.riders.approve',$rider) }}">@csrf
                                <button class="btn btn--amber btn--sm">Re-review</button>
                            </form>
                        @endif
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty-state"><div class="empty-state__icon">🛵</div>No riders found.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="pagination">{{ $riders->links() }}</div>

@endsection
