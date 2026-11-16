@extends('admin.layouts.app')

@section('title', 'Delivery Assignment')
@section('topbar-icon')
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="opacity:.6"><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4"/></svg>
@endsection

@section('topbar-actions')
    <form method="POST" action="#" id="auto-assign-form">
        @csrf
        <button class="btn btn--amber">Auto-Assign by Area</button>
    </form>
@endsection

@section('content')

{{-- Rider availability cards --}}
<div style="margin-bottom:8px;font-size:11.5px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;">Rider Availability</div>
<div class="rider-cards" style="margin-bottom:20px;">
    @forelse($riders as $rider)
        @php
            $activeDelivery = $rider->parcelDeliveries()->whereIn('status',['assigned','out_for_delivery'])->exists();
        @endphp
        <div class="rider-card">
            <div class="rider-card__name">{{ $rider->full_name }}</div>
            <div class="rider-card__area text-muted">{{ $rider->area->name ?? '—' }}</div>
            <div class="rider-card__status">
                @if($activeDelivery)
                    <span class="badge badge--out-delivery" style="font-size:11px;"><span class="badge__dot"></span>On Delivery</span>
                @else
                    <span class="badge badge--active" style="font-size:11px;"><span class="badge__dot"></span>Available</span>
                @endif
            </div>
        </div>
    @empty
        <p class="text-muted" style="font-size:12px;">No active riders available.</p>
    @endforelse
</div>

{{-- Area filter tabs --}}
<div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
    <div class="tab-bar">
        <a href="{{ route('admin.logistics.deliveries.assign-index') }}" class="tab {{ !request('area_id') ? 'active' : '' }}">All</a>
        @foreach($areas as $area)
            <a href="{{ route('admin.logistics.deliveries.assign-index',['area_id'=>$area->id]) }}"
               class="tab {{ request('area_id')==$area->id ? 'active' : '' }}">{{ $area->name }}</a>
        @endforeach
    </div>
</div>

<div class="panel">
    <table>
        <thead>
            <tr>
                <th>Tracking</th>
                <th>Recipient</th>
                <th>Area</th>
                <th>Address</th>
                <th>Weight</th>
                <th>Assign Rider</th>
                <th>Action</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
        @forelse($parcels as $parcel)
            <tr>
                <td><span class="tracking-no">{{ $parcel->tracking_number }}</span></td>
                <td style="font-weight:500;">{{ $parcel->receiver_name }}</td>
                <td><span style="color:var(--amber);font-weight:500;">{{ $parcel->area->name ?? '—' }}</span></td>
                <td class="text-secondary" style="max-width:160px;">{{ $parcel->dropoff_address }}</td>
                <td class="text-secondary">{{ $parcel->weight_kg ? $parcel->weight_kg.' kg' : '—' }}</td>
                <td>
                    <span class="text-secondary">—</span>
                </td>
                <td>
                    <span class="text-xs" style="color:#6b90aa;" data-status-locked>Follows the seller → logistics → rider process</span>
                </td>
                <td><span class="badge badge--pending">Unassigned</span></td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty-state"><div class="empty-state__icon">🗺</div>No parcels awaiting assignment.</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="pagination">{{ $parcels->links() }}</div>

@endsection
