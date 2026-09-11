<x-logistics-layout title="Dashboard">

<div class="stat-grid">
    <div class="stat"><div class="stat__value">{{ $stats['pending_rider_applications'] }}</div><div class="stat__label">Pending Rider Applications</div></div>
    <div class="stat"><div class="stat__value">{{ $stats['active_riders'] }}</div><div class="stat__label">Active Riders</div></div>
    <div class="stat"><div class="stat__value">{{ $stats['pending_pickup_requests'] }}</div><div class="stat__label">Pending Pickup Requests</div></div>
    <div class="stat"><div class="stat__value">{{ $stats['incoming_parcels'] }}</div><div class="stat__label">Parcels at Hub</div></div>
    <div class="stat"><div class="stat__value">{{ $stats['in_transit'] }}</div><div class="stat__label">In Transit</div></div>
    <div class="stat"><div class="stat__value">{{ $stats['delivered_today'] }}</div><div class="stat__label">Delivered Today</div></div>
    <div class="stat"><div class="stat__value">{{ $stats['failed_deliveries'] }}</div><div class="stat__label">Failed Deliveries</div></div>
</div>

<div class="panel">
    <div class="panel__header"><h2>Recent Delivery Activity</h2></div>
    @if($recentActivity->isEmpty())
        <div class="empty-state">No delivery activity yet.</div>
    @else
        <table>
            <thead><tr><th>Tracking #</th><th>Rider</th><th>Status</th><th>Updated</th></tr></thead>
            <tbody>
                @foreach($recentActivity as $d)
                <tr>
                    <td class="tracking-no">{{ $d->parcel->tracking_number ?? '—' }}</td>
                    <td>{{ $d->rider->full_name ?? '—' }}</td>
                    <td><span class="badge badge--{{ in_array($d->status,['delivered']) ? 'approved' : (in_array($d->status,['failed','returned']) ? 'rejected' : 'pending') }}">{{ ucfirst(str_replace('_',' ',$d->status)) }}</span></td>
                    <td class="text-muted">{{ $d->updated_at->diffForHumans() }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

</x-logistics-layout>
