<x-logistics-layout title="Rider Management">

<form method="GET" class="filter-bar">
    <div class="field"><label>Search</label><input type="search" name="search" value="{{ request('search') }}" placeholder="Rider name…"></div>
    <div class="field"><label>Status</label>
        <select name="status">
            <option value="">All</option>
            @foreach(['pending','approved','rejected'] as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
            @endforeach
        </select>
    </div>
    <div class="field" style="padding-top:18px"><button class="btn btn--primary btn--sm">Filter</button></div>
</form>

<div class="panel">
    <div class="panel__header"><h2>Riders ({{ $riders->total() }})</h2></div>
    @if($riders->isEmpty())
        <div class="empty-state">No riders found.</div>
    @else
    <table>
        <thead><tr><th>Name</th><th>Phone</th><th>Area</th><th>Vehicle</th><th>Status</th><th>Active</th><th></th></tr></thead>
        <tbody>
            @foreach($riders as $rider)
            <tr>
                <td>{{ $rider->full_name }}</td>
                <td>{{ $rider->phone ?? '—' }}</td>
                <td>{{ $rider->area->name ?? '—' }}</td>
                <td>{{ $rider->vehicle_type ?? '—' }}</td>
                <td><span class="badge badge--{{ $rider->application_status === 'approved' ? 'approved' : ($rider->application_status === 'rejected' ? 'rejected' : 'pending') }}">{{ ucfirst($rider->application_status) }}</span></td>
                <td><span class="badge badge--{{ $rider->is_active ? 'active' : 'inactive' }}">{{ $rider->is_active ? 'Active' : 'Inactive' }}</span></td>
                <td><a href="{{ route('logistics.riders.show', $rider) }}" class="btn btn--sm">View</a></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:14px 18px;">{{ $riders->links() }}</div>
    @endif
</div>

</x-logistics-layout>
