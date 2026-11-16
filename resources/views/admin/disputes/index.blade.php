<x-admin-layout title="Return Disputes" active="disputes">
@php $peso = fn ($v) => '₱' . number_format((float) $v, 2); @endphp

<div class="mb-5">
    <p class="text-sm" style="color:#6b90aa;">
        A seller rejected a return and the buyer escalated it to ALVY. Open a case to see the buyer's photos and reason, the
        seller's rejection and the buyer's reply, then decide: <strong>refund the buyer</strong> (this overrides the seller) or
        <strong>close it without a refund</strong>. Both sides see your reason.
    </p>
</div>

@if (session('success'))
    <div class="mb-5 rounded-xl border p-3 text-sm" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">✓ {{ session('success') }}</div>
@endif

<style>
    .dp-tabs { display: flex; gap: 1.5rem; margin-bottom: 1.25rem; border-bottom: 1px solid #E5EDF3; }
    .dp-tab { display: inline-flex; align-items: center; gap: .45rem; margin-bottom: -1px; padding: .7rem .125rem; border-bottom: 2px solid transparent; font-size: .9rem; font-weight: 700; color: #6b90aa; }
    .dp-tab:hover { color: #222; }
    .dp-tab[aria-selected="true"] { border-color: #fa4e1c; color: #222; }
    .dp-count { min-width: 1.35rem; padding: .05rem .45rem; border-radius: 9999px; background: #EEF3F7; font-size: .72rem; font-weight: 700; text-align: center; color: #47657c; }
    .dp-count.is-due { background: #fa4e1c; color: #fff; }
    .dp-row td { padding: .8rem 1rem; border-top: 1px solid #EEF3F7; font-size: .85rem; vertical-align: top; }
    .dp-row:hover { background: #FFF8F5; }
    .dp-pill { display: inline-block; padding: .1rem .55rem; border-radius: 9999px; font-size: .72rem; font-weight: 700; }
</style>

<div class="flex flex-wrap items-end justify-between gap-3">
    <nav class="dp-tabs" role="tablist">
        <a href="{{ route('admin.disputes.index') }}" class="dp-tab" role="tab" aria-selected="{{ $tab === 'open' ? 'true' : 'false' }}">
            To decide <span class="dp-count {{ $counts['open'] ? 'is-due' : '' }}">{{ $counts['open'] }}</span></a>
        <a href="{{ route('admin.disputes.index', ['tab' => 'decided']) }}" class="dp-tab" role="tab" aria-selected="{{ $tab === 'decided' ? 'true' : 'false' }}">
            Decided <span class="dp-count">{{ $counts['decided'] }}</span></a>
    </nav>
    <form method="GET" class="mb-4 flex gap-2">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input name="q" value="{{ $search }}" class="input text-sm" placeholder="Buyer name or order number" style="min-width:220px;">
        <button class="rounded-md border px-3 text-sm font-semibold">Search</button>
    </form>
</div>

<div class="card overflow-x-auto">
    <table class="w-full" data-disputes>
        <thead>
            <tr class="text-left text-xs uppercase" style="color:#6b90aa;letter-spacing:.04em;">
                <th class="px-4 py-3">Order</th><th class="px-4 py-3">Buyer → Seller</th><th class="px-4 py-3">Reason</th>
                <th class="px-4 py-3">Refund asked</th><th class="px-4 py-3">{{ $tab === 'open' ? 'Escalated' : 'Decision' }}</th><th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($disputes as $d)
                <tr class="dp-row" data-dispute="{{ $d->id }}">
                    <td><strong>{{ $d->order?->number() }}</strong><div class="text-xs" style="color:#6b90aa;">{{ $d->isPartial() ? count($d->item_ids) . ' item(s) of the order' : 'Whole order' }}</div></td>
                    <td>{{ $d->user?->name }}<div class="text-xs" style="color:#6b90aa;">→ {{ $d->seller?->storeName() ?? $d->seller?->name }}</div></td>
                    <td>{{ $d->reason }}@if ($d->dispute_note)<div class="text-xs" style="color:#6b90aa;">"{{ \Illuminate\Support\Str::limit($d->dispute_note, 70) }}"</div>@endif</td>
                    <td>{{ $peso($d->refund_amount) }}</td>
                    <td>
                        @if ($tab === 'open')
                            {{ $d->escalated_at->tz(config('app.business_timezone'))->format('M j, g:i A') }}
                            <div class="text-xs" style="color:#6b90aa;">{{ $d->escalated_at->diffForHumans() }}</div>
                        @else
                            <span class="dp-pill" style="{{ $d->admin_decision === 'refund' ? 'background:#ECFDF3;color:#16A34A;' : 'background:#F3F4F6;color:#4B5563;' }}">{{ $d->admin_decision === 'refund' ? 'Refunded' : 'Closed, no refund' }}</span>
                            <div class="text-xs" style="color:#6b90aa;">{{ $d->admin_decided_at?->tz(config('app.business_timezone'))->format('M j, g:i A') }}</div>
                        @endif
                    </td>
                    <td class="text-right"><a href="{{ route('admin.disputes.show', $d) }}" class="font-semibold" style="color:#fa4e1c;">{{ $tab === 'open' ? 'Review' : 'View' }} →</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-sm" style="color:#6b90aa;">{{ $tab === 'open' ? 'No disputes waiting. When a buyer escalates a rejected return, it shows up here.' : 'No decided disputes yet.' }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $disputes->links() }}</div>
</x-admin-layout>
