<x-logistics-layout title="Delivery Monitoring">

<form method="GET" class="filter-bar">
    <div class="field"><label>Status</label>
        <select name="status">
            <option value="">All</option>
            @foreach(['assigned','out_for_delivery','delivered','failed','returned'] as $s)
                <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
            @endforeach
        </select>
    </div>
    <div class="field"><label>Rider</label>
        <select name="rider_id">
            <option value="">All Riders</option>
            @foreach($riders as $r)
                <option value="{{ $r->id }}" @selected(request('rider_id')==$r->id)>{{ $r->full_name }}</option>
            @endforeach
        </select>
    </div>
    <div class="field" style="padding-top:18px"><button class="btn btn--primary btn--sm">Filter</button></div>
</form>

<div class="panel">
    <div class="panel__header"><h2>All Deliveries ({{ $deliveries->total() }})</h2></div>
    @if($deliveries->isEmpty())
        <div class="empty-state">No deliveries found.</div>
    @else
    <table>
        <thead><tr><th>Tracking #</th><th>Rider</th><th>Area</th><th>Status</th><th>Delivered At</th><th></th></tr></thead>
        <tbody>
            @foreach($deliveries as $d)
            <tr>
                <td class="tracking-no">{{ $d->parcel->tracking_number ?? '—' }}</td>
                <td>{{ $d->rider->full_name ?? '—' }}</td>
                <td>{{ $d->area->name ?? '—' }}</td>
                <td><span class="badge badge--{{ in_array($d->status,['delivered']) ? 'approved' : (in_array($d->status,['failed','returned']) ? 'rejected' : 'pending') }}">{{ ucfirst(str_replace('_',' ',$d->status)) }}</span></td>
                <td class="text-muted">{{ $d->delivered_at?->format('M d, Y H:i') ?? '—' }}</td>
                <td>
                    @php $next = \App\Services\ParcelService::DELIVERY_FLOW[$d->status] ?? []; @endphp
                    @if($next)
                    <form method="POST" action="{{ route('admin.logistics.deliveries.update-status', $d) }}" style="display:flex;flex-direction:column;gap:4px;">
                        @csrf
                        <div style="display:flex;gap:6px;">
                            <select name="status" style="width:auto;" aria-label="New status">
                                @foreach($next as $s)
                                    <option value="{{ $s }}">{{ \App\Models\ParcelDelivery::STATUS_LABELS[$s] ?? $s }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn--sm btn--primary">Update</button>
                        </div>
                        <input type="text" name="remarks" placeholder="Override reason (10–300, logged)" minlength="10" maxlength="300" required aria-label="Override reason">
                    </form>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:14px 18px;">{{ $deliveries->links() }}</div>
    @endif
</div>

</x-logistics-layout>
