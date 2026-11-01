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
            Scan or type the waybill to find the parcel. You then check it and confirm receipt; scanning alone records nothing.
        </p>
        <form method="POST" action="{{ route('sc.parcels.scan') }}" id="scan-form" style="display:flex;gap:8px;max-width:520px;">
            @csrf
            <input type="text" name="tracking_number" class="form-input" placeholder="Scan the waybill, or type e.g. PH261019482915736"
                   required autofocus autocomplete="off" style="font-family:'SF Mono',Consolas,monospace;">
            <button class="btn btn-blue" style="gap:6px;">
                @include('sc.partials.icon', ['name' => 'inbox', 'size' => 15, 'sw' => 2]) Find parcel
            </button>
        </form>
        
        {{-- This center's recent scans --}}
        @if ($recentScans->isNotEmpty())
            <details style="margin-top:12px;" data-scan-history>
                <summary style="cursor:pointer;font-size:12.5px;font-weight:600;">Recent scans ({{ $recentScans->count() }})</summary>
                <table class="data-table" style="margin-top:8px;">
                    <thead><tr><th>Time</th><th>Scanned</th><th>Result</th><th>Parcel status</th></tr></thead>
                    <tbody>
                    @foreach ($recentScans as $scan)
                        @php
                            [$label, $badge] = match ($scan->status) {
                                'found'     => ['Found', 'badge-green'],
                                'duplicate' => ['Already received', 'badge-orange'],
                                'not_found' => ['Not found', 'badge-red'],
                                default     => ['Invalid code', 'badge-gray'],
                            };
                        @endphp
                        <tr data-scan-row="{{ $scan->status }}">
                            <td style="white-space:nowrap;">{{ $scan->created_at->tz(config('app.business_timezone'))->format('M j, g:i:s A') }}</td>
                            <td style="font-family:'SF Mono',Consolas,monospace;font-size:12px;">
                                @if ($scan->subject_type === 'Parcel' && $scan->subject_id)<a href="{{ route('sc.tracking.show', $scan->subject_id) }}">{{ $scan->old_value }}</a>@else{{ $scan->old_value }}@endif
                            </td>
                            <td><span class="badge {{ $badge }}">{{ $label }}</span></td>
                            <td>{{ $scan->new_value ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </details>
        @endif
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
        @if ($parcels->isNotEmpty())
            <a href="{{ route('sc.waybills', ['ids' => $parcels->pluck('id')->all()]) }}" target="_blank" class="btn btn-ghost btn-sm" style="gap:6px;">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
                Print waybills
            </a>
        @endif
    </div>

    <style>
        .ip-wrap { overflow-x:auto; }
        .ip-table td, .ip-table th { vertical-align:middle; }
        .ip-table .nw { white-space:nowrap; }
        .ip-name { max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
        .ip-dest { min-width:180px; max-width:240px; }
        .ip-dest-addr { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; line-height:1.35; }
    </style>
    <div class="card ip-wrap">
        <table class="data-table ip-table">
            <thead>
                <tr>
                    <th>Waybill / Tracking</th><th>Order</th><th>Sender</th><th>Recipient</th><th>Weight</th>
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
                    <td><span class="id-link">{{ $parcel->waybill_number ?? $parcel->tracking_number }}</span><div class="text-muted text-sm" style="white-space:nowrap;">{{ $parcel->tracking_number }} · <a href="{{ route('sc.parcels.waybill', $parcel) }}" target="_blank" style="color:var(--accent-blue);font-weight:600;">Waybill</a></div></td>
                    <td class="nw">{{ $parcel->order_id ? '#'.str_pad($parcel->order_id,6,'0',STR_PAD_LEFT) : '—' }}</td>
                    <td><div class="ip-name" title="{{ $parcel->seller->name ?? '' }}">{{ $parcel->seller->name ?? '—' }}</div></td>
                    <td><div class="ip-name" title="{{ $parcel->receiver_name }}">{{ $parcel->receiver_name }}</div></td>
                    <td class="nw">{{ $parcel->weight_kg ? $parcel->weight_kg.' kg' : '—' }}</td>
                    @php
                        // Street and barangay only; the city is shown above and the full address on hover.
                        $addrParts = array_map('trim', explode(',', (string) $parcel->dropoff_address));
                        $shortAddr = implode(', ', array_slice($addrParts, 0, 2));
                    @endphp
                    <td class="ip-dest" title="{{ $parcel->dropoff_address }}">
                        <span class="text-blue" style="font-weight:600;">{{ $parcel->destination_municipality ?? '—' }}</span>
                        <div class="text-muted text-sm ip-dest-addr">{{ $shortAddr ?: '—' }}</div>
                    </td>
                    <td class="nw" style="color:var(--text-muted);font-size:12px;">{{ $parcel->received_at?->format('M d, H:i') ?? '—' }}</td>
                    <td class="nw"><span class="badge {{ $badgeClass }}">{{ $parcel->statusLabel() }}</span>
                        @if($parcel->transfer_status === 'outgoing')
                            <span class="badge badge-orange" style="margin-left:4px;gap:4px;">@include('sc.partials.icon', ['name' => 'upload', 'size' => 12, 'sw' => 2]) Transferring</span>
                        @endif
                    </td>
                    <td class="nw">
                        @if($parcel->status === 'pickup_approved')
                            <a href="{{ route('sc.tracking.show', $parcel) }}" class="btn btn-blue btn-sm">Check &amp; receive</a>
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
