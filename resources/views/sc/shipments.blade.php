@extends('sc.layout')
@section('title', 'Shipments')
@section('icon', 'box')

@section('content')
<div class="page-header"><h1>Shipments</h1></div>
<div class="page-body">
    @php $labels = ['' => 'All', 'pending' => 'Pending pickup', 'in_transit' => 'In transit', 'delivered' => 'Delivered', 'failed' => 'Failed / exceptions', 'returned' => 'Returned / cancelled']; @endphp

    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;">
        @foreach($labels as $key => $label)
            <a href="{{ route('sc.shipments', array_filter(['status' => $key, 'from' => request('from'), 'to' => request('to'), 'q' => $q])) }}"
               class="badge {{ (string) $status === (string) $key ? 'badge-blue' : 'badge-gray' }}" style="padding:6px 10px;">
                {{ $label }}@if($key) · {{ $counts[$key] }}@endif
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('sc.shipments') }}" class="card" style="padding:12px 14px;margin-bottom:14px;display:flex;flex-wrap:wrap;gap:8px;align-items:end;">
        <input type="hidden" name="status" value="{{ $status }}">
        <div style="flex:1;min-width:220px;"><label class="form-label" for="ship-q">Tracking number, order ID or buyer</label>
            <input id="ship-q" name="q" value="{{ $q }}" class="form-input" placeholder="e.g. ALV-…, 123 or Juan Dela Cruz"></div>
        <div><label class="form-label" for="ship-from">From</label><input type="date" id="ship-from" name="from" value="{{ request('from') }}" class="form-input"></div>
        <div><label class="form-label" for="ship-to">To</label><input type="date" id="ship-to" name="to" value="{{ request('to') }}" class="form-input"></div>
        <button class="btn btn-blue btn-sm">Search</button>
    </form>

    <div class="card">
        <table class="data-table">
            <thead><tr><th>Tracking</th><th>Order</th><th>Buyer</th><th>Seller</th><th>Status</th><th>Created</th><th>Exception</th></tr></thead>
            <tbody>
            @forelse($parcels as $p)
                <tr data-shipment="{{ $p->tracking_number }}">
                    <td><span class="id-link">{{ $p->tracking_number }}</span></td>
                    <td>{{ $p->order?->number() ?? '—' }}</td>
                    <td>{{ $p->receiver_name }}</td>
                    <td>{{ $p->seller->name ?? '—' }}</td>
                    <td><span class="badge badge-gray">{{ $p->statusLabel() }}</span>@if($p->failure_reason)<div class="text-muted text-sm">{{ \Illuminate\Support\Str::limit($p->failure_reason, 60) }}</div>@endif</td>
                    <td>{{ $p->created_at->format('M j, Y') }}</td>
                    <td>
                        @unless(in_array($p->status, \App\Models\Parcel::CLOSED_STATUSES, true))
                            <form method="POST" action="{{ route('sc.parcels.exception', $p) }}" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:4px;">@csrf
                                <select name="type" class="form-select" style="padding:4px;font-size:11px;" aria-label="Exception for {{ $p->tracking_number }}"><option value="lost">Lost</option><option value="damaged">Damaged</option></select>
                                <input name="reason" class="form-input" style="padding:4px 6px;font-size:11px;width:150px;" placeholder="Reason (10–300)" minlength="10" maxlength="300" required aria-label="Reason">
                                <input type="file" name="photo" accept="image/jpeg,image/png" style="font-size:10px;width:140px;" aria-label="Photo (needed for Damaged)">
                                <button class="btn btn-red btn-sm">Mark</button>
                            </form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:24px;color:var(--text-muted);">No shipments found.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div style="padding:10px 14px;">{{ $parcels->links() }}</div>
    </div>
</div>
@endsection
