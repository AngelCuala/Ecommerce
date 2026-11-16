<x-logistics-layout title="Pickup Requests">

<form method="GET" class="filter-bar">
    <div class="field"><label>Search tracking #</label><input type="search" name="search" value="{{ request('search') }}" placeholder="PCL-…"></div>
    <div class="field" style="padding-top:18px"><button class="btn btn--primary btn--sm">Search</button></div>
</form>

<div class="panel">
    <div class="panel__header"><h2>Pending Pickup Requests ({{ $requests->total() }})</h2></div>
    @if($requests->isEmpty())
        <div class="empty-state">No pending pickup requests.</div>
    @else
    <table>
        <thead><tr><th>Tracking #</th><th>Seller</th><th>Pickup Address</th><th>Dropoff Address</th><th>Submitted</th><th></th></tr></thead>
        <tbody>
            @foreach($requests as $parcel)
            <tr>
                <td class="tracking-no">{{ $parcel->tracking_number }}</td>
                <td>{{ $parcel->seller->name ?? '—' }}</td>
                <td>{{ $parcel->pickup_address }}</td>
                <td>{{ $parcel->dropoff_address }}</td>
                <td class="text-muted">{{ $parcel->created_at->format('M d, Y') }}</td>
                <td style="display:flex;gap:6px;">
                    <span class="text-xs" style="color:#6b90aa;" data-status-locked>Follows the seller → logistics → rider process</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:14px 18px;">{{ $requests->links() }}</div>
    @endif
</div>

</x-logistics-layout>
