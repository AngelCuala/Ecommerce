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
            <input type="text" name="q" value="{{ request('q') }}" class="form-input" placeholder="Tracking, #order, seller, municipality" style="width:240px;">
            <select name="status" class="form-input" style="width:auto;">
                <option value="">Any status</option>
                @foreach (\App\Models\Parcel::STATUS_LABELS as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
            </select>
            @foreach (['bucket', 'from', 'to'] as $keep)@if (request($keep))<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif @endforeach
            <button class="btn btn-ghost">Search</button>
            @if (request('bucket') || request('from') || request('to'))
                <span class="badge badge-blue" style="align-self:center;" data-board-filter>{{ ucfirst(str_replace('_', ' ', request('bucket') ?: 'all')) }}{{ request('from') || request('to') ? ' · ' . (request('from') ?: '…') . ' to ' . (request('to') ?: '…') : '' }}</span>
            @endif
            @if (request()->hasAny(['q', 'status', 'bucket', 'from', 'to']))<a href="{{ route('sc.tracking') }}" class="btn btn-ghost">Reset</a>@endif
        </form>
    </div>

    {{-- The inventory: every parcel this center handles. The status opens the page where its job is done. --}}
    <div class="card">
        <div class="card-header"><h2>{{ $tab === 'all' ? 'All parcels' : $tabs[$tab] }}</h2><span class="card-meta">{{ $parcels->total() }} {{ \Illuminate\Support\Str::plural('parcel', $parcels->total()) }} · click a status to open its page</span></div>
        <style>
            .board-table th, .board-table td { padding-left: 12px; padding-right: 12px; }
            .board-table .sub { color: var(--text-muted); font-size: 11px; line-height: 1.4; }
            .board-status { display: inline-flex; flex-direction: column; gap: 3px; text-decoration: none !important; }
            .board-status .go { font-size: 11px; color: var(--accent-blue); font-weight: 600; }
        </style>
        <table class="data-table board-table">
            <thead>
                <tr><th>Parcel</th><th>Status</th><th>Route</th><th>Latest</th></tr>
            </thead>
            <tbody>
            @forelse ($parcels as $p)
                @php
                    $t = $p->activeTransfer;
                    $last = $p->events->first();
                    [$pageName, $pageUrl] = \App\Support\ParcelJobPage::for($p);
                    $from = $t && $p->status === 'in_transfer' ? \App\Support\SortingCenters::nameOf($t->fromSortingCenter) : (in_array($p->status, ['pending_pickup', 'pickup_approved', 'in_transit_to_hub'], true) ? 'Seller' : 'This center');
                    $dest = $t ? 'Transfer → ' . \App\Support\SortingCenters::nameOf($t->toSortingCenter)
                        : ($p->next_center_id ? 'Hub-to-hub → ' . \App\Support\SortingCenters::nameOf($p->nextCenter)
                        : ($next[$p->id]['label'] ?? ($p->area?->name ? 'Delivery: ' . $p->area->name : $p->destination_municipality)));
                    $flagged = $pageName === 'Flagged Parcels';
                @endphp
                <tr data-parcel="{{ $p->id }}">
                    @php $tz = config('app.business_timezone'); @endphp
                    <td>
                        <a href="{{ route('sc.tracking.show', $p) }}" class="id-link">{{ $p->waybill_number ?: $p->tracking_number }}</a>
                        <div class="sub">{{ $p->tracking_number }}</div>
                        <div class="sub">{{ $p->order?->number() ?? '—' }} · {{ $p->seller?->storeName() ?? $p->seller?->name }}</div>
                    </td>
                    <td>
                        <a href="{{ $pageUrl }}" class="board-status" data-status-link="{{ $p->status }}" title="Open {{ $pageName }}">
                            <span class="badge {{ $flagged ? 'badge-red' : $badge($p->status) }}">{{ $flagged ? 'Flagged · on hold' : ($t?->isDelayed() ? 'Delayed in transit' : $p->statusLabel()) }}</span>
                            <span class="go">{{ $pageName }} →</span>
                        </a>
                    </td>
                    <td style="font-size:12px;">
                        <span style="color:var(--text-muted);">{{ $from }}</span> → {{ $dest }}
                    </td>
                    <td style="font-size:12px;">
                        {{ $last?->label() ?? '—' }} <span class="sub">· {{ $last?->occurred_at?->diffForHumans() }}</span>
                        @if ($p->received_at)<div class="sub">Received {{ $p->received_at->copy()->tz($tz)->format('M j, g:i A') }}</div>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" style="padding:28px;text-align:center;color:var(--text-muted);">No parcels{{ request('q') ? ' matching "' . request('q') . '"' : '' }}.</td></tr>
            @endforelse
            </tbody>
        </table>
        @if ($parcels->hasPages())<div style="padding:12px 16px;">{{ $parcels->links() }}</div>@endif
    </div>

    {{-- This center's recent waybill scans (moved here from the old Incoming Parcels page). --}}
    @php $recentScans = \App\Models\ActivityLog::where('action', 'sc_scan')->where('admin_id', auth()->id())->latest('id')->limit(15)->get(); @endphp
    @if ($recentScans->isNotEmpty())
    <div class="card" style="padding:4px 16px 12px;margin-top:16px;">
        @if ($recentScans->isNotEmpty())
            <details style="margin-top:8px;" data-scan-history>
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
    @endif
</div>
@endsection
