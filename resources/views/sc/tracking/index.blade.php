@extends('sc.layout')
@section('title', 'Shipment Board')
@section('icon', 'list')

@php
    $tabs = \App\Http\Controllers\SortingCenter\ShipmentController::TABS;
    $badge = fn ($s) => match ($s) {
        'pickup_approved', 'in_transit_to_hub' => 'badge-yellow',
        'picked_up', 'sorting' => 'badge-orange',
        'sorted', 'assigned' => 'badge-blue',
        'in_transfer', 'in_transit' => 'badge-blue',
        'delivered', 'returned' => 'badge-green',
        'failed', 'return_to_sender', 'lost', 'damaged' => 'badge-red',
        default => 'badge-gray',
    };
@endphp

@section('content')
<div class="page-header"><h1>Shipment Board</h1></div>
<div class="page-body">

    {{-- Scan: finds the parcel; receipt is confirmed on the next page --}}
    <div class="card" style="padding:16px 18px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;justify-content:space-between;">
        <div style="flex:1;min-width:280px;max-width:520px;">
        <form method="POST" action="{{ route('sc.parcels.scan') }}" id="scan-form" style="display:flex;gap:8px;">
            @csrf
            <input type="text" name="tracking_number" class="form-input" placeholder="Scan or type a waybill / tracking number" required autofocus autocomplete="off" style="font-family:'SF Mono',Consolas,monospace;">
            <button class="btn btn-blue">Find</button>
        </form>
        <div style="margin-top:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">@include('partials.waybill-scanner', ['lookup' => route('sc.parcels.scan')])<span class="text-muted" style="font-size:12px;">Opens the parcel straight from its waybill QR code.</span></div>
        </div>
        <form method="GET" action="{{ route('sc.tracking') }}" style="display:flex;gap:8px;flex-wrap:wrap;">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <input type="text" name="q" value="{{ request('q') }}" class="form-input" placeholder="Tracking, #order, seller, municipality" style="width:240px;">
            <select name="status" class="form-input" style="width:auto;">
                <option value="">Any status</option>
                @foreach (\App\Models\Parcel::STATUS_LABELS as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
            </select>
            <button class="btn btn-ghost">Search</button>
            @if (request()->hasAny(['q', 'status']))<a href="{{ route('sc.tracking', ['tab' => $tab]) }}" class="btn btn-ghost">Reset</a>@endif
        </form>
    </div>

    {{-- Sections --}}
    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:14px;">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('sc.tracking', ['tab' => $key]) }}" data-tab="{{ $key }}"
               style="padding:6px 12px;border-radius:999px;font-size:12.5px;font-weight:600;border:1px solid {{ $tab === $key ? 'var(--accent-blue)' : 'var(--border)' }};
                      background:{{ $tab === $key ? 'var(--accent-blue)' : '#fff' }};color:{{ $tab === $key ? '#fff' : 'var(--text-sub)' }};">
                {{ $label }}
                <span style="margin-left:4px;opacity:.8;{{ in_array($key, ['delayed', 'exceptions'], true) && $counts[$key] > 0 && $tab !== $key ? 'color:#DC2626;' : '' }}">{{ $counts[$key] }}</span>
            </a>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header"><h2>{{ $tabs[$tab] }}</h2><span class="card-meta">{{ $parcels->total() }} {{ \Illuminate\Support\Str::plural('parcel', $parcels->total()) }}</span></div>
        {{-- Fits the card (no sideways scroll): related details share a column. --}}
        <style>
            .board-table th, .board-table td { padding-left: 12px; padding-right: 12px; }
            .board-table .sub { color: var(--text-muted); font-size: 11px; line-height: 1.4; }
        </style>
        <table class="data-table board-table">
            <thead>
                <tr><th>Parcel</th><th>Status</th><th>Route</th><th>Latest</th><th></th></tr>
            </thead>
            <tbody>
            @forelse ($parcels as $p)
                @php
                    $t = $p->activeTransfer;
                    $last = $p->events->first();
                    $task = $p->status === 'in_transfer' && $t?->transporter ? 'Transport: ' . $t->transporter->full_name
                        : (in_array($p->status, ['assigned', 'in_transit'], true) ? 'Delivery: ' . ($p->parcelDelivery?->rider?->full_name ?? '—')
                        : (in_array($p->status, ['pickup_approved', 'in_transit_to_hub'], true) && $p->pickup?->rider && in_array($p->pickup->status, \App\Models\ParcelPickup::OPEN, true) ? 'Pickup: ' . $p->pickup->rider->full_name : '—'));
                    $from = $t && $p->status === 'in_transfer' ? \App\Support\SortingCenters::nameOf($t->fromSortingCenter) : (in_array($p->status, ['pickup_approved', 'in_transit_to_hub'], true) ? 'Seller' : 'This center');
                    $dest = $t ? 'Transfer → ' . \App\Support\SortingCenters::nameOf($t->toSortingCenter) . ' (' . $t->statusLabel() . ')' : ($next[$p->id]['label'] ?? ($p->area?->name ? 'Delivery: ' . $p->area->name : $p->destination_municipality));
                @endphp
                <tr data-parcel="{{ $p->id }}">
                    @php $tz = config('app.business_timezone'); @endphp
                    <td>
                        <a href="{{ route('sc.tracking.show', $p) }}" class="id-link">{{ $p->waybill_number ?: $p->tracking_number }}</a>
                        <div class="sub">{{ $p->tracking_number }}</div>
                        <div class="sub">{{ $p->order?->number() ?? '—' }} · {{ $p->seller?->storeName() ?? $p->seller?->name }}</div>
                    </td>
                    <td><span class="badge {{ $badge($p->status) }}">{{ $t?->isDelayed() ? 'Delayed in transit' : $p->statusLabel() }}</span></td>
                    <td style="font-size:12px;">
                        <span style="color:var(--text-muted);">{{ $from }}</span> → {{ $dest }}
                        @if ($task !== '—')<div class="sub">{{ $task }}</div>@endif
                    </td>
                    <td style="font-size:12px;">
                        {{ $last?->label() ?? '—' }} <span class="sub">· {{ $last?->occurred_at?->diffForHumans() }}</span>
                        @if ($p->received_at)<div class="sub">Received {{ $p->received_at->copy()->tz($tz)->format('M j, g:i A') }}</div>@endif
                        @if ($t?->dispatched_at)<div class="sub">Dispatched {{ $t->dispatched_at->copy()->tz($tz)->format('M j, g:i A') }}</div>@endif
                        @if ($t?->expected_by && $t->isInTransit())<div class="sub">Due {{ $t->expected_by->copy()->tz($tz)->format('M j, g:i A') }}</div>@endif
                    </td>
                    <td style="white-space:nowrap;">
                        @if (in_array($p->status, ['pickup_approved', 'in_transit_to_hub'], true) || ($p->status === 'in_transfer' && $t?->isInTransit() && (int) $t->to_sorting_center_id === auth()->id()))
                            <a href="{{ route('sc.tracking.show', $p) }}" class="btn btn-blue btn-sm">Check &amp; receive</a>
                        @elseif ($p->status === 'picked_up' && ! $p->transfer_status)
                            <form method="POST" action="{{ route('sc.tracking.start-sorting', $p) }}" style="display:inline;" onsubmit="return confirm('Start sorting {{ $p->tracking_number }}?')">@csrf<button class="btn btn-blue btn-sm">Start sorting</button></form>
                        @elseif ($t?->isPending() && (int) $t->from_sorting_center_id === auth()->id())
                            <form method="POST" action="{{ route('sc.transfers.dispatch', $t) }}" style="display:inline;" onsubmit="return confirm('Confirm {{ $p->tracking_number }} is leaving for {{ \App\Support\SortingCenters::nameOf($t->toSortingCenter) }} now?')">@csrf<button class="btn btn-blue btn-sm">Confirm dispatch</button></form>
                        @elseif ($p->status === 'sorting' || ($p->status === 'picked_up'))
                            <a href="{{ route('sc.tracking.show', $p) }}" class="btn btn-ghost btn-sm">Ready for dispatch…</a>
                        @elseif ($p->status === 'sorted' && ! $p->transfer_status)
                            <a href="{{ route('sc.dispatch') }}" class="btn btn-ghost btn-sm">Prepare final delivery</a>
                        @else
                            <a href="{{ route('sc.tracking.show', $p) }}" class="btn btn-ghost btn-sm">Details</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" style="padding:28px;text-align:center;color:var(--text-muted);">Nothing in {{ strtolower($tabs[$tab]) }}{{ request('q') ? ' matching "' . request('q') . '"' : '' }}.</td></tr>
            @endforelse
            </tbody>
        </table>
        @if ($parcels->hasPages())<div style="padding:12px 16px;">{{ $parcels->links() }}</div>@endif
    </div>
</div>
@endsection
