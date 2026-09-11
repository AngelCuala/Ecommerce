@extends('admin.layouts.app')

@section('title', 'Pickup Requests')
@section('topbar-icon')
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="opacity:.6"><path d="M20 7H4a2 2 0 00-2 2v10a2 2 0 002 2h16a2 2 0 002-2V9a2 2 0 00-2-2z"/><path d="M16 3l-4 4-4-4"/></svg>
@endsection

@section('content')

@php
    $allCount       = \App\Models\Parcel::count();
    $pendingCount   = \App\Models\Parcel::where('status','pending_pickup')->count();
    $confirmedCount = \App\Models\Parcel::where('status','pickup_approved')->count();
    $approvedCount  = \App\Models\Parcel::whereIn('status',['picked_up','sorted','assigned','in_transit','delivered'])->count();
    $rejectedCount  = \App\Models\Parcel::where('status','pickup_rejected')->count();
    $tab = request('tab','all');
@endphp

<div class="tab-bar" style="margin-bottom:18px;">
    <a href="{{ route('admin.pickup-requests.index') }}"
       class="tab {{ $tab==='all' ? 'active' : '' }}" style="min-width:100px;text-align:center;">All</a>
    <a href="{{ route('admin.pickup-requests.index',['tab'=>'pending']) }}"
       class="tab {{ $tab==='pending' ? 'active' : '' }}" style="min-width:100px;text-align:center;">Pending</a>
    <a href="{{ route('admin.pickup-requests.index',['tab'=>'confirmed']) }}"
       class="tab {{ $tab==='confirmed' ? 'active' : '' }}" style="min-width:100px;text-align:center;">Confirmed</a>
    <a href="{{ route('admin.pickup-requests.index',['tab'=>'approved']) }}"
       class="tab {{ $tab==='approved' ? 'active' : '' }}" style="min-width:100px;text-align:center;">Approved</a>
    <a href="{{ route('admin.pickup-requests.index',['tab'=>'rejected']) }}"
       class="tab {{ $tab==='rejected' ? 'active' : '' }}" style="min-width:100px;text-align:center;">Rejected</a>
</div>

<div class="panel">
    <table>
        <thead>
            <tr>
                <th>Request ID</th>
                <th>Seller / Business</th>
                <th>Address</th>
                <th>Parcels</th>
                <th>Submitted</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @forelse($requests as $parcel)
            @php
                $badgeClass = match($parcel->status) {
                    'pending_pickup'  => 'badge--pending',
                    'pickup_approved' => 'badge--confirmed',
                    'pickup_rejected' => 'badge--failed',
                    default           => 'badge--active',
                };
                $badgeLabel = match($parcel->status) {
                    'pending_pickup'  => 'Pending',
                    'pickup_approved' => 'Confirmed',
                    'pickup_rejected' => 'Rejected',
                    default           => 'Approved',
                };
            @endphp
            <tr>
                <td><span class="tracking-no">PKP-{{ str_pad($parcel->id,3,'0',STR_PAD_LEFT) }}-{{ now()->format('m') }}{{ str_pad($parcel->id,3,'0',STR_PAD_LEFT) }}</span></td>
                <td>
                    <div style="font-weight:600;color:var(--text-primary);">{{ $parcel->seller->name ?? '—' }}</div>
                    <div class="td-sub">{{ $parcel->seller->email ?? '' }}</div>
                </td>
                <td class="text-secondary" style="max-width:180px;">{{ $parcel->pickup_address }}</td>
                <td class="font-bold text-secondary">1</td>
                <td class="text-muted">{{ $parcel->created_at->format('Y-m-d H:i') }}</td>
                <td><span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span></td>
                <td>
                    <div style="display:flex;gap:6px;align-items:center;">
                        @if($parcel->status === 'pending_pickup')
                            <form method="POST" action="{{ route('admin.pickup-requests.approve',$parcel) }}">@csrf
                                <button class="btn btn--primary btn--sm">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.pickup-requests.reject',$parcel) }}">@csrf
                                <input type="hidden" name="reason" value="Rejected by admin">
                                <button class="btn btn--danger btn--sm" onclick="return confirm('Reject?')">Reject</button>
                            </form>
                        @endif
                        <a href="{{ route('admin.parcels.show',$parcel) }}" class="btn btn--ghost btn--sm">View</a>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty-state"><div class="empty-state__icon">📥</div>No pickup requests found.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="pagination">{{ $requests->links() }}</div>

@endsection
