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
                    <a href="{{ route('logistics.parcels.show', $parcel) }}" class="btn btn--sm">View</a>
                    @if($parcel->status === 'pickup_approved')
                        <form method="POST" action="{{ route('logistics.parcels.mark-picked-up', $parcel) }}">@csrf
                            <button class="btn btn--primary btn--sm">Mark Picked Up</button>
                        </form>
                    @endif
                    @if($parcel->status === 'picked_up')
                        <form method="POST" action="{{ route('logistics.parcels.sort', $parcel) }}" style="display:flex;gap:6px;">
                            @csrf
                            <select name="area_id" required style="width:auto;">
                                <option value="">— Area —</option>
                                @foreach($areas as $area)
                                    <option value="{{ $area->id }}">{{ $area->name }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn--primary btn--sm">Sort</button>
                        </form>
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
