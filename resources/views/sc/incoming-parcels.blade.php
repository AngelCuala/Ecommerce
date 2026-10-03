@extends('sc.layout')
@section('title', 'Incoming Parcels')
@section('icon', 'inbox')
@section('topbar-right')
    <input type="text" class="search-input" placeholder="Filter tracking, sender, recipient..."
           onkeyup="filterTable(this.value,'parcels-tbody')" />
@endsection

@section('content')
<div class="page-header"><h1>Incoming Parcels</h1></div>
<div class="page-body">

    {{-- Receive + scan --}}
    <div class="card" style="padding:16px 18px;margin-bottom:18px;">
        <div style="font-size:14px;font-weight:700;margin-bottom:4px;">Receive Parcel</div>
        <p class="text-muted" style="font-size:12.5px;margin-bottom:10px;">
            Scan or type the tracking number when the pickup rider drops a parcel off at the center.
        </p>
        <form method="POST" action="{{ route('sc.parcels.scan') }}" style="display:flex;gap:8px;max-width:520px;">
            @csrf
            <input type="text" name="tracking_number" class="form-input" placeholder="e.g. ALVY-1A2B3C4D5E"
                   required autofocus autocomplete="off" style="font-family:'SF Mono',Consolas,monospace;">
            <button class="btn btn-blue" style="gap:6px;">
                @include('sc.partials.icon', ['name' => 'inbox', 'size' => 15, 'sw' => 2]) Receive
            </button>
        </form>
    </div>

    {{-- Mini stats --}}
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px;">
        @foreach([['Awaiting Arrival',$miniStats['awaiting']],['Received Today',$miniStats['received']],['For Sorting',$miniStats['for_sorting']],['Sorted',$miniStats['sorted']]] as [$lbl,$val])
        <div class="stat-card" style="padding:12px 16px;">
            <div class="stat-label" style="font-size:11px;margin-bottom:3px;">{{ $lbl }}</div>
            <div class="stat-value" style="font-size:24px;">{{ $val }}</div>
        </div>
        @endforeach
    </div>

    @php $cur = request('tab','all'); @endphp
    <div class="flex-between mb-16">
        <div class="tab-bar" style="margin-bottom:0;">
            @foreach(['all'=>'All','pickup_approved'=>'Awaiting Arrival','picked_up'=>'For Sorting','sorted'=>'Sorted','assigned'=>'Assigned'] as $key => $label)
                <a href="{{ route('sc.incoming-parcels',['tab'=>$key]) }}"
                   class="tab {{ $cur===$key ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking No.</th><th>Order</th><th>Sender</th><th>Recipient</th><th>Weight</th>
                    <th>Destination</th><th>Received</th><th>Status</th><th>Action</th>
                </tr>
            </thead>
            <tbody id="parcels-tbody">
            @forelse($parcels as $parcel)
                @php
                    $badgeClass = match($parcel->status) {
                        'sorted'          => 'badge-green',
                        'picked_up'       => 'badge-blue',
                        'pickup_approved' => 'badge-orange',
                        'assigned'        => 'badge-purple',
                        default           => 'badge-gray',
                    };
                @endphp
                <tr>
                    <td><span class="id-link">{{ $parcel->tracking_number }}</span></td>
                    <td>{{ $parcel->order_id ? '#'.str_pad($parcel->order_id,6,'0',STR_PAD_LEFT) : '—' }}</td>
                    <td>{{ $parcel->seller->name ?? '—' }}</td>
                    <td>{{ $parcel->receiver_name }}</td>
                    <td>{{ $parcel->weight_kg ? $parcel->weight_kg.' kg' : '—' }}</td>
                    <td style="max-width:200px;">
                        <span class="text-blue">{{ $parcel->destination_municipality ?? '—' }}</span>
                        <div class="text-muted text-sm">{{ $parcel->dropoff_address }}</div>
                    </td>
                    <td style="color:var(--text-muted);font-size:12px;">{{ $parcel->received_at?->format('M d, H:i') ?? '—' }}</td>
                    <td><span class="badge {{ $badgeClass }}">{{ $parcel->statusLabel() }}</span>
                        @if($parcel->transfer_status === 'outgoing')
                            <span class="badge badge-orange" style="margin-left:4px;gap:4px;">@include('sc.partials.icon', ['name' => 'upload', 'size' => 12, 'sw' => 2]) Transferring</span>
                        @endif
                    </td>
                    <td>
                        @if($parcel->status === 'pickup_approved')
                            <form method="POST" action="{{ route('sc.parcels.advance',$parcel) }}">@csrf
                                <button class="btn btn-blue btn-sm">Mark Received</button>
                            </form>
                        @elseif($parcel->status === 'picked_up')
                            <a href="{{ route('sc.parcel-sorting') }}" class="btn btn-blue btn-sm">Sort</a>
                        @elseif($parcel->status === 'sorted')
                            <a href="{{ route('sc.delivery-assignment',['area_id'=>$parcel->area_id]) }}" class="btn btn-ghost btn-sm">Assign</a>
                        @else
                            <span class="text-muted text-sm">{{ $parcel->area->name ?? '—' }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;padding:30px;color:var(--text-muted);">No parcels found.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:12px;">{{ $parcels->links() }}</div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/table-filter.js') }}"></script>
@endpush
