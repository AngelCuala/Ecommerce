<x-seller-layout title="Earnings & Payouts" active="earnings">
<style>
    .er-card { background:#fff; border:1px solid #ECE7E3; border-radius:.75rem; }
    .er-label { font-size:.7rem; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:#a39890; }
    .er-table th { padding:.6rem .8rem; font-size:.7rem; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:#a39890; background:#FCFBFA; text-align:right; }
    .er-table th:first-child, .er-table td:first-child { text-align:left; }
    .er-table td { padding:.65rem .8rem; border-top:1px solid #F2EEEA; text-align:right; white-space:nowrap; }
    .er-pill { display:inline-block; padding:.1rem .5rem; border-radius:9999px; font-size:.72rem; font-weight:700; }
    .er-h2 { font-size:1rem; font-weight:700; color:#222; }
    /* How the wallet works: three short steps instead of a paragraph. */
    .er-flow { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.75rem; margin-top:.9rem; }
    @media (max-width:760px) { .er-flow { grid-template-columns:1fr; } }
    .er-flow > div { display:flex; gap:.6rem; align-items:flex-start; padding:.7rem .85rem; border-radius:.75rem; background:#fff; border:1px solid #ECE7E3; font-size:.8rem; line-height:1.45; color:#78716C; }
    .er-flow b { display:block; color:#222; font-size:.82rem; }
    .er-flow i { display:flex; flex:none; width:1.6rem; height:1.6rem; align-items:center; justify-content:center; border-radius:999px; font-style:normal; font-weight:800; font-size:.75rem; }
    /* Withdraw card: how much (left), where to (right). */
    .er-wd { display:grid; grid-template-columns:minmax(0,1fr) minmax(0,1.4fr); }
    .er-wd > div { padding:1.1rem 1.25rem; }
    .er-wd > div + div { border-left:1px solid #F2EEEA; }
    @media (max-width:860px) { .er-wd { grid-template-columns:1fr; } .er-wd > div + div { border-left:0; border-top:1px solid #F2EEEA; } }
    .er-amount { display:flex; align-items:center; border:1px solid #E7E0DA; border-radius:.6rem; background:#fff; overflow:hidden; }
    .er-amount > span { padding:0 .75rem; font-weight:700; color:#78716C; }
    .er-amount input { flex:1; min-width:0; border:0 !important; box-shadow:none !important; padding:.65rem .25rem !important; font-size:1.1rem; font-weight:700; outline:none; background:transparent; }
    .er-amount button { margin:.3rem; padding:.3rem .65rem; border-radius:.45rem; background:#FFF1EB; color:#C2410C; font-size:.75rem; font-weight:700; }
    .er-go { width:100%; margin-top:.75rem; justify-content:center; padding:.7rem 1rem; }
    .er-acct { display:flex; align-items:center; gap:.6rem; padding:.6rem .75rem; border:1px solid #ECE7E3; border-radius:.6rem; cursor:pointer; font-size:.85rem; }
    .er-acct:has(input:checked) { border-color:#fa4e1c; background:#FFF7F3; }
    .er-new summary { list-style:none; cursor:pointer; font-size:.85rem; font-weight:600; color:#C2410C; }
    .er-new summary::-webkit-details-marker { display:none; }
    .er-fields { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:.5rem; margin-top:.6rem; }
    .er-fields .input { width:100%; }
    .er-fields [name=account_name], .er-fields:has([data-bank]:not([hidden])) [name=account_number] { grid-column:1 / -1; }
    @media (max-width:560px) { .er-fields { grid-template-columns:1fr; } }
</style>
@php
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
    $tz = config('app.business_timezone');
    $pill = ['on_hold' => 'background:#FEF3C7;color:#92400E;', 'available' => 'background:#DCFCE7;color:#166534;', 'reversed' => 'background:#F3F4F6;color:#6B7280;',
        'pending' => 'background:#DBEAFE;color:#1E40AF;', 'paid' => 'background:#DCFCE7;color:#166534;', 'rejected' => 'background:#FEE2E2;color:#B91C1C;'];
@endphp
<div class="mx-auto max-w-6xl">
    <div class="mb-5">
        <h1 class="font-display text-2xl font-bold" style="color:#222;">Earnings &amp; Payouts</h1>
        <p class="mt-0.5 text-sm" style="color:#8a7f78;">What you earned from delivered orders, and what you can withdraw. No fee on withdrawals.</p>
        <div class="er-flow" data-wallet-steps>
            <div><i style="background:#FEF3C7;color:#92400E;">1</i><span><b>On hold</b>A delivered order waits out the {{ \App\Models\Order::RETURN_DAYS }}-day return window.</span></div>
            <div><i style="background:#DCFCE7;color:#166534;">2</i><span><b>Available</b>Once the buyer confirms the item or the window ends. A refunded order is reversed.</span></div>
            <div><i style="background:#FFF1EB;color:#C2410C;">3</i><span><b>Withdraw</b>Any amount, to GCash, Maya or your bank: the item price minus ALVY's commission.</span></div>
        </div>
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
    <form method="POST" action="{{ route('seller.earnings.payout') }}" class="er-card mb-5 er-wd" id="payout-form" data-withdraw>
        @csrf
        <input type="hidden" name="request_token" value="{{ $token }}">
        {{-- How much --}}
        <div>
            <h2 class="er-h2">Withdraw</h2>
            <label for="amount" class="er-label mt-3 block">Amount · no fee</label>
            <div class="er-amount mt-1">
                <span>₱</span>
                <input type="number" id="amount" name="amount" step="0.01" min="0.01" max="{{ $balances['available'] }}" placeholder="0.00"
                       value="{{ old('amount', $balances['available'] > 0 ? number_format($balances['available'], 2, '.', '') : '') }}" @disabled($balances['available'] <= 0)>
                @if ($balances['available'] > 0)
                    <button type="button" onclick="document.getElementById('amount').value = this.dataset.max" data-max="{{ number_format($balances['available'], 2, '.', '') }}">Max</button>
                @endif
            </div>
            <p class="mt-1 text-xs" style="color:#8a7f78;">Available: <b style="color:#047857;">{{ $peso($balances['available']) }}</b></p>
            <button id="payout-btn" class="btn-primary er-go disabled:cursor-not-allowed disabled:opacity-50" @disabled($balances['available'] <= 0 || $balances['processing'] > 0)>Withdraw</button>
            @if ($balances['processing'] > 0)
                <p class="mt-2 text-xs" style="color:#1D4ED8;">A withdrawal is processing: you can withdraw again once ALVY sends it.</p>
            @elseif ($balances['available'] <= 0)
                <p class="mt-2 text-xs" style="color:#8a7f78;">Nothing to withdraw yet. Money becomes available after the return window{{ $balances['on_hold'] > 0 ? ' (' . $peso($balances['on_hold']) . ' on hold now)' : '' }}.</p>
            @endif
        </div>

        {{-- Where to --}}
        <div>
            <h2 class="er-h2">Send to</h2>
            <div class="mt-3 grid gap-2">
                @foreach ($accounts as $a)
                    <label class="er-acct"><input type="radio" name="payout_account_id" value="{{ $a->id }}" @checked(old('payout_account_id', $loop->first ? $a->id : null) == $a->id)>
                        <span><b>{{ $a->label() }}</b> · {{ $a->account_name }}</span></label>
                @endforeach
                <details class="er-new" @if ($accounts->isEmpty() || old('account_number')) open @endif>
                    @if ($accounts->isNotEmpty())
                        <summary>+ Use another account</summary>
                    @else
                        <summary style="cursor:default;color:#78716C;font-weight:500;" onclick="event.preventDefault()">The account to send it to:</summary>
                    @endif
                    <div class="er-fields">
                        <select name="method" class="input" aria-label="Send to" onchange="this.form.querySelector('[data-bank]').hidden = this.value !== 'bank'; this.form.querySelectorAll('[name=payout_account_id]').forEach(r => r.checked = false);">
                            @foreach (\App\Models\PayoutAccount::METHODS as $k => $label)<option value="{{ $k }}" @selected(old('method', 'gcash') === $k)>{{ $label }}</option>@endforeach
                        </select>
                        <input name="bank_name" class="input" placeholder="Bank name (e.g. BDO)" value="{{ old('bank_name') }}" data-bank @if (old('method') !== 'bank') hidden @endif maxlength="80">
                        <input name="account_number" class="input" placeholder="Mobile (09…) or account number" value="{{ old('account_number') }}" maxlength="30"
                               oninput="this.form.querySelectorAll('[name=payout_account_id]').forEach(r => r.checked = false);">
                        <input name="account_name" class="input" placeholder="Name on the account" value="{{ old('account_name') }}" maxlength="120">
                    </div>
                </details>
            </div>
        </div>
    </form>

    {{-- The ledger: one line per delivered order; the balance is these lines minus the withdrawals. --}}
    <div class="er-card mb-5 overflow-x-auto" data-ledger>
        <div class="px-5 py-3"><h2 class="er-h2">Wallet ledger</h2></div>
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
        <div class="px-5 py-3"><h2 class="er-h2">Withdrawals</h2></div>
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
        <div class="px-5 py-3"><h2 class="er-h2">Per-order breakdown</h2></div>
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
