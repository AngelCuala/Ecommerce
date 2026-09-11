<x-logistics-layout title="Reports">

<form method="GET" class="filter-bar">
    <div class="field"><label>From</label><input type="date" name="from" value="{{ request('from', $from->format('Y-m-d')) }}"></div>
    <div class="field"><label>To</label><input type="date" name="to" value="{{ request('to', $to->format('Y-m-d')) }}"></div>
    <div class="field" style="padding-top:18px"><button class="btn btn--primary btn--sm">Apply</button></div>
    <div class="field" style="padding-top:18px">
        <a href="{{ route('logistics.reports.export', request()->only('from','to')) }}" class="btn btn--sm">Export CSV</a>
    </div>
</form>

<div class="stat-grid">
    <div class="stat"><div class="stat__value">{{ $summary['total_parcels'] }}</div><div class="stat__label">Total Parcels</div></div>
    <div class="stat"><div class="stat__value">{{ $summary['delivered'] }}</div><div class="stat__label">Delivered</div></div>
    <div class="stat"><div class="stat__value">{{ $summary['failed'] }}</div><div class="stat__label">Failed</div></div>
    <div class="stat"><div class="stat__value">{{ $summary['new_rider_signups'] }}</div><div class="stat__label">New Rider Signups</div></div>
</div>

<div class="panel">
    <div class="panel__header"><h2>Deliveries in Period</h2></div>
    @if($deliveries->isEmpty())
        <div class="empty-state">No deliveries in this period.</div>
    @else
    <table>
        <thead><tr><th>Tracking #</th><th>Rider</th><th>Area</th><th>Status</th><th>Assigned</th><th>Delivered</th></tr></thead>
        <tbody>
            @foreach($deliveries as $d)
            <tr>
                <td class="tracking-no">{{ $d->parcel->tracking_number ?? '—' }}</td>
                <td>{{ $d->rider->full_name ?? '—' }}</td>
                <td>{{ $d->area->name ?? '—' }}</td>
                <td><span class="badge badge--{{ $d->status==='delivered' ? 'approved' : ($d->status==='failed' ? 'rejected' : 'pending') }}">{{ ucfirst(str_replace('_',' ',$d->status)) }}</span></td>
                <td class="text-muted">{{ $d->created_at->format('M d, Y') }}</td>
                <td class="text-muted">{{ $d->delivered_at?->format('M d, Y') ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:14px 18px;">{{ $deliveries->links() }}</div>
    @endif
</div>

</x-logistics-layout>
