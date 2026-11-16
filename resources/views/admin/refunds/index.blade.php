<x-admin-layout title="Refunds" active="refunds">
@php
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
    $tz = config('app.business_timezone', 'Asia/Manila');
@endphp

<div class="mb-5">
    <p class="text-sm" style="color:#6b90aa;">
        Money for approved returns. A refund is sent <strong>as soon as the item is back with the seller</strong>, to the account the
        buyer gave. Send it from ALVY's GCash or bank, then type the transfer's reference number so the buyer sees it. If the details
        don't work (the transfer bounced, or the name doesn't match the buyer), send them back to the buyer to fix.
    </p>
</div>

<style>
    .rf-tabs { display: flex; gap: 1.5rem; margin-bottom: 1.25rem; border-bottom: 1px solid #E5EDF3; }
    .rf-tab { display: inline-flex; align-items: center; gap: .45rem; margin-bottom: -1px; padding: .7rem .125rem; border-bottom: 2px solid transparent; font-size: .9rem; font-weight: 700; color: #6b90aa; }
    .rf-tab:hover { color: #222; }
    .rf-tab[aria-selected="true"] { border-color: #fa4e1c; color: #222; }
    .rf-count { min-width: 1.35rem; padding: .05rem .45rem; border-radius: 9999px; background: #EEF3F7; font-size: .72rem; font-weight: 700; text-align: center; color: #47657c; }
    .rf-count.is-due { background: #DC2626; color: #fff; }
    .rf-row td { padding: .85rem 1rem; border-top: 1px solid #EEF3F7; font-size: .85rem; vertical-align: top; }
    .rf-pill { display: inline-block; padding: .1rem .55rem; border-radius: 9999px; font-size: .72rem; font-weight: 700; }
    .rf-acct { font-family: ui-monospace, monospace; font-size: .95rem; font-weight: 700; color: #222; letter-spacing: .02em; }
    .rf-sub { font-size: .75rem; color: #6b90aa; }
</style>

<nav class="rf-tabs" role="tablist">
    <a href="{{ route('admin.refunds.index') }}" class="rf-tab" role="tab" aria-selected="{{ $tab === 'send' ? 'true' : 'false' }}">
        To send now <span class="rf-count {{ $counts['send'] ? 'is-due' : '' }}">{{ $counts['send'] }}</span></a>
    <a href="{{ route('admin.refunds.index', ['tab' => 'waiting']) }}" class="rf-tab" role="tab" aria-selected="{{ $tab === 'waiting' ? 'true' : 'false' }}">
        Waiting <span class="rf-count">{{ $counts['waiting'] }}</span></a>
    <a href="{{ route('admin.refunds.index', ['tab' => 'sent']) }}" class="rf-tab" role="tab" aria-selected="{{ $tab === 'sent' ? 'true' : 'false' }}">
        Sent <span class="rf-count">{{ $counts['sent'] }}</span></a>
</nav>

<div class="card overflow-x-auto">
    <table class="w-full" data-refunds="{{ $tab }}">
        <thead>
            <tr class="text-left text-xs uppercase" style="color:#6b90aa;letter-spacing:.04em;">
                <th class="px-4 py-3">Order</th><th class="px-4 py-3">Buyer</th><th class="px-4 py-3">Amount</th>
                <th class="px-4 py-3">Send to</th><th class="px-4 py-3">{{ $tab === 'sent' ? 'Sent' : 'Status' }}</th><th class="px-4 py-3" style="min-width:260px;"></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $r)
                <tr class="rf-row" data-refund="{{ $r->id }}">
                    <td>
                        <strong>{{ $r->order?->number() }}</strong>
                        @if ($tab === 'send')<span class="rf-pill ml-1" style="background:#FEE2E2;color:#B91C1C;">URGENT</span>@endif
                        <div class="rf-sub">{{ $r->order?->seller?->storeName() }} · {{ $r->order?->paymentMethodLabel() }}</div>
                    </td>
                    <td>{{ $r->user?->name }}<div class="rf-sub">{{ $r->user?->contact_no ?: $r->user?->email }}</div></td>
                    <td><strong>{{ $peso($r->amount) }}</strong></td>
                    <td>
                        @if ($r->method && $r->method !== 'original')
                            <div class="rf-sub">{{ $r->method === 'bank' ? $r->bank_name : \App\Models\Refund::METHODS[$r->method] }}</div>
                            <div class="rf-acct">{{ $tab === 'sent' ? $r->destination() : $r->account_number }}</div>
                            <div class="text-xs">{{ $r->account_name }}
                                @if (mb_strtolower(trim($r->account_name)) !== mb_strtolower(trim((string) $r->user?->name)))
                                    <span class="rf-pill" style="background:#FFFBEB;color:#B45309;" title="The account name is not the buyer's name. Check it before sending.">not the buyer's name</span>
                                @endif
                            </div>
                        @elseif ($r->method === 'original')
                            <span class="rf-sub">{{ $r->destination() }}</span>
                        @else
                            <span class="rf-sub">The buyer hasn't given their details yet.</span>
                        @endif
                    </td>
                    <td>
                        @if ($tab === 'sent')
                            {{ $r->sent_at?->tz($tz)->format('M j, g:i A') }}
                            <div class="rf-sub">{{ $r->sender?->name ?? 'Payment gateway' }}{{ $r->reference ? ' · Ref ' . $r->reference : '' }}</div>
                        @elseif ($tab === 'send')
                            Item back {{ $r->item_back_at?->tz($tz)->format('M j, g:i A') }}
                            <div class="rf-sub">ready {{ $r->ready_at?->diffForHumans() }}</div>
                        @else
                            {{ $r->statusLabel() }}
                            @if ($r->admin_note)<div class="text-xs" style="color:#B91C1C;">{{ $r->admin_note }}</div>@endif
                        @endif
                    </td>
                    <td>
                        @if ($tab === 'send')
                            @if ($r->admin_note)<p class="mb-2 text-xs" style="color:#B91C1C;">{{ $r->admin_note }}</p>@endif
                            @if ($r->method !== 'original')
                                <form method="POST" action="{{ route('admin.refunds.sent', $r) }}" class="flex gap-2">
                                    @csrf
                                    @php $gcashRef = $r->method === 'gcash'; @endphp
                                    <input name="reference" required inputmode="{{ $gcashRef ? 'numeric' : 'text' }}" minlength="{{ $gcashRef ? 13 : 6 }}" maxlength="{{ $gcashRef ? 13 : 20 }}"
                                           class="input font-mono text-sm" style="min-width:0;flex:1;" autocomplete="off"
                                           placeholder="{{ $gcashRef ? 'GCash Ref No. (13 numbers)' : 'Reference no. (6 to 20)' }}"
                                           title="{{ $gcashRef ? 'The 13 numbers of the GCash Ref No.' : '6 to 20 letters or numbers' }}"
                                           oninput="this.value = this.value{{ $gcashRef ? ".replace(/\\D/g, '')" : ".replace(/[^A-Za-z0-9]/g, '').toUpperCase()" }}.slice(0, this.maxLength)">
                                    <button class="rounded-md px-3 py-1.5 text-sm font-semibold text-white" style="background:#16A34A;">Mark sent</button>
                                </form>
                            @endif
                            <details class="mt-2 text-xs">
                                <summary class="cursor-pointer font-semibold" style="color:#6b90aa;">Details don't work? Send back to the buyer</summary>
                                <form method="POST" action="{{ route('admin.refunds.send-back', $r) }}" class="mt-2 flex gap-2">
                                    @csrf
                                    <input name="reason" required minlength="10" maxlength="200" class="input text-sm" placeholder="e.g. The account name doesn't match yours." style="min-width:0;flex:1;">
                                    <button class="rounded-md border px-3 py-1.5 text-sm font-semibold">Send back</button>
                                </form>
                            </details>
                        @elseif ($tab === 'waiting' && $r->status === 'waiting_for_item')
                            <span class="rf-sub">Sent as soon as the item is back with the seller.</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-10 text-center text-sm" style="color:#6b90aa;">{{ match ($tab) {
                    'send' => 'Nothing to send right now. A refund shows up here the moment a returned item is back with the seller.',
                    'waiting' => 'No refunds waiting.', default => 'No refunds sent yet.' } }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
</x-admin-layout>
