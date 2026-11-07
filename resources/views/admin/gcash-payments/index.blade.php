<x-admin-layout title="GCash Payments" active="gcash">
@php $peso = fn ($v) => '₱' . number_format((float) $v, 2); @endphp

<div class="mb-5">
    <p class="text-sm" style="color:#6b90aa;">
        Buyers who choose GCash pay ALVY's GCash QR for the whole checkout and upload the receipt. Open your GCash app, check that the
        amount arrived with the Ref No., then approve: the sellers are told to prepare the orders and their share goes to their Earnings.
        Reject when nothing arrived or the amount is wrong: the orders are cancelled.
    </p>
</div>

@if (session('success'))
    <div class="mb-5 rounded-xl border p-3 text-sm" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">✓ {{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="mb-5 rounded-xl border p-3 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.25);color:#DC2626;">{{ session('error') }}</div>
@endif
@if ($errors->any())
    <div class="mb-5 rounded-xl border p-3 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.25);color:#DC2626;">{{ $errors->first() }}</div>
@endif

@php
    // A failed save opens the tab it came from.
    $errorTab = $errors->hasAny(['qr', 'name', 'number']) ? 'account' : ($errors->has('refund_reference') ? 'refunds' : null);
    $tabs = [
        'check'   => ['To check', $waiting->count() + $fixing->count(), $waiting->count() > 0],
        'refunds' => ['Refunds to send', $refunds->count(), $refunds->isNotEmpty()],
        'history' => ['Last 14 days', $recent->count(), false],
        'account' => ["ALVY's GCash account", null, ! $account['qr_url']],
    ];
@endphp
<style>
    .gc-tabs { display: flex; flex-wrap: wrap; gap: .25rem 1.5rem; margin-bottom: 1.25rem; border-bottom: 1px solid #E5EDF3; }
    .gc-tab { display: inline-flex; align-items: center; gap: .45rem; margin-bottom: -1px; padding: .7rem .125rem; border-bottom: 2px solid transparent; font-size: .9rem; font-weight: 700; color: #6b90aa; transition: color .15s; }
    .gc-tab:hover { color: #222; }
    .gc-tab[aria-selected="true"] { border-color: #fa4e1c; color: #222; }
    .gc-count { min-width: 1.35rem; padding: .05rem .45rem; border-radius: 9999px; background: #EEF3F7; font-size: .72rem; font-weight: 700; text-align: center; color: #47657c; }
    .gc-count.is-due { background: #fa4e1c; color: #fff; }
    .gc-dot { height: .5rem; width: .5rem; border-radius: 9999px; background: #DC2626; }
</style>
<div class="gc-tabs" role="tablist" aria-label="GCash payments" data-gcash-tabs data-error-tab="{{ $errorTab }}">
    @foreach ($tabs as $key => [$label, $count, $due])
        <button type="button" role="tab" id="gc-tab-{{ $key }}" aria-controls="gc-panel-{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" class="gc-tab" data-tab="{{ $key }}">
            {{ $label }}
            @if ($count !== null)
                <span class="gc-count {{ $due ? 'is-due' : '' }}">{{ $count }}</span>
            @elseif ($due)
                <span class="gc-dot" title="No QR yet: GCash is hidden at checkout"></span><span class="sr-only">(needs a QR)</span>
            @endif
        </button>
    @endforeach
</div>

<div role="tabpanel" id="gc-panel-check" aria-labelledby="gc-tab-check" data-gcash-panel="check">
{{-- ── To check ── --}}
<h2 class="mb-3 font-display text-lg font-bold" style="color:#222;">To check <span class="text-sm font-semibold" style="color:#6b90aa;">({{ $waiting->count() }})</span></h2>
<div class="mb-8 space-y-4" data-gcash-waiting>
    @forelse ($waiting as $group => $orders)
        @php
            $first = $orders->first();
            $total = (float) ($first->payment_expected ?? $orders->sum('total_price'));   // the exact amount the buyer was asked to send
            $proofUrl = route('admin.orders.payment-proof', $first->id);
            $hints = \App\Services\GcashChecks::hints($orders);
        @endphp
        <div class="card p-5" data-gcash-group="{{ $group }}">
            <div class="flex flex-col gap-5 md:flex-row">
                <div class="shrink-0">
                    @if ($first->payment_proof_path)
                        <a href="{{ $proofUrl }}" target="_blank" rel="noopener" title="Open the full receipt">
                            <img src="{{ $proofUrl }}" alt="GCash receipt" class="h-56 w-40 rounded-xl border bg-white object-contain" style="border-color:#DBEAFE;">
                        </a>
                    @else
                        <div class="flex h-56 w-40 items-center justify-center rounded-xl border text-xs" style="border-color:#F0E4DC;color:#b5a79d;">No receipt</div>
                    @endif
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs" style="color:#6b90aa;">Amount to find in ALVY's GCash</p>
                            <p class="font-display text-3xl font-extrabold" style="color:#0B2A5B;">{{ $peso($total) }}</p>
                            <p class="mt-1 text-sm">Ref No. <span class="font-mono font-bold" style="color:#222;">{{ $first->payment_reference ?: 'not given' }}</span></p>
                            @if ($first->payment_expected && abs($first->payment_expected - $orders->sum('total_price')) >= 0.005)
                                <p class="text-xs" style="color:#6b90aa;">Orders total ₱{{ number_format($orders->sum('total_price'), 2) }}; ALVY covers ₱{{ number_format($orders->sum('total_price') - $first->payment_expected, 2) }} so this amount is unique.</p>
                            @endif
                            {{-- What the receipt reader found: hints only, the admin decides. --}}
                            @foreach ($first->payment_check['attempts'] ?? [] as $i => $a)
                                <p class="mt-1 text-xs" style="color:#6b90aa;" data-gcash-attempt>Earlier receipt {{ $i + 1 }}: Ref {{ $a['reference'] ?? '—' }} · rejected: {{ implode(' ', array_column($a['problems'] ?? [], 'reason')) }}</p>
                            @endforeach
                            <ul class="mt-2 space-y-0.5 text-xs" data-gcash-hints>
                                @foreach ($hints as [$text, $tone])
                                    <li style="color:{{ ['ok' => '#047857', 'warn' => '#B45309', 'wait' => '#6b90aa'][$tone] }};">{{ ['ok' => '✓', 'warn' => '⚠', 'wait' => '…'][$tone] }} {{ $text }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="text-right text-xs" style="color:#6b90aa;">
                            <p>Paid {{ $first->created_at->format('M d, Y g:i A') }}</p>
                            <p class="mt-0.5">{{ $first->user->name ?? $first->full_name }} · {{ $first->user->email ?? $first->email }}</p>
                        </div>
                    </div>

                    <table class="mt-4 w-full text-sm">
                        <thead><tr class="text-left text-xs" style="color:#6b90aa;"><th class="py-1 font-semibold">Order</th><th class="py-1 font-semibold">Store</th><th class="py-1 text-right font-semibold">Items</th><th class="py-1 text-right font-semibold">Shipping</th><th class="py-1 text-right font-semibold">Total</th></tr></thead>
                        <tbody>
                            @foreach ($orders as $o)
                                <tr class="border-t" style="border-color:#F0E4DC;">
                                    <td class="py-1.5"><a href="{{ route('admin.orders.show', $o->id) }}" class="font-semibold hover:underline" style="color:#fa4e1c;">{{ $o->number() }}</a></td>
                                    <td class="py-1.5">{{ $o->seller?->storeName() ?? '—' }}</td>
                                    <td class="py-1.5 text-right">{{ $o->items->sum('quantity') }}</td>
                                    <td class="py-1.5 text-right">{{ $peso(max(0, $o->shipping_fee - $o->shipping_discount)) }}</td>
                                    <td class="py-1.5 text-right font-semibold">{{ $peso($o->total_price) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-4 flex flex-col gap-3 lg:flex-row lg:items-start">
                        <form method="POST" action="{{ route('admin.gcash.approve', $group) }}"
                              onsubmit="return confirm('Did {{ $peso($total) }} with Ref No. {{ $first->payment_reference }} arrive in ALVY\'s GCash?');">
                            @csrf
                            <button class="rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background:#059669;">Payment received · Approve</button>
                        </form>
                        <details class="flex-1 rounded-lg border px-3 py-2" style="border-color:#FECACA;">
                            <summary class="cursor-pointer text-sm font-semibold" style="color:#B91C1C;">Reject…</summary>
                            <form method="POST" action="{{ route('admin.gcash.reject', $group) }}" class="mt-3 space-y-2" data-gcash-reject>
                                @csrf
                                @foreach ($reasons as $key => $label)
                                    <label class="flex items-start gap-2 text-sm"><input type="radio" name="reason" value="{{ $key }}" class="mt-1" required> {{ $label }}</label>
                                @endforeach
                                <input name="note" maxlength="200" class="input w-full text-sm" placeholder="Note to the buyer (optional), e.g. received ₱500 only">
                                <label class="flex items-start gap-2 text-xs" style="color:#57534E;"><input type="checkbox" name="let_fix" value="1" class="mt-0.5" checked>
                                    Let the buyer fix it: they're told why and how, and can upload the right receipt within {{ \App\Services\GcashChecks::FIX_HOURS }} hours. Untick to cancel the order now.</label>
                                <label class="flex items-start gap-2 text-xs" style="color:#57534E;"><input type="checkbox" name="received" value="1" class="mt-0.5">
                                    Some money did arrive: list it under Refunds to send so it goes back to the buyer.</label>
                                <button class="rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background:#DC2626;"
                                        onclick="return confirm('Reject this payment?');">Reject</button>
                            </form>
                        </details>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="card p-6 text-center text-sm" style="color:#6b90aa;">No GCash payments to check.</div>
    @endforelse
</div>

{{-- ── Rejected: waiting for the buyer to upload the right receipt ── --}}
@if ($fixing->isNotEmpty())
    <h2 class="mb-3 font-display text-lg font-bold" style="color:#222;">Waiting for the buyer to fix <span class="text-sm font-semibold" style="color:#6b90aa;">({{ $fixing->count() }})</span></h2>
    <div class="card mb-8 overflow-x-auto" data-gcash-fixing>
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs" style="color:#6b90aa;"><th class="px-4 py-2">Orders</th><th class="px-4 py-2">Buyer</th><th class="px-4 py-2">Why it was rejected</th><th class="px-4 py-2">Fix by</th></tr></thead>
            <tbody>
                @foreach ($fixing as $orders)
                    @php $f = $orders->first(); $c = $f->payment_check ?? []; @endphp
                    <tr class="border-t align-top" style="border-color:#F0E4DC;">
                        <td class="px-4 py-2">@foreach ($orders as $o)<a href="{{ route('admin.orders.show', $o->id) }}" class="block font-semibold hover:underline" style="color:#fa4e1c;">{{ $o->number() }}</a>@endforeach
                            <span class="block text-xs" style="color:#6b90aa;">Try {{ $c['tries'] ?? 1 }} of {{ \App\Services\GcashChecks::MAX_TRIES }} · {{ ($c['rejection']['by'] ?? '') === 'reader' ? 'rejected by the receipt reader' : 'rejected by an admin' }}</span></td>
                        <td class="px-4 py-2">{{ $f->user->name ?? $f->full_name }}</td>
                        <td class="px-4 py-2 text-xs">@foreach ($c['rejection']['problems'] ?? [] as $p)<span class="block">{{ $p['reason'] }}</span>@endforeach</td>
                        <td class="px-4 py-2 text-xs">{{ ! empty($c['fix_by']) ? \Illuminate\Support\Carbon::parse($c['fix_by'])->tz(config('app.business_timezone', 'Asia/Manila'))->format('M j, g:i A') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif

</div>

<div role="tabpanel" id="gc-panel-refunds" aria-labelledby="gc-tab-refunds" data-gcash-panel="refunds">
{{-- ── Refunds to send ── --}}
<h2 class="mb-3 font-display text-lg font-bold" style="color:#222;">Refunds to send <span class="text-sm font-semibold" style="color:#6b90aa;">({{ $refunds->count() }})</span></h2>
<div class="card mb-8 overflow-x-auto" data-gcash-refunds>
    @if ($refunds->isEmpty())
        <p class="p-6 text-center text-sm" style="color:#6b90aa;">No GCash refunds waiting.</p>
    @else
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs" style="color:#6b90aa;"><th class="px-4 py-2">Order</th><th class="px-4 py-2">Buyer</th><th class="px-4 py-2">Why</th><th class="px-4 py-2 text-right">Send back</th><th class="px-4 py-2">Done?</th></tr></thead>
            <tbody>
                @foreach ($refunds as $o)
                    <tr class="border-t align-top" style="border-color:#F0E4DC;">
                        <td class="px-4 py-2"><a href="{{ route('admin.orders.show', $o->id) }}" class="font-semibold hover:underline" style="color:#fa4e1c;">{{ $o->number() }}</a>
                            <span class="block text-xs" style="color:#6b90aa;">paid Ref {{ $o->payment_reference ?: '—' }}</span></td>
                        <td class="px-4 py-2">{{ $o->user->name ?? $o->full_name }}<span class="block text-xs" style="color:#6b90aa;">{{ $o->phone ?: ($o->user->contact_no ?? '') }}</span></td>
                        <td class="px-4 py-2 text-xs">{{ $o->status === \App\Models\Order::REFUNDED ? 'Return approved' : ($o->cancellation_reason ?: $o->status) }}</td>
                        <td class="px-4 py-2 text-right font-bold">{{ $peso(\App\Http\Controllers\Admin\GcashPaymentController::refundAmount($o)) }}</td>
                        <td class="px-4 py-2">
                            <form method="POST" action="{{ route('admin.gcash.refunded', $o->id) }}" class="flex gap-2">
                                @csrf
                                <input name="refund_reference" maxlength="13" inputmode="numeric" pattern="\d{13}" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 13)" class="input w-36 py-1 font-mono text-xs" placeholder="Refund Ref No.">
                                <button class="whitespace-nowrap rounded-lg px-3 py-1 text-xs font-semibold text-white" style="background:#007DFE;">Mark refunded</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

</div>

<div role="tabpanel" id="gc-panel-history" aria-labelledby="gc-tab-history" data-gcash-panel="history">
{{-- ── Recently checked ── --}}
@if ($recent->isNotEmpty())
    <h2 class="mb-3 font-display text-lg font-bold" style="color:#222;">Last 14 days</h2>
    <div class="card overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="text-left text-xs" style="color:#6b90aa;"><th class="px-4 py-2">Order</th><th class="px-4 py-2">Buyer</th><th class="px-4 py-2">Ref No.</th><th class="px-4 py-2 text-right">Total</th><th class="px-4 py-2">Payment</th></tr></thead>
            <tbody>
                @foreach ($recent as $o)
                    <tr class="border-t" style="border-color:#F0E4DC;">
                        <td class="px-4 py-2"><a href="{{ route('admin.orders.show', $o->id) }}" class="font-semibold hover:underline" style="color:#fa4e1c;">{{ $o->number() }}</a></td>
                        <td class="px-4 py-2">{{ $o->user->name ?? $o->full_name }}</td>
                        <td class="px-4 py-2 font-mono text-xs">{{ $o->payment_reference ?: '—' }}</td>
                        <td class="px-4 py-2 text-right">{{ $peso($o->payment_expected ?? $o->total_price) }}</td>
                        <td class="px-4 py-2 text-xs font-semibold">{{ $o->payment_status }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@if ($recent->isEmpty())
    <h2 class="mb-3 font-display text-lg font-bold" style="color:#222;">Last 14 days</h2>
    <div class="card p-6 text-center text-sm" style="color:#6b90aa;">No GCash payments were checked in the last 14 days.</div>
@endif
</div>

<div role="tabpanel" id="gc-panel-account" aria-labelledby="gc-tab-account" data-gcash-panel="account">
{{-- ── ALVY's GCash ── --}}
<h2 class="mb-3 font-display text-lg font-bold" style="color:#222;">ALVY's GCash account</h2>
<div class="card mb-8 p-5" data-gcash-account>
    <form method="POST" action="{{ route('admin.gcash.account') }}" enctype="multipart/form-data" class="flex flex-col gap-5 md:flex-row">
        @csrf
        <div class="shrink-0">
            @if ($account['qr_url'])
                <img src="{{ $account['qr_url'] }}" alt="ALVY GCash QR" class="h-40 w-40 rounded-xl border bg-white object-contain" style="border-color:#DBEAFE;">
            @else
                <div class="flex h-40 w-40 items-center justify-center rounded-xl border p-3 text-center text-xs" style="border-color:#FECACA;color:#B91C1C;">No QR yet: GCash is hidden at checkout</div>
            @endif
        </div>
        <div class="grid flex-1 gap-3 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-semibold" style="color:#57534E;">GCash QR image {{ $account['qr_url'] ? '(upload to replace)' : '*' }}</label>
                <input type="file" name="qr" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold" style="color:#57534E;">Account name *</label>
                <input name="name" value="{{ old('name', $account['name']) }}" maxlength="60" required class="input w-full text-sm" placeholder="e.g. ALVY Trading">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold" style="color:#57534E;">GCash number</label>
                <input name="number" value="{{ old('number', $account['number']) }}" maxlength="11" inputmode="numeric" class="input w-full text-sm" placeholder="09XXXXXXXXX">
            </div>
            <div class="sm:col-span-2">
                <button class="rounded-lg px-4 py-2 text-sm font-semibold text-white" style="background:#fa4e1c;">Save</button>
            </div>
        </div>
    </form>
</div>



</div>

<script>
(function () {
    // Without this script every section simply shows, one under the other.
    var bar = document.querySelector('[data-gcash-tabs]');
    var tabs = Array.prototype.slice.call(bar.querySelectorAll('[data-tab]'));
    var key = 'admin-gcash-tab';
    function show(name, focus) {
        if (!document.querySelector('[data-gcash-panel="' + name + '"]')) name = 'check';
        tabs.forEach(function (t) {
            var on = t.dataset.tab === name;
            t.setAttribute('aria-selected', on ? 'true' : 'false');
            t.tabIndex = on ? 0 : -1;
            if (on && focus) t.focus();
        });
        document.querySelectorAll('[data-gcash-panel]').forEach(function (p) { p.hidden = p.dataset.gcashPanel !== name; });
        try { sessionStorage.setItem(key, name); } catch (e) {}
        if (history.replaceState) history.replaceState(null, '', '#' + name);
    }
    bar.addEventListener('click', function (e) {
        var t = e.target.closest('[data-tab]');
        if (t) show(t.dataset.tab);
    });
    bar.addEventListener('keydown', function (e) {
        var i = tabs.indexOf(document.activeElement);
        if (i < 0 || (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft')) return;
        e.preventDefault();
        show(tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length].dataset.tab, true);
    });
    // A link to another tab of this page (#refunds) only changes the address, so follow it here.
    window.addEventListener('hashchange', function () { show(location.hash.slice(1)); });
    // After approving, refunding or saving, the page comes back to the tab that was open.
    var stored = null;
    try { stored = sessionStorage.getItem(key); } catch (e) {}
    show(bar.dataset.errorTab || location.hash.slice(1) || stored || 'check');
})();
</script>
</x-admin-layout>
