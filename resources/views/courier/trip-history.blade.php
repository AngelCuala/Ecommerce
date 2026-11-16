<x-courier-layout title="Job History" active="trips-history">
<div class="cx-page-head"><h1 class="font-display">Job History</h1><p>Every hub-to-hub trip you finished, declined or handed over, with its pay.</p></div>

<form method="GET" class="cx-filters card" style="padding:14px 18px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:10px;align-items:end;">
    <div><label class="cx-label" for="from">From</label><input type="date" id="from" name="from" value="{{ request('from') }}" class="input"></div>
    <div><label class="cx-label" for="to">To</label><input type="date" id="to" name="to" value="{{ request('to') }}" class="input"></div>
    <button class="cx-btn cx-btn-navy cx-btn-sm">Filter</button>
    @error('to')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror
</form>

<div class="card cx-table-wrap">
    <table class="cx-table">
        <thead><tr><th>Date</th><th>Trip</th><th>Route</th><th>Parcels</th><th>Result</th><th>Pay</th></tr></thead>
        <tbody>
        @forelse($rows as $r)
            <tr data-history="{{ $r['code'] }}">
                <td>{{ $r['at']->copy()->tz(config('app.business_timezone', 'Asia/Manila'))->format('M j, Y g:i A') }}</td>
                <td style="font-family:ui-monospace,monospace;font-weight:700;">{{ $r['code'] ?? '—' }}</td>
                <td>{{ $r['route'] ?? '—' }}</td>
                <td>{{ $r['parcels'] ?? '—' }}</td>
                <td>{{ $r['result'] }}@if($r['reason'])<div class="text-muted" style="font-size:12px;">{{ $r['reason'] }}</div>@endif</td>
                <td style="font-weight:700;color:{{ $r['pay'] > 0 ? 'var(--green)' : 'var(--text-muted)' }};">₱{{ number_format($r['pay'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="text-align:center;padding:24px;">No trips in this period.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
</x-courier-layout>
