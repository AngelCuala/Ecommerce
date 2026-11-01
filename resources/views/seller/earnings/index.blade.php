<x-seller-layout title="Earnings & Payouts" active="earnings">
<style>
    .er-card { background:#fff; border:1px solid #ECE7E3; border-radius:.75rem; }
    .er-label { font-size:.7rem; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:#a39890; }
    .er-table th { padding:.6rem .8rem; font-size:.7rem; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:#a39890; background:#FCFBFA; text-align:right; }
    .er-table th:first-child, .er-table td:first-child { text-align:left; }
    .er-table td { padding:.65rem .8rem; border-top:1px solid #F2EEEA; text-align:right; white-space:nowrap; }
</style>
<div class="mx-auto max-w-6xl">
    <div class="mb-5">
        <span class="section-eyebrow">Finance</span>
        <h1 class="text-2xl">Earnings &amp; Payouts</h1>
        <p class="text-sm" style="color:#8a7f78;">Earnings stay Pending until the order is Completed, then become Available. Cancelled and refunded orders don't count. ALVY vouchers and shipping vouchers are paid by ALVY and don't reduce your earnings.</p>
    </div>

    @if ($errors->any())<div class="mb-4 rounded-lg px-4 py-2.5 text-sm" style="background:#FEF2F2;color:#B91C1C;">{{ $errors->first() }}</div>@endif

    <div class="er-card mb-5 grid grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Pending', $balances['pending'], 'Orders not completed yet', '#B45309', 'pending'],
            ['Available', $balances['available'], 'Ready to request', '#047857', 'available'],
            ['Pending payout', $balances['pending_payout'], 'Requested, not sent yet', '#1D4ED8', 'pending-payout'],
            ['Earned (completed)', $balances['completed'], 'All completed orders', '#222', 'completed'],
        ] as $i => [$label, $value, $note, $color, $key])
            <div class="px-5 py-4 {{ $i > 0 ? 'border-l' : '' }}" style="border-color:#F2EEEA;">
                <p class="er-label">{{ $label }}</p>
                <p class="mt-1 font-display text-2xl font-bold" style="color:{{ $color }};" data-balance="{{ $key }}">₱{{ number_format($value, 2) }}</p>
                <p class="mt-0.5 text-xs" style="color:#8a7f78;">{{ $note }}</p>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('seller.earnings.payout') }}" class="er-card mb-5 flex flex-wrap items-end gap-3 px-5 py-4" id="payout-form">
        @csrf
        <input type="hidden" name="request_token" value="{{ $token }}">
        <div>
            <label for="amount" class="er-label">Request payout (₱)</label>
            <input type="number" id="amount" name="amount" step="0.01" min="{{ \App\Models\SellerPayout::MINIMUM }}" max="{{ $balances['available'] }}" value="{{ old('amount') }}" class="input mt-1 !py-1.5" placeholder="Min ₱{{ number_format(\App\Models\SellerPayout::MINIMUM, 2) }}">
        </div>
        <button id="payout-btn" class="btn-primary disabled:cursor-not-allowed disabled:opacity-50" @disabled($balances['available'] < \App\Models\SellerPayout::MINIMUM)>Request Payout</button>
        <p class="text-xs" style="color:#8a7f78;">Minimum ₱{{ number_format(\App\Models\SellerPayout::MINIMUM, 2) }}, up to your available balance.</p>
    </form>

    <div class="er-card mb-5 overflow-x-auto">
        <div class="px-5 py-3"><h2 class="text-base">Per-order breakdown</h2></div>
        <table class="er-table w-full text-sm">
            <thead><tr><th>Order</th><th>Status</th><th>Gross</th><th>Store voucher</th><th>Promos</th><th>Commission</th><th>Net</th><th>Balance</th></tr></thead>
            <tbody>
            @forelse ($orders as $order)
                @php $it = $order->items; @endphp
                <tr data-order="{{ $order->id }}">
                    <td><a href="{{ route('seller.orders.show', $order->id) }}" class="font-semibold hover:underline">{{ $order->number() }}</a></td>
                    <td>{{ $order->status }}</td>
                    <td>₱{{ number_format($it->sum(fn ($i) => $i->quantity * $i->price), 2) }}</td>
                    <td>−₱{{ number_format($it->sum('seller_discount'), 2) }}</td>
                    <td>−₱{{ number_format($it->sum('promo_discount'), 2) }}</td>
                    <td>−₱{{ number_format($it->sum('commission_amount'), 2) }}</td>
                    <td class="font-semibold" style="color:#047857;">₱{{ number_format($it->sum('seller_earning'), 2) }}</td>
                    <td>{{ $order->status === \App\Models\Order::COMPLETED ? 'Available' : 'Pending' }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-center" style="text-align:center;color:#8a7f78;">No sales yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="px-5 py-3">{{ $orders->links() }}</div>
    </div>

    <div class="er-card overflow-x-auto">
        <div class="px-5 py-3"><h2 class="text-base">Payout requests</h2></div>
        <table class="er-table w-full text-sm">
            <thead><tr><th>Requested</th><th>Amount</th><th>Status</th><th>Reference</th></tr></thead>
            <tbody>
            @forelse ($payouts as $p)
                <tr><td>{{ $p->created_at->format('M j, Y g:i A') }}</td><td>₱{{ number_format($p->amount, 2) }}</td><td>{{ ucfirst($p->status) }}</td><td>{{ $p->reference ?? '—' }}</td></tr>
            @empty
                <tr><td colspan="4" style="text-align:center;color:#8a7f78;">No payout requests yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
<script>
// One request per click: the button locks after the first submit.
document.getElementById('payout-form').addEventListener('submit', function (e) {
    var b = document.getElementById('payout-btn');
    if (b.dataset.sent) { e.preventDefault(); return; }
    b.dataset.sent = '1'; b.disabled = true; b.textContent = 'Sending…';
});
</script>
</x-seller-layout>
