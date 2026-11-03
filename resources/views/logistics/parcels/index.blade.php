<x-logistics-layout title="Parcels & Sorting">

<form method="GET" class="filter-bar">
    <div class="field"><label>Search</label><input type="search" name="search" value="{{ request('search') }}" placeholder="Tracking #…"></div>
    <div class="field"><label>Status</label>
        <select name="status">
            <option value="">All</option>
            @foreach(['picked_up','sorted','assigned','in_transit'] as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
            @endforeach
        </select>
    </div>
    <div class="field" style="padding-top:18px"><button class="btn btn--primary btn--sm">Filter</button></div>
</form>

<div class="panel">
    <div class="panel__header"><h2>Incoming Parcels ({{ $parcels->total() }})</h2></div>
    @if($parcels->isEmpty())
        <div class="empty-state">No parcels found.</div>
    @else
    <table>
        <thead><tr><th>Tracking #</th><th>Receiver</th><th>Area</th><th>Status</th><th></th></tr></thead>
        <tbody>
            @foreach($parcels as $parcel)
            <tr>
                <td class="tracking-no">{{ $parcel->tracking_number }}</td>
                <td>{{ $parcel->receiver_name }}</td>
                <td>{{ $parcel->area->name ?? '—' }}</td>
                <td><span class="badge badge--neutral">{{ ucfirst(str_replace('_',' ',$parcel->status)) }}</span></td>
                <td style="display:flex;gap:6px;flex-wrap:wrap;">
                    <a href="{{ route('admin.logistics.parcels.show', $parcel) }}" class="btn btn--sm">View</a>
                    @if($parcel->status === 'pickup_approved')
                        <span class="text-xs" style="color:#6b90aa;" data-status-locked>Follows the seller → logistics → rider process</span>
                    @endif
                    @if($parcel->status === 'picked_up')
                        <span class="text-xs" style="color:#6b90aa;" data-status-locked>Follows the seller → logistics → rider process</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:14px 18px;">{{ $parcels->links() }}</div>
    @endif
</div>

</x-logistics-layout>
