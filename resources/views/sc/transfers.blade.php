@extends('sc.layout')
@section('title', 'SC Transfers')
@section('icon', 'transfer')

@php
    $delayed = $outgoing->merge($incoming)->filter->isDelayed()->count();
    $badge = fn ($t) => $t->isDelayed() || $t->status === 'exception' ? 'badge-red' : ($t->isPending() ? 'badge-yellow' : 'badge-blue');
@endphp

@section('content')
<style>
    .tr-stats { display: grid; gap: 12px; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-bottom: 18px; }
    @media (max-width: 900px) { .tr-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .tr-stat { display: flex; align-items: center; gap: 12px; padding: 14px 16px; }
    .tr-stat-ic { display: flex; width: 40px; height: 40px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 11px; }
    .tr-stat-n { font-size: 24px; font-weight: 800; line-height: 1; color: var(--text); }
    .tr-stat-l { margin-top: 3px; font-size: 12px; color: var(--text-muted); }
    .tr-top { display: grid; gap: 18px; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); align-items: start; margin-bottom: 18px; }
    @media (max-width: 1000px) { .tr-top { grid-template-columns: 1fr; } }
    .tr-label { display: block; margin-bottom: 5px; font-size: 12px; font-weight: 600; color: var(--text-muted); }
    .tr-empty { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 28px 20px; text-align: center; font-size: 13px; color: var(--text-muted); }
    .tr-empty span { display: flex; width: 42px; height: 42px; align-items: center; justify-content: center; border-radius: 12px; background: #F1F5F9; color: #94A3B8; }
    .tr-ref { font-family: 'SF Mono', Consolas, monospace; font-weight: 700; white-space: nowrap; }
    .tr-sub { margin-top: 2px; font-size: 11.5px; color: var(--text-muted); white-space: nowrap; }
    .tr-dir { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; padding: 2px 9px; font-size: 11.5px; font-weight: 600; white-space: nowrap; }
</style>

<div class="page-header"><h1>Sorting Center Transfers</h1></div>
<div class="page-body">

    {{-- Summary --}}
    <div class="tr-stats" data-transfer-stats>
        @foreach ([
            ['Awaiting dispatch', $outgoing->where('status', 'pending')->count(), 'upload', '#FFF4E5', 'var(--accent-orange)'],
            ['Sent, in transit', $outgoing->where('status', 'in_transit')->count(), 'transfer', '#EAF2FB', 'var(--accent-blue)'],
            ['Coming to you', $incoming->where('status', 'in_transit')->count(), 'inbox', '#E9F8EF', 'var(--accent-green)'],
            ['Delayed', $delayed, 'clock', '#FEF2F2', 'var(--accent-red)'],
        ] as [$label, $n, $ic, $bg, $fg])
            <div class="card tr-stat">
                <span class="tr-stat-ic" style="background:{{ $bg }};color:{{ $fg }};">@include('sc.partials.icon', ['name' => $ic, 'size' => 19, 'sw' => 2])</span>
                <div><div class="tr-stat-n" style="{{ $label === 'Delayed' && $n ? 'color:var(--accent-red);' : '' }}">{{ $n }}</div><div class="tr-stat-l">{{ $label }}</div></div>
            </div>
        @endforeach
    </div>

    <div class="tr-top">
        {{-- Create --}}
        <div class="card" data-transfer-create>
            <div class="card-header"><h2>Create a transfer</h2><span class="card-meta">Send a parcel to another sorting center</span></div>
            @if ($myParcels->isEmpty())
                <div class="tr-empty">
                    <span>@include('sc.partials.icon', ['name' => 'parcel', 'size' => 20])</span>
                    <b style="color:var(--text);">No parcels available to transfer</b>
                    Only parcels received at your hub, with no active transfer or rider, can be transferred.
                </div>
            @else
                <form action="{{ route('sc.transfers.initiate') }}" method="POST" style="display:grid;gap:12px;padding:16px 18px;"
                      data-confirm="The parcel stays here until you confirm dispatch." data-confirm-title="Create this transfer?" data-confirm-ok="Create transfer">
                    @csrf
                    <div>
                        <label class="tr-label" for="tr-parcel">Parcel</label>
                        <select id="tr-parcel" name="parcel_id" required class="form-select" style="width:100%;">
                            <option value="">Choose a parcel…</option>
                            @foreach ($myParcels as $p)
                                <option value="{{ $p->id }}" @selected(old('parcel_id') == $p->id)>{{ $p->tracking_number }} · to {{ $p->destination_municipality }} · {{ $suggested[$p->id]['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="tr-label" for="tr-to">Destination sorting center <span style="font-weight:500;">({{ \App\Support\ServiceArea::label() }})</span></label>
                        <select id="tr-to" name="to_sorting_center_id" required class="form-select" style="width:100%;">
                            <option value="">Select destination…</option>
                            @foreach ($otherCenters as $sc)
                                <option value="{{ $sc->id }}" @selected(old('to_sorting_center_id') == $sc->id)>{{ \App\Support\SortingCenters::displayName($sc) }} · {{ $sc->assigned_municipality }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="tr-label" for="tr-rider">Transporter <span style="font-weight:500;">(optional)</span></label>
                        <select id="tr-rider" name="transporter_rider_id" class="form-select" style="width:100%;">
                            <option value="">None</option>
                            @foreach ($transporters as $r)<option value="{{ $r->id }}">{{ $r->full_name }}</option>@endforeach
                        </select>
                    </div>
                    <div>
                        <label class="tr-label" for="tr-reason">Reason <span style="font-weight:500;">(needed when it isn't the center serving the address)</span></label>
                        <textarea id="tr-reason" name="reason" rows="2" class="form-input" style="width:100%;resize:vertical;">{{ old('reason') }}</textarea>
                    </div>
                    @if ($errors->any())<p style="color:#B91C1C;font-size:12.5px;">{{ $errors->first() }}</p>@endif
                    <button type="submit" class="btn btn-blue" style="width:100%;justify-content:center;gap:6px;">
                        @include('sc.partials.icon', ['name' => 'upload', 'size' => 15, 'sw' => 2]) Create transfer
                    </button>
                </form>
            @endif
        </div>

        {{-- Incoming --}}
        <div class="card" data-transfer-incoming>
            <div class="card-header"><h2>Incoming transfers</h2><span class="card-meta">{{ $incoming->count() }} active</span></div>
            @if ($incoming->isEmpty())
                <div class="tr-empty">
                    <span>@include('sc.partials.icon', ['name' => 'inbox', 'size' => 20])</span>
                    <b style="color:var(--text);">Nothing on its way to you</b>
                    Parcels other centers send you show up here.
                </div>
            @else
                <table class="data-table">
                    <thead><tr><th>Transfer</th><th>From</th><th>Status</th><th style="text-align:right;">Action</th></tr></thead>
                    <tbody>
                        @foreach ($incoming as $t)
                        <tr data-transfer="{{ $t->reference }}">
                            <td><span class="id-link tr-ref">{{ $t->reference }}</span><div class="tr-sub">{{ $t->parcel->tracking_number }}</div></td>
                            <td style="font-size:12.5px;">{{ \App\Support\SortingCenters::nameOf($t->fromSortingCenter) }}</td>
                            <td><span class="badge {{ $badge($t) }}">{{ $t->isPending() ? 'Not dispatched yet' : $t->statusLabel() }}</span>
                                @if ($t->expected_by && $t->isInTransit())<div class="tr-sub">Due {{ $t->expected_by->format('M j, g:i A') }}</div>@endif</td>
                            <td style="text-align:right;white-space:nowrap;">
                                @if ($t->isInTransit())
                                    <a href="{{ route('sc.tracking.show', $t->parcel) }}" class="btn btn-sm btn-blue">Check &amp; receive</a>
                                @elseif ($t->isPending())
                                    <form action="{{ route('sc.transfers.reject', $t->id) }}" method="POST" style="display:inline;"
                                          data-confirm="The parcel stays with the sending center." data-confirm-title="Decline this transfer?" data-confirm-ok="Decline">@csrf
                                        <button class="btn btn-sm btn-ghost" style="color:#DC2626;border-color:#f3c9c9;">Decline</button></form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>

    {{-- Outgoing (full width) --}}
    <div class="card" style="margin-bottom:18px;" data-transfer-outgoing>
        <div class="card-header"><h2>Outgoing transfers</h2><span class="card-meta">{{ $outgoing->count() }} active</span></div>
        @if ($outgoing->isEmpty())
            <div class="tr-empty" style="padding:22px;">No outgoing transfers.</div>
        @else
            <table class="data-table">
                <thead><tr><th>Transfer</th><th>To</th><th>Status</th><th>Sent</th><th style="text-align:right;">Action</th></tr></thead>
                <tbody>
                    @foreach ($outgoing as $t)
                    <tr data-transfer="{{ $t->reference }}">
                        <td><a href="{{ route('sc.tracking.show', $t->parcel) }}" class="id-link tr-ref">{{ $t->reference }}</a><div class="tr-sub">{{ $t->parcel->tracking_number }}</div></td>
                        <td>{{ \App\Support\SortingCenters::nameOf($t->toSortingCenter) }}</td>
                        <td><span class="badge {{ $badge($t) }}">{{ $t->statusLabel() }}</span></td>
                        <td style="white-space:nowrap;color:var(--text-muted);font-size:12.5px;">{{ $t->dispatched_at?->format('M j, g:i A') ?? 'Not yet' }}</td>
                        <td style="text-align:right;white-space:nowrap;">
                            @if ($t->isPending())
                                <form action="{{ route('sc.transfers.dispatch', $t->id) }}" method="POST" style="display:inline;"
                                      data-confirm="Confirm {{ $t->parcel->tracking_number }} is leaving your hub now." data-confirm-title="Confirm dispatch?" data-confirm-ok="Confirm dispatch">@csrf
                                    <button class="btn btn-sm btn-blue">Confirm dispatch</button></form>
                                <form action="{{ route('sc.transfers.cancel', $t->id) }}" method="POST" style="display:inline;"
                                      data-confirm="The parcel stays at your hub." data-confirm-title="Cancel this transfer?" data-confirm-ok="Cancel transfer">@csrf
                                    <button class="btn btn-sm btn-ghost" style="color:#DC2626;border-color:#f3c9c9;">Cancel</button></form>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- History (full width, so nothing wraps) --}}
    <div class="card" data-transfer-history>
        <div class="card-header"><h2>Transfer history</h2><span class="card-meta">Last {{ min(30, $history->count()) }}</span></div>
        @if ($history->isEmpty())
            <div class="tr-empty" style="padding:22px;">No transfer history yet.</div>
        @else
            <table class="data-table">
                <thead><tr><th>Transfer</th><th>Direction</th><th>Other center</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                    @foreach ($history as $t)
                    @php $isFrom = $t->from_sorting_center_id === auth()->id(); @endphp
                    <tr>
                        <td><span class="id-link tr-ref">{{ $t->reference }}</span><div class="tr-sub">{{ $t->parcel->tracking_number }}</div></td>
                        <td>
                            <span class="tr-dir" style="{{ $isFrom ? 'background:#FFF4E5;color:#B45309;' : 'background:#EAF2FB;color:#1D4ED8;' }}">
                                {{ $isFrom ? '↗ Sent to' : '↙ Received from' }}
                            </span>
                        </td>
                        <td>{{ $isFrom ? \App\Support\SortingCenters::nameOf($t->toSortingCenter) : \App\Support\SortingCenters::nameOf($t->fromSortingCenter) }}</td>
                        <td><span class="badge {{ $t->status === 'received' ? 'badge-green' : 'badge-gray' }}">{{ $t->statusLabel() }}</span></td>
                        <td style="white-space:nowrap;color:var(--text-muted);font-size:12.5px;">{{ ($t->received_at ?? $t->cancelled_at ?? $t->updated_at)->format('M j, Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
