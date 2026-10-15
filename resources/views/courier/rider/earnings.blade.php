<x-courier-layout title="Rider Pay" active="rider-earnings">
<div class="cx-page-head"><h1 class="font-display">Rider Pay</h1>
    <p>₱{{ number_format(\App\Services\RiderDispatch::PAY_PICKUP, 2) }} per completed pickup, ₱{{ number_format(\App\Services\RiderDispatch::PAY_DELIVERY, 2) }} per successful delivery, ₱0.00 per failed attempt. COD cash is not part of your pay.</p></div>

<div class="cx-grid cx-cols-3" style="margin-bottom:24px;">
    <div class="card cx-stat"><p class="cx-stat-value" data-today-pay>₱{{ number_format($todayPay, 2) }}</p><p class="cx-stat-label">Today</p></div>
    <div class="card cx-stat"><p class="cx-stat-value" data-week-pay>₱{{ number_format($weekPay, 2) }}</p><p class="cx-stat-label">This week</p></div>
    <div class="card cx-stat"><p class="cx-stat-value">{{ $today->count() }}</p><p class="cx-stat-label">Jobs today</p></div>
</div>

<h2 class="cx-section-title">Today</h2>
<div class="card cx-table-wrap">
    <table class="cx-table">
        <thead><tr><th>Time</th><th>Tracking</th><th>Job</th><th>Result</th><th>Pay</th></tr></thead>
        <tbody>
        @forelse($today as $r)
            <tr><td>{{ $r['at']->format('g:i A') }}</td><td>{{ $r['tracking'] }}</td><td>{{ $r['type'] }}</td><td>{{ $r['result'] }}</td><td>₱{{ number_format($r['pay'], 2) }}</td></tr>
        @empty
            <tr><td colspan="5" style="text-align:center;padding:24px;">No jobs yet today.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
</x-courier-layout>
