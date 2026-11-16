<x-logistics-layout title="Delivery Assignment">

<form method="GET" class="filter-bar">
    <div class="field"><label>Filter by Area</label>
        <select name="area_id">
            <option value="">All Areas</option>
            @foreach($areas as $area)
                <option value="{{ $area->id }}" @selected(request('area_id')==$area->id)>{{ $area->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="field" style="padding-top:18px"><button class="btn btn--primary btn--sm">Filter</button></div>
</form>

<div class="panel">
    <div class="panel__header"><h2>Sorted Parcels Ready for Assignment ({{ $parcels->total() }})</h2></div>
    @if($parcels->isEmpty())
        <div class="empty-state">No sorted parcels waiting for assignment.</div>
    @else
    <table>
        <thead><tr><th>Tracking #</th><th>Receiver</th><th>Area</th><th>Assign to Rider</th></tr></thead>
        <tbody>
            @foreach($parcels as $parcel)
            <tr>
                <td class="tracking-no">{{ $parcel->tracking_number }}</td>
                <td>{{ $parcel->receiver_name }}<br><span class="text-muted" style="font-size:12px;">{{ $parcel->dropoff_address }}</span></td>
                <td>{{ $parcel->area->name ?? '—' }}</td>
                <td>
                    <span class="text-xs" style="color:#6b90aa;" data-status-locked>Follows the seller → logistics → rider process</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:14px 18px;">{{ $parcels->links() }}</div>
    @endif
</div>

</x-logistics-layout>
