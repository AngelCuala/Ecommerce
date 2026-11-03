@extends('sc.layout')
@section('title', 'Shipments')
@section('icon', 'box')

@section('content')
@php
    $labels = ['' => 'All', 'pending' => 'Pending pickup', 'in_transit' => 'In transit', 'delivered' => 'Delivered', 'failed' => 'Failed / exceptions', 'returned' => 'Returned / cancelled'];
    $tone = ['pending' => 'yellow', 'in_transit' => 'blue', 'delivered' => 'green', 'failed' => 'red', 'returned' => 'gray'];
    $bucketOf = function (string $s) {
        foreach (\App\Models\Parcel::BUCKETS as $bucket => $statuses) {
            if (in_array($s, $statuses, true)) return $bucket;
        }
        return 'returned';
    };
    $all = array_sum(array_intersect_key($counts, \App\Models\Parcel::BUCKETS));
@endphp
<style>
    .sh-tabs { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:10px; margin-bottom:14px; }
    .sh-tab { display:block; padding:12px 14px; border:1px solid var(--border); border-radius:12px; background:var(--card,#fff); text-decoration:none; color:var(--text); transition:border-color .12s, box-shadow .12s; }
    .sh-tab:hover { border-color:#CBD5E1; text-decoration:none; }
    .sh-tab.is-on { border-color:var(--accent-orange,#fa4e1c); box-shadow:0 0 0 3px rgba(250,78,28,.12); }
    .sh-tab .n { font-size:22px; font-weight:800; line-height:1.1; }
    .sh-tab .l { font-size:12px; color:var(--text-muted); margin-top:2px; }
    .sh-filter { display:flex; flex-wrap:wrap; gap:10px; align-items:end; padding:12px 14px; margin-bottom:14px; }
    .sh-table-wrap { overflow-x:auto; }
    .sh-table td { vertical-align:top; }
    .sh-sub { display:block; font-size:12px; color:var(--text-muted); margin-top:2px; }
    .sh-issue summary { list-style:none; cursor:pointer; }
    .sh-issue summary::-webkit-details-marker { display:none; }
    .sh-issue[open] summary { margin-bottom:8px; }
    .sh-issue form { display:grid; gap:6px; width:230px; }
</style>

<div class="page-header"><h1>Shipments</h1></div>
<div class="page-body">

    {{-- Status groups with their counts --}}
    <nav class="sh-tabs" aria-label="Shipment status">
        @foreach($labels as $key => $label)
            <a href="{{ route('sc.shipments', array_filter(['status' => $key, 'from' => request('from'), 'to' => request('to'), 'q' => $q])) }}"
               class="sh-tab {{ (string) $status === (string) $key ? 'is-on' : '' }}" @if((string) $status === (string) $key) aria-current="page" @endif>
                <div class="n" @if($key) style="color:{{ ['pending' => '#B45309', 'in_transit' => '#2563EB', 'delivered' => '#059669', 'failed' => '#DC2626', 'returned' => '#57534E'][$key] }};" @endif>{{ $key ? $counts[$key] : $all }}</div>
                <div class="l">{{ $label }}</div>
            </a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('sc.shipments') }}" class="card sh-filter">
        <input type="hidden" name="status" value="{{ $status }}">
        <div style="flex:1;min-width:220px;"><label class="form-label" for="ship-q">Tracking number, order ID or buyer</label>
            <input id="ship-q" name="q" value="{{ $q }}" class="form-input" placeholder="e.g. ALV-…, 123 or Juan Dela Cruz"></div>
        <div><label class="form-label" for="ship-from">From</label><input type="date" id="ship-from" name="from" value="{{ request('from') }}" class="form-input"></div>
        <div><label class="form-label" for="ship-to">To</label><input type="date" id="ship-to" name="to" value="{{ request('to') }}" class="form-input"></div>
        <button class="btn btn-blue btn-sm">Search</button>
        @if($q || request('from') || request('to'))
            <a href="{{ route('sc.shipments', array_filter(['status' => $status])) }}" class="btn btn-ghost btn-sm">Clear</a>
        @endif
    </form>

    <div class="card">
        <div class="sh-table-wrap">
        <table class="data-table sh-table">
            <thead><tr><th>Tracking / order</th><th>Buyer</th><th>Seller</th><th>Status</th><th>Created</th><th style="text-align:right;">Issue</th></tr></thead>
            <tbody>
            @forelse($parcels as $p)
                <tr data-shipment="{{ $p->tracking_number }}">
                    <td><span class="id-link">{{ $p->tracking_number }}</span><span class="sh-sub">Order {{ $p->order?->number() ?? '—' }}</span></td>
                    <td>{{ $p->receiver_name }}
                        <span class="sh-sub">{{ collect([$p->destination_municipality, $p->destination_province])->filter()->implode(', ') ?: ($p->receiver_phone ?: '') }}</span></td>
                    <td>{{ $p->seller?->storeName() ?? '—' }}</td>
                    <td><span class="badge badge-{{ $tone[$bucketOf($p->status)] }}">{{ $p->statusLabel() }}</span>
                        @if($p->failure_reason)<span class="sh-sub" title="{{ $p->failure_reason }}">{{ \Illuminate\Support\Str::limit($p->failure_reason, 60) }}</span>@endif</td>
                    <td style="white-space:nowrap;">{{ $p->created_at->format('M j, Y') }}<span class="sh-sub">{{ $p->created_at->format('g:i A') }}</span></td>
                    <td style="text-align:right;">
                        @unless(in_array($p->status, \App\Models\Parcel::CLOSED_STATUSES, true))
                            {{-- Lost / damaged: tucked away until needed, so the list stays readable. --}}
                            <details class="sh-issue" style="display:inline-block;text-align:left;">
                                <summary class="btn btn-ghost btn-sm">Report issue</summary>
                                <form method="POST" action="{{ route('sc.parcels.exception', $p) }}" enctype="multipart/form-data">@csrf
                                    <select name="type" class="form-select" aria-label="Exception for {{ $p->tracking_number }}"><option value="lost">Lost</option><option value="damaged">Damaged</option></select>
                                    <input name="reason" class="form-input" placeholder="What happened (10–300 characters)" minlength="10" maxlength="300" required aria-label="Reason">
                                    <label class="text-muted text-sm">Photo (needed for Damaged)
                                        <input type="file" name="photo" accept="image/jpeg,image/png" style="font-size:11px;margin-top:2px;"></label>
                                    <button class="btn btn-red btn-sm">Mark {{ $p->tracking_number }}</button>
                                </form>
                            </details>
                        @else
                            <span class="text-muted text-sm">—</span>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-muted);">No shipments found.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <div style="padding:10px 14px;">{{ $parcels->links() }}</div>
    </div>
</div>
@endsection
