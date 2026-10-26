<x-courier-layout title="Delivery History" active="history">
@php $statuses = \App\Http\Controllers\Courier\DeliveryController::HISTORY_STATUSES; $filtering = $q !== '' || $status || $date; @endphp
<style>
    .dh-stats { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
    @media (min-width: 900px) { .dh-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
    .dh-stats .cx-stat-value { font-size: 22px; }
    .dh-filters { display: grid; gap: 12px; padding: 16px 18px; margin-bottom: 16px; }
    @media (min-width: 900px) { .dh-filters { grid-template-columns: 1.4fr 1fr 1fr auto; align-items: end; } }
    .dh-filters label { display: block; margin-bottom: 6px; font-size: 11.5px; font-weight: 600; color: var(--text-muted); }
    .dh-filters .input { height: 42px; width: 100%; }
    .dh-filters .cx-btn { height: 42px; }
    .dh-row { display: grid; gap: 10px 18px; padding: 16px 18px; border-top: 1px solid var(--border); align-items: center; }
    .dh-row:first-child { border-top: 0; }
    @media (min-width: 900px) { .dh-row { grid-template-columns: minmax(0, 1.5fr) minmax(0, 1.2fr) auto auto; } }
    .dh-order { font-weight: 700; color: var(--text); }
    .dh-meta { margin-top: 2px; font-size: 12.5px; color: var(--text-muted); }
    .dh-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; }
    .dh-money { text-align: right; white-space: nowrap; }
    .dh-pay { font-weight: 800; font-size: 15px; }
</style>

<div class="cx-page-head">
    <h1 class="font-display">Delivery History</h1>
    <p>Every delivery job you were given, in progress and finished, with what you earned.</p>
</div>

{{-- Totals (all time) --}}
<div class="dh-stats">
    @foreach ([
        ['Delivered', $summary['delivered'], 'var(--green)', 'delivered'],
        ['In progress', $summary['in_progress'], 'var(--accent)', 'in_progress'],
        ['Failed / returned', $summary['failed'], 'var(--red)', 'failed'],
        ['Earned from deliveries', '₱' . number_format($summary['earned'], 2), 'var(--navy)', 'earned'],
    ] as [$label, $value, $color, $key])
        <div class="card cx-stat" data-summary="{{ $key }}">
            <p class="cx-stat-value" style="color:{{ $color }};">{{ $value }}</p>
            <p class="cx-stat-label">{{ $label }}</p>
        </div>
    @endforeach
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('courier.history') }}" class="card dh-filters">
    <div>
        <label for="q">Order # or tracking no.</label>
        <input type="text" id="q" name="q" value="{{ $q }}" placeholder="e.g. 000066 or ALVY-59BA" class="input" maxlength="40">
    </div>
    <div>
        <label for="status">Status</label>
        <select id="status" name="status" class="input">
            <option value="">All</option>
            @foreach ($statuses as $val => [$label])
                <option value="{{ $val }}" @selected($status === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="date">Date</label>
        <input type="date" id="date" name="date" value="{{ $date }}" class="input">
    </div>
    <div class="cx-actions" style="flex-wrap:nowrap;">
        <button type="submit" class="cx-btn cx-btn-navy" style="min-width:96px;">Filter</button>
        @if ($filtering)<a href="{{ route('courier.history') }}" class="cx-btn cx-btn-outline">Clear</a>@endif
    </div>
    @if ($errors->any())<p class="cx-alert cx-alert-error" style="grid-column:1/-1;margin:0;">{{ $errors->first() }}</p>@endif
</form>

@if ($deliveries->count())
    <div class="card" style="overflow:hidden;">
        @foreach ($deliveries as $d)
            @php [, $pill] = $statuses[$d->group]; @endphp
            <div class="dh-row" data-history-row="{{ $d->tracking }}" data-group="{{ $d->group }}">
                <div>
                    <p class="dh-order">Order {{ $d->order }}</p>
                    <p class="dh-meta">@if ($d->tracking)<span class="dh-mono">{{ $d->tracking }}</span> · @endif{{ $d->at?->format('M j, Y g:i A') }}</p>
                </div>
                <div>
                    <p style="font-size:13.5px;color:var(--text);">{{ $d->buyer ?? '—' }}</p>
                    <p class="dh-meta">{{ $d->place ?: '—' }}@if ($d->seller) · from {{ $d->seller }}@endif</p>
                </div>
                <div>
                    <span class="cx-pill {{ $pill }}">{{ $d->label }}</span>
                    @if ($d->attempt > 1)<span class="dh-meta" style="margin-left:6px;">attempt {{ $d->attempt }}</span>@endif
                    @if ($d->note)<p class="dh-meta" style="max-width:220px;">{{ $d->note }}</p>@endif
                </div>
                <div class="dh-money">
                    <p class="dh-pay" style="color:{{ $d->pay > 0 ? 'var(--green)' : 'var(--text-muted)' }};">{{ $d->pay > 0 ? '+₱' . number_format($d->pay, 2) : '₱0.00' }}</p>
                    @if ($d->cod > 0)<p class="dh-meta">COD ₱{{ number_format($d->cod, 2) }}</p>@endif
                    <a href="{{ $d->url }}" class="cx-btn cx-btn-outline cx-btn-sm" style="margin-top:6px;">View</a>
                </div>
            </div>
        @endforeach
    </div>
    <div style="margin-top:16px;">{{ $deliveries->links() }}</div>
@else
    <div class="card cx-empty">
        <span class="cx-empty-icon">@include('courier.partials.icon', ['name' => 'history', 'size' => 26])</span>
        @if ($filtering)
            <p class="cx-empty-title">No deliveries match these filters</p>
            <p class="cx-empty-sub"><a href="{{ route('courier.history') }}" style="color:var(--accent);font-weight:600;">Clear the filters</a> to see all your deliveries.</p>
        @else
            <p class="cx-empty-title">No delivery history yet</p>
            <p class="cx-empty-sub">Deliveries your sorting center gives you will appear here, with what you earned.</p>
        @endif
    </div>
@endif

</x-courier-layout>
