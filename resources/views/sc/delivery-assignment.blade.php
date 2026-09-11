@extends('sc.layout')
@section('title', 'Delivery Assignment')
@section('icon', '📍')
@section('topbar-right')
    <button class="btn btn-orange" onclick="alert('Auto-assign feature coming soon.')">Auto-Assign by Area</button>
@endsection

@section('content')
<div class="page-header"><h1>Delivery Assignment</h1></div>
<div class="page-body">

    {{-- Rider availability --}}
    <div style="font-size:13px;font-weight:600;margin-bottom:10px;">Rider Availability</div>
    <div class="rider-avail-grid">
        @forelse($riders as $rider)
            @php $busy = $rider->parcelDeliveries()->whereIn('status',['assigned','out_for_delivery'])->exists(); @endphp
            <div class="rider-avail-card">
                <div class="ra-name">{{ $rider->full_name }}</div>
                <div class="ra-area">{{ $rider->area->name ?? '—' }}</div>
                <div class="ra-status {{ $busy ? 'on-delivery' : 'available' }}">
                    ● {{ $busy ? 'On Delivery' : 'Available' }}
                </div>
            </div>
        @empty
            <p style="color:var(--text-muted);font-size:12px;">No active riders.</p>
        @endforelse
    </div>

    {{-- Area tabs --}}
    <div class="flex-between mb-16">
        <div class="tab-bar" style="margin-bottom:0;">
            <a href="{{ route('sc.delivery-assignment') }}" class="tab {{ !request('area_id') ? 'active' : '' }}">All</a>
            @foreach($areas as $area)
                <a href="{{ route('sc.delivery-assignment',['area_id'=>$area->id]) }}"
                   class="tab {{ request('area_id')==$area->id ? 'active' : '' }}">{{ $area->name }}</a>
            @endforeach
        </div>
    </div>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking</th><th>Recipient</th><th>Area</th><th>Address</th>
                    <th>Weight</th><th>Assign Rider</th><th>Action</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
            @forelse($parcels as $parcel)
                <tr>
                    <td><span class="id-link">{{ $parcel->tracking_number }}</span></td>
                    <td>{{ $parcel->receiver_name }}</td>
                    <td><span class="text-orange">{{ $parcel->area->name ?? '—' }}</span></td>
                    <td style="max-width:150px;color:var(--text-sub);">{{ $parcel->dropoff_address }}</td>
                    <td>{{ $parcel->weight_kg ? $parcel->weight_kg.' kg' : '—' }}</td>
                    <td>
                        <select id="rider-sel-{{ $parcel->id }}" class="form-select rider-select">
                            <option value="">Select rider...</option>
                            @foreach($riders as $r)
                                <option value="{{ $r->id }}">{{ $r->full_name }}</option>
                            @endforeach
                        </select>
                    </td>
                    <td>
                        <form method="POST" action="{{ route('sc.parcels.assign',$parcel) }}" id="assign-{{ $parcel->id }}">
                            @csrf
                            <input type="hidden" name="rider_id" id="rider-hid-{{ $parcel->id }}">
                            <button type="button" class="btn btn-blue btn-sm"
                                onclick="document.getElementById('rider-hid-{{ $parcel->id }}').value=document.getElementById('rider-sel-{{ $parcel->id }}').value;document.getElementById('assign-{{ $parcel->id }}').submit();">
                                Assign
                            </button>
                        </form>
                    </td>
                    <td><span class="badge badge-yellow">Unassigned</span></td>
                </tr>
            @empty
                <tr><td colspan="8" style="text-align:center;padding:30px;color:var(--text-muted);">No parcels waiting for assignment.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:12px;">{{ $parcels->links() }}</div>
</div>
@endsection
