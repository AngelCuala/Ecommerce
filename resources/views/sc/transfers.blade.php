@extends('sc.layout')
@section('title', 'SC Transfers')
@section('icon', 'transfer')

@php $field = 'width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:6px;font-size:13px;background:#fff;'; @endphp

@section('content')
<div class="page-header"><h1>Sorting Center Transfers</h1></div>
<div class="page-body">

    <div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
        <div class="stat-card">
            <div class="stat-icon">@include('sc.partials.icon', ['name' => 'upload', 'size' => 20])</div>
            <div class="stat-value">{{ $outgoing->where('status', 'pending')->count() }}</div>
            <div class="stat-label">Awaiting dispatch</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">@include('sc.partials.icon', ['name' => 'transfer', 'size' => 20])</div>
            <div class="stat-value">{{ $outgoing->where('status', 'in_transit')->count() }}</div>
            <div class="stat-label">Sent, in transit</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">@include('sc.partials.icon', ['name' => 'inbox', 'size' => 20])</div>
            <div class="stat-value">{{ $incoming->where('status', 'in_transit')->count() }}</div>
            <div class="stat-label">Coming to you</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">@include('sc.partials.icon', ['name' => 'list', 'size' => 20])</div>
            <div class="stat-value" style="{{ $outgoing->merge($incoming)->filter->isDelayed()->count() ? 'color:#DC2626;' : '' }}">{{ $outgoing->merge($incoming)->filter->isDelayed()->count() }}</div>
            <div class="stat-label">Delayed</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
        <div>
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header"><h2>Create a Transfer</h2></div>
                <div style="padding:18px;">
                    @if ($myParcels->isEmpty())
                        <p style="color:var(--text-muted);font-size:13px;text-align:center;padding:20px 0;">
                            No parcels available for transfer.<br><small>Only parcels received at your hub (with no active transfer or rider) can be transferred.</small>
                        </p>
                    @else
                        <form action="{{ route('sc.transfers.initiate') }}" method="POST" style="display:grid;gap:12px;" onsubmit="return confirm('Create this transfer? The parcel stays here until you confirm dispatch.')">
                            @csrf
                            <div>
                                <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:4px;">Parcel</label>
                                <select name="parcel_id" required style="{{ $field }}">
                                    <option value="">— Choose a parcel —</option>
                                    @foreach ($myParcels as $p)
                                        <option value="{{ $p->id }}" @selected(old('parcel_id') == $p->id)>{{ $p->tracking_number }} — {{ $p->destination_municipality }} · {{ $suggested[$p->id]['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:4px;">Destination sorting center ({{ \App\Support\ServiceArea::label() }})</label>
                                <select name="to_sorting_center_id" required style="{{ $field }}">
                                    <option value="">— Select destination —</option>
                                    @foreach ($otherCenters as $sc)
                                        <option value="{{ $sc->id }}" @selected(old('to_sorting_center_id') == $sc->id)>{{ \App\Support\SortingCenters::displayName($sc) }} — {{ $sc->assigned_municipality }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:4px;">Transporter (optional)</label>
                                <select name="transporter_rider_id" style="{{ $field }}">
                                    <option value="">— None —</option>
                                    @foreach ($transporters as $r)<option value="{{ $r->id }}">{{ $r->full_name }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label style="font-size:12px;font-weight:600;color:var(--text-muted);display:block;margin-bottom:4px;">Reason (needed when it is not the center serving the address)</label>
                                <textarea name="reason" rows="2" style="{{ $field }}resize:vertical;">{{ old('reason') }}</textarea>
                            </div>
                            @if ($errors->any())<p style="color:#B91C1C;font-size:12.5px;">{{ $errors->first() }}</p>@endif
                            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;gap:6px;">
                                @include('sc.partials.icon', ['name' => 'upload', 'size' => 15, 'sw' => 2]) Create transfer
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2>Outgoing Transfers</h2></div>
                @if ($outgoing->isEmpty())
                    <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px;">No outgoing transfers.</div>
                @else
                <table class="data-table">
                    <thead><tr><th>Reference</th><th>To</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($outgoing as $t)
                        <tr data-transfer="{{ $t->reference }}">
                            <td><a href="{{ route('sc.tracking.show', $t->parcel) }}" class="id-link">{{ $t->reference }}</a><div class="text-muted" style="font-size:11px;">{{ $t->parcel->tracking_number }}</div></td>
                            <td style="font-size:12.5px;">{{ \App\Support\SortingCenters::nameOf($t->toSortingCenter) }}</td>
                            <td><span class="badge {{ $t->isDelayed() || $t->status === 'exception' ? 'badge-red' : ($t->isPending() ? 'badge-yellow' : 'badge-blue') }}">{{ $t->statusLabel() }}</span>
                                @if ($t->dispatched_at)<div class="text-muted" style="font-size:11px;">Sent {{ $t->dispatched_at->format('M j, g:i A') }}</div>@endif</td>
                            <td style="white-space:nowrap;">
                                @if ($t->isPending())
                                    <form action="{{ route('sc.transfers.dispatch', $t->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Confirm {{ $t->parcel->tracking_number }} is leaving now?')">@csrf
                                        <button class="btn btn-sm btn-blue">Confirm dispatch</button></form>
                                    <form action="{{ route('sc.transfers.cancel', $t->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Cancel this transfer? The parcel stays here.')">@csrf
                                        <button class="btn btn-sm btn-ghost" style="color:#DC2626;border-color:#DC2626;">Cancel</button></form>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </div>

        <div>
            <div class="card" style="margin-bottom:20px;">
                <div class="card-header"><h2>Incoming Transfers</h2></div>
                @if ($incoming->isEmpty())
                    <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px;">No incoming transfers right now.</div>
                @else
                    <table class="data-table">
                        <thead><tr><th>Reference</th><th>From</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($incoming as $t)
                            <tr data-transfer="{{ $t->reference }}">
                                <td><span class="id-link">{{ $t->reference }}</span><div class="text-muted" style="font-size:11px;">{{ $t->parcel->tracking_number }}</div></td>
                                <td style="font-size:12.5px;">{{ \App\Support\SortingCenters::nameOf($t->fromSortingCenter) }}</td>
                                <td><span class="badge {{ $t->isDelayed() || $t->status === 'exception' ? 'badge-red' : ($t->isPending() ? 'badge-yellow' : 'badge-blue') }}">{{ $t->isPending() ? 'Not dispatched yet' : $t->statusLabel() }}</span>
                                    @if ($t->expected_by && $t->isInTransit())<div class="text-muted" style="font-size:11px;">Due {{ $t->expected_by->format('M j, g:i A') }}</div>@endif</td>
                                <td style="white-space:nowrap;">
                                    @if ($t->isInTransit())
                                        <a href="{{ route('sc.tracking.show', $t->parcel) }}" class="btn btn-sm btn-primary">Check &amp; receive</a>
                                    @elseif ($t->isPending())
                                        <form action="{{ route('sc.transfers.reject', $t->id) }}" method="POST" onsubmit="return confirm('Decline this transfer? The parcel stays with the sender.')">@csrf
                                            <button class="btn btn-sm btn-ghost" style="color:#DC2626;border-color:#DC2626;">Decline</button></form>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="card">
                <div class="card-header"><h2>Transfer History (last 30)</h2></div>
                @if ($history->isEmpty())
                    <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px;">No transfer history yet.</div>
                @else
                    <table class="data-table">
                        <thead><tr><th>Reference</th><th>Direction</th><th>Other SC</th><th>Status</th><th>Date</th></tr></thead>
                        <tbody>
                            @foreach ($history as $t)
                            @php $isFrom = $t->from_sorting_center_id === auth()->id(); @endphp
                            <tr>
                                <td><span class="id-link">{{ $t->reference }}</span><div class="text-muted" style="font-size:11px;">{{ $t->parcel->tracking_number }}</div></td>
                                <td style="font-size:12px;color:var(--text-muted);">{{ $isFrom ? 'Sent to' : 'From' }}</td>
                                <td style="font-size:13px;">{{ $isFrom ? \App\Support\SortingCenters::nameOf($t->toSortingCenter) : \App\Support\SortingCenters::nameOf($t->fromSortingCenter) }}</td>
                                <td><span class="badge {{ $t->status === 'received' ? 'badge-green' : 'badge-gray' }}">{{ $t->statusLabel() }}</span></td>
                                <td style="color:var(--text-muted);font-size:12px;">{{ ($t->received_at ?? $t->cancelled_at ?? $t->updated_at)->format('M d, Y') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
