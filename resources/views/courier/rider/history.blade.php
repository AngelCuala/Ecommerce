<x-courier-layout title="Job History" active="rider-history">
<div class="cx-page-head"><h1 class="font-display">Job History</h1><p>Every pickup and delivery attempt, with its pay.</p></div>

<form method="GET" class="cx-filters card" style="padding:14px 18px;margin-bottom:16px;display:flex;flex-wrap:wrap;gap:10px;align-items:end;">
    <div><label class="cx-label" for="from">From</label><input type="date" id="from" name="from" value="{{ request('from') }}" class="input"></div>
    <div><label class="cx-label" for="to">To</label><input type="date" id="to" name="to" value="{{ request('to') }}" class="input"></div>
    <button class="cx-btn cx-btn-navy cx-btn-sm">Filter</button>
    @error('to')<p class="cx-alert cx-alert-error">{{ $message }}</p>@enderror
</form>

<div class="card cx-table-wrap">
    <table class="cx-table">
        <thead><tr><th>Date</th><th>Tracking</th><th>Job</th><th>Result</th><th>Pay</th></tr></thead>
        <tbody>
        @forelse($rows as $r)
            <tr data-history="{{ $r['tracking'] }}">
                <td>{{ $r['at']->format('M j, Y g:i A') }}</td><td>{{ $r['tracking'] }}</td><td>{{ $r['type'] }}</td><td>{{ $r['result'] }}</td><td>₱{{ number_format($r['pay'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" style="text-align:center;padding:24px;">No jobs in this period.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
</x-courier-layout>
