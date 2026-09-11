@extends('admin.layouts.app')

@section('title', 'Incoming Parcels')
@section('topbar-icon')
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="opacity:.6"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/></svg>
@endsection

@section('topbar-actions')
    <div class="search-input-wrap" style="width:240px;">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <form method="GET" id="search-parcels">
            <input type="search" name="search" value="{{ request('search') }}" placeholder="Search tracking, sender, recipient…" onchange="document.getElementById('search-parcels').submit()">
        </form>
    </div>
@endsection

@section('content')

@php
    $receivedCount   = \App\Models\Parcel::where('status','pickup_approved')->count();
    $loggedCount     = \App\Models\Parcel::where('status','picked_up')->count();
    $forSortingCount = \App\Models\Parcel::where('status','picked_up')->count();
    $sortedCount     = \App\Models\Parcel::where('status','sorted')->count();
    $tab = request('tab','all');
@endphp

{{-- Mini stat strip --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
    @foreach([['Received',$receivedCount],['Logged',$loggedCount],['For Sorting',$forSortingCount],['Sorted',$sortedCount]] as [$lbl,$val])
    <div class="stat" style="padding:12px 16px;">
        <div class="stat__value" style="font-size:22px;">{{ $val }}</div>
        <div class="stat__label">{{ $lbl }}</div>
    </div>
    @endforeach
</div>

{{-- Tabs + table --}}
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
    <div class="tab-bar">
        @foreach(['all'=>'All','pickup_approved'=>'Received','picked_up'=>'Logged','picked_up'=>'For Sorting','sorted'=>'Sorted'] as $val=>$lbl)
            <a href="{{ route('admin.parcels.index',['tab'=>$val]) }}"
               class="tab {{ $tab===$val ? 'active' : '' }}">{{ $lbl }}</a>
        @endforeach
    </div>
</div>

<div class="panel">
    <table>
        <thead>
            <tr>
                <th>Tracking No.</th>
                <th>Sender</th>
                <th>Recipient</th>
                <th>Weight</th>
                <th>Type</th>
                <th>Origin → Destination</th>
                <th>Received</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        @forelse($parcels as $parcel)
            @php
                $badgeClass = match($parcel->status) {
                    'sorted'         => 'badge--active',
                    'picked_up'      => 'badge--transit',
                    'pickup_approved'=> 'badge--pending',
                    'assigned'       => 'badge--assigned',
                    default          => 'badge--neutral',
                };
                $badgeLabel = match($parcel->status) {
                    'sorted'         => 'Sorted',
                    'picked_up'      => 'For Sorting',
                    'pickup_approved'=> 'Received',
                    'assigned'       => 'Assigned',
                    default          => ucwords(str_replace('_',' ',$parcel->status)),
                };
            @endphp
            <tr>
                <td><a href="{{ route('admin.parcels.show',$parcel) }}" class="tracking-no">{{ $parcel->tracking_number }}</a></td>
                <td style="font-weight:500;">{{ $parcel->seller->name ?? '—' }}</td>
                <td>{{ $parcel->receiver_name }}</td>
                <td class="text-secondary">{{ $parcel->weight_kg ? $parcel->weight_kg.' kg' : '—' }}</td>
                <td class="text-secondary">{{ $parcel->size ?? 'Parcel' }}</td>
                <td>
                    <span style="color:var(--amber);">{{ $parcel->area->name ?? 'Unknown' }}</span>
                    <span class="text-muted"> → </span>
                    <span style="color:var(--teal);">{{ $parcel->dropoff_address }}</span>
                </td>
                <td class="text-muted font-mono">{{ $parcel->created_at->format('Y-m-d H:i') }}</td>
                <td><span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span></td>
                <td>
                    @if($parcel->status === 'pickup_approved')
                        <form method="POST" action="{{ route('admin.parcels.mark-picked-up',$parcel) }}">@csrf
                            <button class="btn btn--blue btn--sm">Advance →</button>
                        </form>
                    @elseif($parcel->status === 'picked_up')
                        <a href="{{ route('admin.parcels.sorting') }}" class="btn btn--amber btn--sm">Sort →</a>
                    @elseif($parcel->status === 'sorted')
                        <span class="text-muted" style="font-size:12px;">Done</span>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="9"><div class="empty-state"><div class="empty-state__icon">📦</div>No parcels found.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="pagination">{{ $parcels->links() }}</div>

@endsection
