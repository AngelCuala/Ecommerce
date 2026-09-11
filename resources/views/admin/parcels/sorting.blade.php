@extends('admin.layouts.app')

@section('title', 'Parcel Sorting')
@section('topbar-icon')
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="opacity:.6"><path d="M3 6h18M7 12h10M11 18h2"/></svg>
@endsection

@section('topbar-actions')
    <span style="font-size:12px;color:var(--teal);font-weight:600;">{{ $sortedToday }} sorted today</span>
@endsection

@section('content')

{{-- Area bins --}}
<div style="margin-bottom:8px;font-size:11.5px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.4px;">
    Area Bins — Sorted Count
</div>
<div class="area-bins">
    @forelse($areas as $area)
        <div class="area-bin">
            <span class="area-bin__name">{{ $area->name }}</span>
            <span class="area-bin__count">{{ $area->parcels_count }}</span>
        </div>
    @empty
        <div class="area-bin"><span class="area-bin__name text-muted">No areas configured</span><span class="area-bin__count">0</span></div>
    @endforelse
</div>

{{-- Sorting queue --}}
<div class="panel">
    <div class="panel__header">
        <h2>Sorting Queue — {{ $pending->count() }} pending</h2>
        <span class="panel__meta text-teal">{{ $sortedToday }} sorted today</span>
    </div>
    <table>
        <thead>
            <tr>
                <th>Tracking</th>
                <th>Recipient</th>
                <th>Destination</th>
                <th>Type</th>
                <th>Weight</th>
                <th>Assign to Area</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        @forelse($pending as $parcel)
            <tr>
                <td><span class="tracking-no">{{ $parcel->tracking_number }}</span></td>
                <td style="font-weight:500;">{{ $parcel->receiver_name }}</td>
                <td><span style="color:var(--amber);">{{ $parcel->dropoff_address }}</span></td>
                <td class="text-secondary">{{ $parcel->size ?? 'Parcel' }}</td>
                <td class="text-secondary">{{ $parcel->weight_kg ? $parcel->weight_kg.' kg' : '—' }}</td>
                <td>
                    <form method="POST" action="{{ route('admin.parcels.sort',$parcel) }}" id="sort-form-{{ $parcel->id }}" style="display:inline;">
                        @csrf
                        <select name="area_id" style="width:140px;background:var(--bg-input);" onchange="">
                            <option value="">Select area…</option>
                            @foreach($areas as $area)
                                <option value="{{ $area->id }}" @selected($parcel->area_id == $area->id)>{{ $area->name }}</option>
                            @endforeach
                        </select>
                    </form>
                </td>
                <td>
                    <button onclick="document.getElementById('sort-form-{{ $parcel->id }}').submit()"
                            class="btn btn--primary btn--sm">Confirm Sort</button>
                </td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty-state"><div class="empty-state__icon">🎉</div>All parcels sorted!</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>

@endsection
