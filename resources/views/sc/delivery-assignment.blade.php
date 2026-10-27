@extends('sc.layout')
@section('title', 'Delivery Assignment')
@section('icon', 'location')

@section('content')
<div class="page-header"><h1>Delivery Assignment</h1></div>
<div class="page-body">

    @php
        $busyIds = \App\Models\ParcelDelivery::whereIn('rider_id', $riders->pluck('id'))
            ->whereIn('status', ['assigned','out_for_delivery'])->pluck('rider_id')->unique()->all();
        $ridersByArea = $riders->groupBy('area_id');
    @endphp

    {{-- Rider availability --}}
    <div style="font-size:13px;font-weight:600;margin-bottom:10px;">Rider Availability</div>
    <div class="rider-avail-grid">
        @forelse($riders as $rider)
            @php $busy = in_array($rider->id, $busyIds); @endphp
            <div class="rider-avail-card">
                <div class="ra-name">{{ $rider->full_name }}</div>
                <div class="ra-area">{{ $rider->area->name ?? 'No area' }}</div>
                <div class="ra-status {{ $busy ? 'on-delivery' : 'available' }}">
                    {{ $busy ? 'On Delivery' : 'Available' }}
                </div>
            </div>
        @empty
            <p style="color:var(--text-muted);font-size:12px;">
                No active riders. Add riders under <a href="{{ route('sc.riders') }}" style="color:var(--accent-blue);font-weight:600;">Rider Management</a>.
            </p>
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
        @if ($parcels->isNotEmpty())
            <a href="{{ route('sc.waybills', ['ids' => $parcels->pluck('id')->all()]) }}" target="_blank" class="btn btn-ghost btn-sm" style="gap:6px;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
                Print waybills
            </a>
        @endif
    </div>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Waybill</th><th>Recipient</th><th>Area</th><th>Address</th>
                    <th>Weight</th><th>Rider for This Area</th><th>Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($parcels as $parcel)
                @php $areaRiders = $ridersByArea->get($parcel->area_id, collect()); @endphp
                <tr>
                    <td>
                        <span class="id-link">{{ $parcel->waybill_number ?? $parcel->tracking_number }}</span>
                        <div class="text-muted text-sm" style="white-space:nowrap;"><a href="{{ route('sc.parcels.waybill', $parcel) }}" target="_blank" style="color:var(--accent-blue);font-weight:600;">Waybill</a></div>
                        @if($parcel->deliveries()->where('status','failed')->exists())
                            <div><span class="badge badge-red" style="font-size:10px;margin-top:4px;">Re-delivery</span></div>
                        @endif
                    </td>
                    <td>
                        {{ $parcel->receiver_name }}
                        <div class="text-muted text-sm">{{ $parcel->receiver_phone }}</div>
                    </td>
                    <td><span class="text-orange">{{ $parcel->area->name ?? '—' }}</span></td>
                    <td style="max-width:180px;color:var(--text-sub);">{{ $parcel->dropoff_address }}</td>
                    <td>{{ $parcel->weight_kg ? $parcel->weight_kg.' kg' : '—' }}</td>
                    @if($areaRiders->isEmpty())
                        <td colspan="2">
                            <span class="badge badge-yellow">No rider for this area</span>
                            <div class="text-muted text-sm" style="margin-top:4px;">
                                <a href="{{ route('sc.riders') }}" style="color:var(--accent-blue);">Add a rider</a> for {{ $parcel->area->name ?? 'this area' }}.
                            </div>
                        </td>
                    @else
                        <td>
                            <form method="POST" action="{{ route('sc.parcels.assign',$parcel) }}" id="assign-{{ $parcel->id }}">
                                @csrf
                                <select name="rider_id" class="form-select rider-select" required>
                                    @foreach($areaRiders->sortBy(fn($r) => in_array($r->id, $busyIds)) as $r)
                                        <option value="{{ $r->id }}">{{ $r->full_name }}{{ in_array($r->id, $busyIds) ? ' (on delivery)' : '' }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </td>
                        <td>
                            <button class="btn btn-blue btn-sm" form="assign-{{ $parcel->id }}">Assign</button>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:30px;color:var(--text-muted);">No sorted parcels waiting for a rider.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:12px;">{{ $parcels->links() }}</div>
</div>
@endsection
