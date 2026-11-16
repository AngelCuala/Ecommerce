@extends('sc.layout')
@section('title', 'COD Remittances')
@section('icon', 'rider')

@push('styles')
<style>
    /* Three separate views, one at a time, so the tables don't pile up. */
    .rm-tabs { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; margin-bottom: 16px; }
    .rm-tab { display: flex; align-items: center; gap: 12px; padding: 12px 14px; border-radius: 14px; border: 1px solid var(--border);
              background: var(--card); box-shadow: var(--shadow-card); color: var(--text); text-decoration: none; transition: border-color .15s, background .15s; }
    .rm-tab:hover { border-color: #fdc3b0; }
    .rm-tab.is-active { border-color: var(--accent-blue); background: #fff6f2; box-shadow: 0 0 0 3px rgba(250,78,28,.12); }
    .rm-tab-icon { display: flex; flex: none; height: 38px; width: 38px; align-items: center; justify-content: center; border-radius: 11px; background: #eef4f8; color: var(--text-sub); }
    .rm-tab.is-active .rm-tab-icon { background: #ffe4da; color: var(--accent-blue-d); }
    .rm-tab-text { min-width: 0; flex: 1; }
    .rm-tab-name { display: block; font-size: 14px; font-weight: 700; }
    .rm-tab.is-active .rm-tab-name { color: var(--accent-blue-d); }
    .rm-tab-sub { display: block; font-size: 12px; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .rm-count { flex: none; min-width: 24px; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 700; text-align: center; background: #eef4f8; color: var(--text-sub); }
    .rm-count.is-alert { background: #fff4d6; color: #a86f00; }
    .rm-panel[hidden] { display: none; }
    .rm-head { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px; padding: 16px 18px; border-bottom: 1px solid var(--border-dim); }
    .rm-head h2 { margin: 0; font-size: 16px; font-weight: 700; color: var(--text); }
    .rm-head p { margin: 3px 0 0; font-size: 12.5px; color: var(--text-muted); }
    .rm-head .rm-head-text { margin-right: auto; }
    .rm-date { display: flex; align-items: flex-end; gap: 8px; }
    .rm-empty { text-align: center; padding: 28px !important; color: var(--text-muted); }
    @media (max-width: 760px) {
        .rm-tabs { grid-template-columns: 1fr; }
    }
</style>
@endpush

@php
    $tabs = [
        'remittances' => ['Remittances', 'Cash riders handed over', $pendingCount, 'M3 7h18v10H3zM12 9.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5zM6 10v.01M18 14v.01'],
        'riders'      => ['COD per rider', 'What each rider still holds', $riders->count(), 'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1'],
        'daily'       => ['Daily reconciliation', 'Collected vs remitted · ' . $date->format('M j'), $daily->count(), 'M8 3v3M16 3v3M4 8h16M5 5h14a1 1 0 0 1 1 1v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zM9 14l2 2 4-4'],
    ];
@endphp

@section('content')
<div class="page-header"><h1>COD Remittances</h1></div>
<div class="page-body">
    <nav class="rm-tabs" role="tablist" aria-label="COD remittance views">
        @foreach($tabs as $key => [$name, $sub, $count, $icon])
            <a href="{{ route('sc.remittances', array_filter(['tab' => $key, 'date' => request('date')])) }}" id="rm-tab-{{ $key }}"
               class="rm-tab {{ $tab === $key ? 'is-active' : '' }}" role="tab" data-rm-tab="{{ $key }}"
               aria-controls="rm-panel-{{ $key }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}">
                <span class="rm-tab-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $icon }}"/></svg></span>
                <span class="rm-tab-text"><span class="rm-tab-name">{{ $name }}</span><span class="rm-tab-sub">{{ $sub }}</span></span>
                <span class="rm-count {{ $key === 'remittances' && $count > 0 ? 'is-alert' : '' }}" @if($key === 'remittances') title="Waiting for you to confirm" @endif>{{ $count }}</span>
            </a>
        @endforeach
    </nav>

    {{-- Remittances riders sent: confirm the cash once it is in hand. --}}
    <section class="rm-panel" id="rm-panel-remittances" role="tabpanel" aria-labelledby="rm-tab-remittances" @if($tab !== 'remittances') hidden @endif>
        <div class="card">
            <div class="rm-head">
                <div class="rm-head-text">
                    <h2>Remittances</h2>
                    <p>Cash each rider handed over. <strong>Accept</strong> it with the cash you counted: a match puts the rider's pay for those COD deliveries in their wallet;
                        a shortage is charged to the rider and their pay stays on hold until they pay it or you take it from their earnings.
                        <strong>Reject</strong> it when the cash wasn't handed over: it doesn't count and the rider remits again.@if($pendingCount > 0) <strong style="color:#a86f00;">{{ $pendingCount }} waiting for you.</strong>@endif</p>
                </div>
            </div>
            {{-- History: your riders' remittances only; filter them. --}}
            <form method="GET" action="{{ route('sc.remittances') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;padding:0 16px 12px;" data-remit-filter>
                <input type="hidden" name="tab" value="remittances">
                <label class="text-muted" style="font-size:12px;">Status
                    <select name="status" class="form-select" style="display:block;margin-top:2px;">
                        <option value="">All</option>
                        @foreach (\App\Models\RiderRemittance::STATUS_LABELS as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }}</option>@endforeach
                    </select></label>
                <label class="text-muted" style="font-size:12px;">Rider
                    <select name="rider" class="form-select" style="display:block;margin-top:2px;">
                        <option value="">All riders</option>
                        @foreach ($riders as $row)<option value="{{ $row['rider']->id }}" @selected((int) request('rider') === $row['rider']->id)>{{ $row['rider']->full_name }}</option>@endforeach
                    </select></label>
                <label class="text-muted" style="font-size:12px;">From<input type="date" name="from" value="{{ request('from') }}" class="form-input" style="display:block;margin-top:2px;"></label>
                <label class="text-muted" style="font-size:12px;">To<input type="date" name="to" value="{{ request('to') }}" class="form-input" style="display:block;margin-top:2px;"></label>
                <button class="btn btn-blue btn-sm">Filter</button>
                @if (request()->hasAny(['status', 'rider', 'from', 'to']))<a href="{{ route('sc.remittances', ['tab' => 'remittances']) }}" class="btn btn-ghost btn-sm">Clear</a>@endif
            </form>
            <table class="data-table">
                <thead><tr><th>Sent</th><th>Rider</th><th>Cash handed over</th><th>Rider's pay kept</th><th>COD balance then</th><th>Reconciliation</th><th>Accept or reject</th></tr></thead>
                <tbody>
                @forelse($remittances as $rm)
                    <tr data-remittance="{{ $rm->id }}">
                        <td>{{ $rm->created_at->format('M j, g:i A') }}</td>
                        <td>{{ $rm->rider->full_name ?? '—' }}</td>
                        <td style="font-weight:700;">₱{{ number_format($rm->amount, 2) }}@if($rm->status === 'confirmed' && $rm->counted_amount !== null)<div class="text-muted" style="font-size:11.5px;font-weight:400;">counted</div>@endif</td>
                        <td>{{ (float) $rm->pay_kept > 0 ? '₱' . number_format($rm->pay_kept, 2) : '—' }}</td>
                        <td>₱{{ number_format($rm->expected_amount, 2) }}</td>
                        <td>@if($rm->status === 'rejected')<span class="badge badge-gray">Rejected</span>
                            @elseif($rm->status === 'pending')<span class="badge">Not counted yet</span>
                            @elseif($rm->shortfall() > 0)<span class="badge badge-red" data-shortfall>Short ₱{{ number_format($rm->shortfall(), 2) }}</span>
                            @else <span class="badge badge-green">Matches</span>@if((float) $rm->deducted > 0)<div class="text-muted" style="font-size:11.5px;">₱{{ number_format($rm->deducted, 2) }} taken from earnings</div>@endif
                            @endif</td>
                        <td>
                            @if($rm->status === 'pending')
                                <form method="POST" action="{{ route('sc.remittances.confirm', $rm) }}" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;" data-count-form>@csrf
                                    <input type="number" name="counted" step="0.01" min="0" max="{{ number_format((float) $rm->expected_amount - (float) $rm->pay_kept, 2, '.', '') }}"
                                           value="{{ number_format((float) $rm->amount, 2, '.', '') }}" class="form-input" style="width:120px;" aria-label="Cash counted (₱)" title="Cash you counted">
                                    <button class="btn btn-green btn-sm">Accept</button>
                                </form>
                                <details style="margin-top:6px;" data-reject-remittance><summary class="text-sm" style="cursor:pointer;color:#B91C1C;">Reject…</summary>
                                    <form method="POST" action="{{ route('sc.remittances.reject', $rm) }}" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;">@csrf
                                        <input name="reason" class="form-input" placeholder="Why (e.g. the cash wasn't handed over)" required minlength="5" maxlength="300" style="flex:1;min-width:160px;" aria-label="Reason">
                                        <button class="btn btn-red btn-sm">Reject</button>
                                    </form>
                                </details>
                            @elseif($rm->status === 'rejected')
                                Rejected {{ $rm->rejected_at?->format('M j, g:i A') }}@if($rm->rejecter) by {{ $rm->rejecter->name }}@endif
                                <div class="text-muted" style="font-size:11.5px;">{{ $rm->reject_reason }}</div>
                            @else
                                Accepted {{ $rm->confirmed_at?->format('M j, g:i A') }}@if($rm->confirmer) by {{ $rm->confirmer->name }}@endif
                                @if($rm->shortfall() > 0 && app(\App\Services\RiderWallet::class)->openShortage($rm->rider)?->id === $rm->id)
                                    <form method="POST" action="{{ route('sc.remittances.deduct', $rm) }}" style="margin-top:6px;" data-deduct
                                          onsubmit="return confirm({{ \Illuminate\Support\Js::from('Take ₱' . number_format($rm->shortfall(), 2) . ' from ' . ($rm->rider->full_name ?? 'the rider') . "'s earnings?") }});">@csrf
                                        <button class="btn btn-red btn-sm">Take ₱{{ number_format($rm->shortfall(), 2) }} from earnings</button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="rm-empty">{{ request()->hasAny(['status', 'rider', 'from', 'to']) ? 'No remittances match the filter.' : 'No remittances yet.' }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin-top:12px;">{{ $remittances->links() }}</div>
    </section>

    {{-- Running COD totals per rider. --}}
    <section class="rm-panel" id="rm-panel-riders" role="tabpanel" aria-labelledby="rm-tab-riders" @if($tab !== 'riders') hidden @endif>
        <div class="card">
            <div class="rm-head">
                <div class="rm-head-text">
                    <h2>COD per rider</h2>
                    <p>Cash each rider has collected so far, and what they should hand over next.</p>
                </div>
            </div>
            <table class="data-table">
                <thead><tr><th>Rider</th><th>Collected</th><th>Pending remittance</th><th>Outstanding COD</th><th>Next: pay kept / cash to you</th></tr></thead>
                <tbody>
                @forelse($riders as $row)
                    <tr data-rider-cod="{{ $row['rider']->full_name }}">
                        <td>{{ $row['rider']->full_name }}</td>
                        <td>₱{{ number_format($row['collected'], 2) }}</td>
                        <td>₱{{ number_format($row['pending'], 2) }}</td>
                        <td>@if($row['balance'] > 0)<span class="badge badge-yellow">₱{{ number_format($row['balance'], 2) }}</span>@else ₱0.00 @endif</td>
                        <td data-next-remit>₱{{ number_format($row['next']['pay'], 2) }} / <strong>₱{{ number_format($row['next']['remit'], 2) }}</strong>@if($row['next']['owed'] > 0)<span class="text-muted text-sm" style="display:block;">+ ₱{{ number_format($row['next']['owed'], 2) }} pay still owed to the rider</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="rm-empty">No riders yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- One day: COD collected vs remitted, per rider. --}}
    <section class="rm-panel" id="rm-panel-daily" role="tabpanel" aria-labelledby="rm-tab-daily" @if($tab !== 'daily') hidden @endif>
        <div class="card" id="reconciliation">
            <form method="GET" action="{{ route('sc.remittances') }}" class="rm-head">
                <input type="hidden" name="tab" value="daily">
                <div class="rm-head-text">
                    <h2>Daily reconciliation · {{ $date->format('M j, Y') }}</h2>
                    <p>COD each rider collected that day against what they remitted. Shortfalls are highlighted in red.</p>
                </div>
                <div class="rm-date">
                    <div><label class="form-label" for="rec-date">Date</label><input type="date" id="rec-date" name="date" value="{{ $date->toDateString() }}" class="form-input"></div>
                    <button class="btn btn-blue btn-sm">Show</button>
                </div>
            </form>
            <table class="data-table">
                <thead><tr><th>Rider</th><th>COD collected</th><th>Remitted</th><th>Difference</th><th>Orders</th></tr></thead>
                <tbody>
                @forelse($daily as $row)
                    <tr data-reconcile="{{ $row['rider']->full_name }}" @if($row['difference'] < 0) style="background:rgba(239,68,68,.08);" @endif>
                        <td>{{ $row['rider']->full_name }}</td>
                        <td>₱{{ number_format($row['collected'], 2) }}</td>
                        <td>₱{{ number_format($row['remitted'], 2) }}</td>
                        <td>
                            @if($row['difference'] < 0)<span class="badge badge-red" data-day-shortfall>−₱{{ number_format(-$row['difference'], 2) }} shortfall</span>
                            @elseif($row['difference'] > 0)<span class="badge badge-yellow">+₱{{ number_format($row['difference'], 2) }} over</span>
                            @else<span class="badge badge-green">₱0.00</span>@endif
                        </td>
                        <td style="font-size:12px;">
                            @foreach($row['orders'] as $d)
                                <div>{{ $d->parcel?->order?->number() ?? $d->parcel?->tracking_number }} · ₱{{ number_format($d->cod_collected, 2) }}</div>
                            @endforeach
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="rm-empty">No COD collected or remitted on this date.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script>
// Switch tabs without reloading; the address keeps the tab so refresh and Back land on it.
document.querySelectorAll('[data-rm-tab]').forEach(function (link) {
    link.addEventListener('click', function (e) {
        if (e.ctrlKey || e.metaKey || e.shiftKey) return;
        e.preventDefault();
        var key = link.getAttribute('data-rm-tab');
        document.querySelectorAll('[data-rm-tab]').forEach(function (l) {
            var on = l === link;
            l.classList.toggle('is-active', on);
            l.setAttribute('aria-selected', on ? 'true' : 'false');
            document.getElementById('rm-panel-' + l.getAttribute('data-rm-tab')).hidden = !on;
        });
        history.replaceState(null, '', link.href);
    });
});
</script>
@endpush
