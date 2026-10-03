@extends('sc.layout')
@section('title', 'Parcel Sorting')
@section('icon', 'sort')
@section('topbar-right')
    <span style="font-size:12px;font-weight:600;color:var(--accent-green);">{{ $sortedToday }} sorted today</span>
@endsection

@section('content')
<div class="page-header"><h1>Parcel Sorting</h1></div>
<div class="page-body">

    @if($areas->isEmpty())
        <div class="card" style="padding:14px 18px;margin-bottom:16px;border-left:3px solid var(--accent-orange);">
            <div style="font-weight:700;color:var(--accent-orange);">No barangay coverage defined</div>
            <div style="font-size:12.5px;color:var(--text-muted);margin-top:2px;">
                Add the barangays your municipality covers under
                <a href="{{ route('sc.areas') }}" style="color:var(--accent-blue);font-weight:600;">Coverage Areas</a>
                before sorting parcels.
            </div>
        </div>
    @endif

    {{-- Area bins --}}
    <div style="font-size:11.5px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">
        Area Bins — Waiting for Rider Assignment
    </div>
    <div class="area-bins">
        @forelse($areas as $area)
            <a href="{{ route('sc.delivery-assignment',['area_id'=>$area->id]) }}" class="area-bin" style="text-decoration:none;">
                <span class="bin-name">{{ $area->name }}</span>
                <span class="bin-count">{{ $area->sorted_count ?? 0 }}</span>
            </a>
        @empty
            <div class="area-bin"><span class="bin-name">No areas yet</span><span class="bin-count">0</span></div>
        @endforelse
    </div>

    {{-- Sorting queue --}}
    <div class="card">
        <div class="card-header">
            <h2>Sorting Queue — <span style="color:var(--accent-orange);">{{ $pending->count() }} received</span></h2>
            <span class="card-meta">The destination barangay is read from the delivery address. Check it, then confirm.</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking</th><th>Recipient</th><th>Delivery Address</th>
                    <th>Weight</th><th>Destination Area</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($pending as $parcel)
                @php
                    $isCovered = $covered[$parcel->id] ?? true;
                    $suggest   = $suggested[$parcel->id] ?? null;
                @endphp
                <tr>
                    <td><span class="id-link">{{ $parcel->tracking_number }}</span></td>
                    <td>{{ $parcel->receiver_name }}</td>
                    <td style="max-width:240px;">
                        <span class="text-orange">{{ $parcel->dropoff_address }}</span>
                    </td>
                    <td>{{ $parcel->weight_kg ? $parcel->weight_kg.' kg' : '—' }}</td>
                    @if($isCovered)
                        <td>
                            <form method="POST" action="{{ route('sc.parcels.sort',$parcel) }}" id="sort-{{ $parcel->id }}">
                                @csrf
                                <select name="area_id" class="form-select" style="width:180px;" required>
                                    <option value="">Select barangay...</option>
                                    @foreach($areas as $area)
                                        <option value="{{ $area->id }}" @selected($suggest === $area->id)>{{ $area->name }}</option>
                                    @endforeach
                                </select>
                            </form>
                            <div class="text-sm" style="margin-top:4px;color:{{ $suggest ? 'var(--accent-green)' : 'var(--text-muted)' }};">
                                {{ $suggest ? 'Detected from address' : 'No match — choose manually' }}
                            </div>
                        </td>
                        <td>
                            <button class="btn btn-green btn-sm" form="sort-{{ $parcel->id }}">Confirm Sort</button>
                        </td>
                    @else
                        <td>
                            <span class="badge badge-orange">Outside your municipality</span>
                            <div class="text-muted text-sm" style="margin-top:4px;">Destination: {{ $parcel->destination_municipality }}</div>
                        </td>
                        <td>
                            <a href="{{ route('sc.transfers') }}" class="btn btn-ghost btn-sm" style="gap:4px;">
                                @include('sc.partials.icon', ['name' => 'transfer', 'size' => 13, 'sw' => 2]) Transfer
                            </a>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:30px;color:var(--text-muted);"><span style="display:inline-flex;align-items:center;gap:6px;color:var(--accent-green);">@include('sc.partials.icon', ['name' => 'check', 'size' => 16]) All received parcels are sorted.</span></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
