@extends('admin.layouts.app')

@section('title', 'Shipments')
@section('topbar-icon')
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="opacity:.6"><path d="M3 7h18M3 12h18M3 17h12"/></svg>
@endsection

@section('content')
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:18px;">
    @foreach ([['transfers', 'Active transfers'], ['delayed', 'Delayed transfers'], ['exceptions', 'Open exceptions'], ['failed', 'Failed attempts (7 days)']] as [$key, $label])
        <a href="{{ route('admin.logistics.shipments.index', ['view' => $key]) }}" class="stat" style="padding:12px 16px;display:block;{{ $view === $key ? 'outline:2px solid var(--blue);' : '' }}" data-stat="{{ $key }}">
            <div style="font-size:22px;font-weight:700;{{ in_array($key, ['delayed', 'exceptions'], true) && $stats[$key] ? 'color:var(--red);' : '' }}">{{ $stats[$key] }}</div>
            <div class="text-muted" style="font-size:12px;">{{ $label }}</div>
        </a>
    @endforeach
</div>

<form method="GET" class="filter-bar" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px;">
    <input type="hidden" name="view" value="{{ $view }}">
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Tracking, waybill, municipality" style="width:240px;">
    <select name="status"><option value="">Any status</option>@foreach (\App\Models\Parcel::STATUS_LABELS as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach</select>
    <select name="center"><option value="">Any sorting center</option>@foreach ($centers as $c)<option value="{{ $c->id }}" @selected((string) request('center') === (string) $c->id)>{{ $c->name }}</option>@endforeach</select>
    <button class="btn btn--blue btn--sm">Filter</button>
    <a href="{{ route('admin.logistics.shipments.index') }}" class="btn btn--ghost btn--sm">Reset</a>
</form>

<div class="panel">
    <table>
        <thead><tr><th>Tracking</th><th>Order</th><th>Status</th><th>Location</th><th>Transfer</th><th>Last event</th><th></th></tr></thead>
        <tbody>
        @forelse ($parcels as $p)
            <tr data-parcel="{{ $p->id }}">
                <td class="tracking-no">{{ $p->tracking_number }}</td>
                <td>{{ $p->order?->number() }}</td>
                <td><span class="badge badge--neutral">{{ $p->statusLabel() }}</span>@if ($p->open_exceptions)<span class="badge badge--rejected" style="margin-left:4px;">{{ $p->open_exceptions }} open</span>@endif</td>
                <td>{{ $p->status === 'in_transfer' ? 'Between hubs' : (\App\Support\SortingCenters::nameOf($p->currentSortingCenter) ?? '—') }}</td>
                <td>{{ $p->activeTransfer ? $p->activeTransfer->reference . ' → ' . \App\Support\SortingCenters::nameOf($p->activeTransfer->toSortingCenter) . ' (' . $p->activeTransfer->statusLabel() . ')' : '—' }}</td>
                <td>{{ $p->events->first()?->label() ?? '—' }}<div class="text-muted" style="font-size:11px;">{{ $p->events->first()?->occurred_at?->diffForHumans() }}</div></td>
                <td><a href="{{ route('admin.logistics.shipments.show', $p) }}" class="btn btn--sm">Open</a></td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty-state">No shipments match.</div></td></tr>
        @endforelse
        </tbody>
    </table>
    <div style="padding:12px 16px;">{{ $parcels->links() }}</div>
</div>
@endsection
