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

    {{-- Scan: finds the parcel (receipt is confirmed on the next page); then the board's own search and filter. --}}
    <style>
        .sb-tools { padding: 16px 18px; margin-bottom: 16px; display: grid; gap: 14px 24px; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); align-items: start; }
        @media (max-width: 1100px) { .sb-tools { grid-template-columns: minmax(0, 1fr); } }
        .sb-label { display: block; margin-bottom: 6px; font-size: 12px; font-weight: 700; color: var(--text-sub); }
        .sb-row { display: grid; gap: 8px; grid-template-columns: minmax(0, 1fr) auto auto; align-items: stretch; }
        .sb-row > * { min-height: 42px; }
        .sb-code { font-family: 'SF Mono', Consolas, monospace; font-size: 14px; }
        .sb-code::placeholder { font-family: Inter, system-ui, sans-serif; font-size: 13px; }
        .sb-btn { justify-content: center; min-width: 84px; font-size: 13.5px; border-radius: 9px; }
        .sb-find { background: #fa4e1c !important; border-color: #fa4e1c !important; color: #fff !important; }
        .sb-row .wbs-trigger { height: 100%; min-height: 42px; justify-content: center; padding: 0 16px; font-size: 13.5px; }
        .sb-hint { margin: 6px 0 0; font-size: 12px; color: var(--text-muted); }
        .sb-filter { display: grid; gap: 8px; grid-template-columns: minmax(0, 1fr) minmax(0, 170px) auto; align-items: stretch; }
        .sb-actions { display: flex; gap: 8px; }
        .sb-actions > * { flex: 1; }
        .sb-filter > * { min-height: 42px; }
        @media (max-width: 560px) {
            .sb-row { grid-template-columns: 1fr 1fr; }
            .sb-row > :first-child { grid-column: 1 / -1; }
            .sb-filter { grid-template-columns: 1fr; }
        }
    </style>
    <div class="card sb-tools" data-board-tools>
        <div>
            <label class="sb-label" for="sb-code">Find a parcel</label>
            {{-- The scanner pop-up has its own form, so the input and Find button join this form by its id. --}}
            <form method="POST" action="{{ route('sc.parcels.scan') }}" id="scan-form">@csrf</form>
            <div class="sb-row">
                <input id="sb-code" type="text" name="tracking_number" form="scan-form" class="form-input sb-code" placeholder="Waybill or tracking number" required autofocus autocomplete="off">
                <button form="scan-form" class="btn sb-btn sb-find" data-keep-colors>Find</button>
                @include('partials.waybill-scanner', ['lookup' => route('sc.parcels.scan'), 'label' => 'Scan QR', 'style' => 'ghost'])
            </div>
            <p class="sb-hint">Scan QR opens the camera and goes straight to the parcel.</p>
        </div>

        <div>
            <label class="sb-label" for="sb-q">Filter the board</label>
            <form method="GET" action="{{ route('sc.tracking') }}" class="sb-filter">
                <input id="sb-q" type="text" name="q" value="{{ request('q') }}" class="form-input" placeholder="Tracking, #order, seller, municipality">
                <select name="status" class="form-input" aria-label="Status">
                    <option value="">Any status</option>
                    @foreach (\App\Models\Parcel::STATUS_LABELS as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
                </select>
                @foreach (['bucket', 'from', 'to'] as $keep)@if (request($keep))<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif @endforeach
                <div class="sb-actions">
                    <button class="btn btn-ghost sb-btn">Search</button>
                    @if (request()->hasAny(['q', 'status', 'bucket', 'from', 'to']))<a href="{{ route('sc.tracking') }}" class="btn btn-ghost sb-btn">Reset</a>@endif
                </div>
            </form>
            @if (request('bucket') || request('from') || request('to'))
                <span class="badge badge-blue" style="margin-top:8px;" data-board-filter>{{ ucfirst(str_replace('_', ' ', request('bucket') ?: 'all')) }}{{ request('from') || request('to') ? ' · ' . (request('from') ?: '…') . ' to ' . (request('to') ?: '…') : '' }}</span>
            @endif
        </div>
    </div>

    {{-- The inventory: every parcel this center handles. The status opens the page where its job is done. --}}
    <div class="card">
        <div class="card-header"><h2>{{ $tab === 'all' ? 'All parcels' : $tabs[$tab] }}</h2><span class="card-meta">{{ $parcels->total() }} {{ \Illuminate\Support\Str::plural('parcel', $parcels->total()) }} · click a status to open its page</span></div>
        <style>
            .board-table th, .board-table td { padding-left: 12px; padding-right: 12px; }
            .board-table .sub { color: var(--text-muted); font-size: 11px; line-height: 1.4; }
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
                @endphp
                <tr data-parcel="{{ $p->id }}">
                    @php $tz = config('app.business_timezone'); @endphp
                    <td>
                        <a href="{{ route('sc.tracking.show', $p) }}" class="id-link">{{ $p->waybill_number ?: $p->tracking_number }}</a>
                        <div class="sub">{{ $p->tracking_number }}</div>
                        <div class="sub">{{ $p->order?->number() ?? '—' }} · {{ $p->seller?->storeName() ?? $p->seller?->name }}</div>
                    </td>
                    <td>
                        <a href="{{ $pageUrl }}" class="status-link" data-status-link="{{ $p->status }}" title="Open {{ $pageName }}">
                            <span class="badge {{ $badge($p->status) }}">{{ $t?->isDelayed() ? 'Delayed in transit' : $p->statusLabel() }}</span>
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
