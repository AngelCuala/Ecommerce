@extends('sc.layout')
@section('title', 'Parcel Sorting')
@section('icon', '🔀')
@section('topbar-right')
    <span style="font-size:12px;font-weight:600;color:var(--accent-green);">{{ $sortedToday }} sorted today</span>
@endsection

@section('content')
<div class="page-header"><h1>Parcel Sorting</h1></div>
<div class="page-body">

    {{-- Area bins --}}
    <div style="font-size:11.5px;font-weight:600;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:10px;">
        Area Bins — Sorted Count
    </div>
    <div class="area-bins">
        @forelse($areas as $area)
            <div class="area-bin">
                <span class="bin-name">{{ $area->name }}</span>
                <span class="bin-count">{{ $area->sorted_count ?? 0 }}</span>
            </div>
        @empty
            <div class="area-bin"><span class="bin-name">No areas yet</span><span class="bin-count">0</span></div>
        @endforelse
    </div>

    {{-- Sorting queue --}}
    <div class="card">
        <div class="card-header">
            <h2>Sorting Queue — <span style="color:var(--accent-orange);">{{ $pending->count() }} pending</span></h2>
            <span style="font-size:12px;color:var(--accent-green);font-weight:600;">{{ $sortedToday }} sorted today</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking</th><th>Recipient</th><th>Destination</th>
                    <th>Type</th><th>Weight</th><th>Assign to Area</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($pending as $parcel)
                <tr>
                    <td><span class="id-link">{{ $parcel->tracking_number }}</span></td>
                    <td>{{ $parcel->receiver_name }}</td>
                    <td><span class="text-orange">{{ $parcel->dropoff_address }}</span></td>
                    <td>{{ $parcel->size ?? 'Parcel' }}</td>
                    <td>{{ $parcel->weight_kg ? $parcel->weight_kg.' kg' : '—' }}</td>
                    <td>
                        <form method="POST" action="{{ route('sc.parcels.sort',$parcel) }}" id="sort-{{ $parcel->id }}">
                            @csrf
                            <select name="area_id" class="form-select" style="width:150px;">
                                <option value="">Select area...</option>
                                @foreach($areas as $area)
                                    <option value="{{ $area->id }}">{{ $area->name }}</option>
                                @endforeach
                            </select>
                        </form>
                    </td>
                    <td>
                        <button class="btn btn-green btn-sm"
                                onclick="document.getElementById('sort-{{ $parcel->id }}').submit()">
                            Confirm Sort
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted);">🎉 All parcels sorted!</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

</div>
@endsection
