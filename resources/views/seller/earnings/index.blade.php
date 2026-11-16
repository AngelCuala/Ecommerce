<x-seller-layout title="Earnings & Payouts" active="earnings">
<style>
    .er-card { background:#fff; border:1px solid #ECE7E3; border-radius:.75rem; }
    .er-label { font-size:.7rem; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:#a39890; }
    .er-table th { padding:.6rem .8rem; font-size:.7rem; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:#a39890; background:#FCFBFA; text-align:right; }
    .er-table th:first-child, .er-table td:first-child { text-align:left; }
    .er-table td { padding:.65rem .8rem; border-top:1px solid #F2EEEA; text-align:right; white-space:nowrap; }
    .er-pill { display:inline-block; padding:.1rem .5rem; border-radius:9999px; font-size:.72rem; font-weight:700; }
</style>
@php
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
    $tz = config('app.business_timezone');
    $pill = ['on_hold' => 'background:#FEF3C7;color:#92400E;', 'available' => 'background:#DCFCE7;color:#166534;', 'reversed' => 'background:#F3F4F6;color:#6B7280;',
        'pending' => 'background:#DBEAFE;color:#1E40AF;', 'paid' => 'background:#DCFCE7;color:#166534;', 'rejected' => 'background:#FEE2E2;color:#B91C1C;'];
@endphp
<div class="mx-auto max-w-6xl">
    <div class="mb-5">
        <span class="section-eyebrow">Finance</span>
        <h1 class="text-2xl">Earnings &amp; Payouts</h1>
        <p class="text-sm" style="color:#8a7f78;">Your wallet adds up your orders: each delivered order is <strong>On hold</strong> during the {{ \App\Models\Order::RETURN_DAYS }}-day return window
            and becomes <strong>Available</strong> once the buyer confirms the item is OK or the window ends. A refunded order is <strong>Reversed</strong>.
            You get the item price minus ALVY's commission; ALVY vouchers and shipping vouchers are paid by ALVY. Withdraw any amount, no fee.</p>
    </div>

    @if ($errors->any())<div class="mb-4 rounded-lg px-4 py-2.5 text-sm" style="background:#FEF2F2;color:#B91C1C;">{{ $errors->first() }}</div>@endif

    <div class="er-card mb-5 grid grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Available', $balances['available'], 'You can withdraw it', '#047857', 'available'],
            ['On hold', $balances['on_hold'], 'Delivered, in the return window', '#B45309', 'on-hold'],
            ['Processing', $balances['processing'], 'Withdrawal ALVY is sending', '#1D4ED8', 'pending-payout'],
            ['On the way', $balances['in_progress'], 'Orders not delivered yet', '#222', 'in-progress'],
        ] as $i => [$label, $value, $note, $color, $key])
            <div class="px-5 py-4 {{ $i > 0 ? 'border-l' : '' }}" style="border-color:#F2EEEA;">
                <p class="er-label">{{ $label }}</p>
                <p class="mt-1 font-display text-2xl font-bold" style="color:{{ $color }};" data-balance="{{ $key }}">{{ $peso($value) }}</p>
                <p class="mt-0.5 text-xs" style="color:#8a7f78;">{{ $note }}</p>
            </div>
        @endforeach
    </div>

    {{-- Withdraw: any amount up to the available balance, to a saved or new GCash, Maya or bank account. --}}
    <form method="POST" action="{{ route('seller.earnings.payout') }}" class="er-card mb-5 px-5 py-4" id="payout-form" data-withdraw>
        @csrf
        <input type="hidden" name="request_token" value="{{ $token }}">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label for="amount" class="er-label">Withdraw (₱) · no fee</label>
                <input type="number" id="amount" name="amount" step="0.01" min="0.01" max="{{ $balances['available'] }}" value="{{ old('amount', $balances['available'] > 0 ? number_format($balances['available'], 2, '.', '') : '') }}" class="input mt-1 !py-1.5">
            </div>
            <button id="payout-btn" class="btn-primary disabled:cursor-not-allowed disabled:opacity-50" @disabled($balances['available'] <= 0 || $balances['processing'] > 0)>Withdraw</button>
            @if ($balances['processing'] > 0)<p class="text-xs" style="color:#1D4ED8;">A withdrawal is processing: you can withdraw again once ALVY sends it.</p>@endif
        </div>
        <div class="mt-3 grid gap-2 text-sm">
            @foreach ($accounts as $a)
                <label class="flex items-center gap-2"><input type="radio" name="payout_account_id" value="{{ $a->id }}" @checked(old('payout_account_id', $loop->first ? $a->id : null) == $a->id)> {{ $a->label() }} · {{ $a->account_name }}</label>
            @endforeach
            <details @if ($accounts->isEmpty() || old('account_number')) open @endif>
                <summary class="cursor-pointer font-semibold">{{ $accounts->isEmpty() ? 'Where to send it' : 'Use another account' }}</summary>
                <div class="mt-2 flex flex-wrap gap-2">
                    <select name="method" class="input !py-1.5" aria-label="Send to" onchange="this.form.querySelector('[data-bank]').hidden = this.value !== 'bank'; this.form.querySelectorAll('[name=payout_account_id]').forEach(r => r.checked = false);">
                        @foreach (\App\Models\PayoutAccount::METHODS as $k => $label)<option value="{{ $k }}" @selected(old('method', 'gcash') === $k)>{{ $label }}</option>@endforeach
                    </select>
                    <input name="bank_name" class="input !py-1.5" placeholder="Bank name (e.g. BDO)" value="{{ old('bank_name') }}" data-bank @if (old('method') !== 'bank') hidden @endif maxlength="80">
                    <input name="account_number" class="input !py-1.5" placeholder="Mobile (09…) or account number" value="{{ old('account_number') }}" maxlength="30"
                           oninput="this.form.querySelectorAll('[name=payout_account_id]').forEach(r => r.checked = false);">
                    <input name="account_name" class="input !py-1.5" placeholder="Name on the account" value="{{ old('account_name') }}" maxlength="120">
                </div>
            </details>
        </div>
    </form>

    {{-- The ledger: one line per delivered order; the balance is these lines minus the withdrawals. --}}
    <div class="er-card mb-5 overflow-x-auto" data-ledger>
        <div class="px-5 py-3"><h2 class="text-base">Wallet ledger</h2></div>
        <table class="er-table w-full text-sm">
            <thead><tr><th>Order</th><th>Delivered</th><th>Status</th><th>Release</th><th>Amount</th></tr></thead>
            <tbody>
            @forelse ($ledger as $l)
                <tr data-ledger-line="{{ $l->order_id }}" data-status="{{ $l->status }}">
                    <td>@if ($l->order)<a href="{{ route('seller.orders.show', $l->order_id) }}" class="font-semibold hover:underline">{{ $l->order->number() }}</a>@else {{ $l->note }} @endif
                        @if ($l->type === 'return_reversal')<div class="text-xs" style="color:#8a7f78;">Refunded after it was available</div>@endif</td>
                    <td>{{ $l->earned_at?->copy()->tz($tz)->format('M j, Y') ?? '—' }}</td>
                    <td><span class="er-pill" style="{{ $pill[$l->status] ?? '' }}">{{ $l->statusLabel() }}</span></td>
                    <td>@if ($l->status === 'on_hold' && $l->release_at)Expected {{ $l->release_at->copy()->tz($tz)->format('M j') }}@else — @endif</td>
                    <td class="font-semibold" style="{{ $l->status === 'reversed' ? 'text-decoration:line-through;color:#a39890;' : ((float) $l->amount < 0 ? 'color:#B91C1C;' : 'color:#047857;') }}">{{ (float) $l->amount < 0 ? '−' : '+' }}{{ $peso(abs((float) $l->amount)) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;color:#8a7f78;">Nothing yet: an order shows here once it is delivered.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="px-5 py-3">{{ $ledger->links() }}</div>
    </div>

    <div class="er-card mb-5 overflow-x-auto">
        <div class="px-5 py-3"><h2 class="text-base">Withdrawals</h2></div>
        <table class="er-table w-full text-sm">
            <thead><tr><th>Requested</th><th>To</th><th>Amount</th><th>Status</th><th>Reference</th></tr></thead>
            <tbody>
            @forelse ($payouts as $p)
                <tr data-payout="{{ $p->id }}"><td>{{ $p->created_at->copy()->tz($tz)->format('M j, Y g:i A') }}</td><td>{{ $p->destination() ?? '—' }}</td><td>−{{ $peso($p->amount) }}</td>
                    <td><span class="er-pill" style="{{ $pill[$p->status] ?? '' }}">{{ $p->statusLabel() }}</span>@if ($p->reject_reason)<div class="text-xs" style="color:#B91C1C;white-space:normal;">{{ $p->reject_reason }}</div>@endif</td>
                    <td>{{ $p->reference ?? '—' }}</td></tr>
            @empty
                <tr><td colspan="5" style="text-align:center;color:#8a7f78;">No withdrawals yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="er-card overflow-x-auto">
        <div class="px-5 py-3"><h2 class="text-base">Per-order breakdown</h2></div>
        <table class="er-table w-full text-sm">
            <thead><tr><th>Order</th><th>Status</th><th>Gross</th><th>Store voucher</th><th>Promos</th><th>Commission</th><th>Net</th></tr></thead>
            <tbody>
            @forelse ($orders as $order)
                @php $it = $order->items; @endphp
                <tr data-order="{{ $order->id }}">
                    <td><a href="{{ route('seller.orders.show', $order->id) }}" class="font-semibold hover:underline">{{ $order->number() }}</a></td>
                    <td>{{ $order->status }}</td>
                    <td>{{ $peso($it->sum(fn ($i) => $i->quantity * $i->price)) }}</td>
                    <td>−{{ $peso($it->sum('seller_discount')) }}</td>
                    <td>−{{ $peso($it->sum('promo_discount')) }}</td>
                    <td>−{{ $peso($it->sum('commission_amount')) }}</td>
                    <td class="font-semibold" style="color:#047857;">{{ $peso($it->sum('seller_earning')) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center" style="text-align:center;color:#8a7f78;">No sales yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="px-5 py-3">{{ $orders->links() }}</div>
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
