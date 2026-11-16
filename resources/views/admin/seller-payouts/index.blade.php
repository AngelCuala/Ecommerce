<x-admin-layout title="Seller Payouts" active="seller-payouts">
@php
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
    $tz = config('app.business_timezone');
@endphp

<div class="mb-5">
    <p class="text-sm" style="color:#6b90aa;">
        Sellers withdraw from their wallet (any amount of their available balance, no fee). Send each one from ALVY's bank to the account shown
        (InstaPay, PESONet, GCash or Maya), then mark it <strong>Completed</strong> with the bank reference. If the transfer failed, mark it
        <strong>Failed</strong>: the amount goes back to the seller's available balance and they are asked to check their account details.
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

{{-- Reconciliation: what ALVY owes sellers should equal what it keeps aside in its bank for them. --}}
<div class="mb-6 grid gap-3 sm:grid-cols-3" data-owed>
    @foreach ([['Sellers can withdraw', $owed['available']], ['On hold (return window)', $owed['on_hold']], ['Processing', $owed['processing']]] as [$label, $v])
        <div class="rounded-xl border bg-white p-4" style="border-color:#E5EDF3;">
            <p class="text-xs font-semibold uppercase" style="color:#6b90aa;">{{ $label }}</p>
            <p class="mt-1 font-display text-xl font-bold" style="color:#222;">{{ $peso($v) }}</p>
        </div>
    @endforeach
</div>

<h2 class="mb-3 font-display text-lg font-bold" style="color:#222;">To send <span class="text-sm font-semibold" style="color:#6b90aa;">({{ $processing->count() }})</span></h2>
<div class="mb-8 overflow-x-auto rounded-xl border bg-white" style="border-color:#E5EDF3;">
    <table class="w-full text-sm">
        <thead><tr style="background:#F6F9FB;color:#6b90aa;" class="text-left text-xs uppercase">
            <th class="px-4 py-3">Requested</th><th class="px-4 py-3">Seller</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Send to</th><th class="px-4 py-3">Action</th></tr></thead>
        <tbody>
        @forelse ($processing as $p)
            <tr class="border-t" style="border-color:#EEF3F7;" data-payout="{{ $p->id }}">
                <td class="px-4 py-3 whitespace-nowrap">{{ $p->created_at->copy()->tz($tz)->format('M j, g:i A') }}</td>
                <td class="px-4 py-3"><strong>{{ $p->seller?->storeName() }}</strong><div class="text-xs" style="color:#6b90aa;">{{ $p->seller?->email }}</div></td>
                <td class="px-4 py-3 font-bold">{{ $peso($p->amount) }}</td>
                <td class="px-4 py-3">@if ($p->destination()){{ $p->method === 'bank' ? ($p->bank_name ?: 'Bank') : (\App\Models\PayoutAccount::METHODS[$p->method] ?? '') }}
                        <strong class="block font-mono">{{ $p->account_number }}</strong><span class="text-xs">{{ $p->account_name }}</span>
                    @else<span style="color:#B91C1C;">No account given (older request): ask the seller, or mark it failed.</span>@endif</td>
                <td class="px-4 py-3" style="min-width:250px;">
                    <form method="POST" action="{{ route('admin.seller-payouts.paid', $p) }}" class="mb-2 flex flex-wrap gap-2">@csrf
                        <input name="reference" required minlength="6" maxlength="60" placeholder="Bank reference no." class="flex-1 rounded-lg border px-2 py-1.5" style="border-color:#D5E1EA;min-width:150px;" aria-label="Bank reference number">
                        <button class="rounded-lg px-3 py-1.5 text-xs font-bold text-white" style="background:#16a34a;">Completed</button>
                    </form>
                    <details><summary class="cursor-pointer text-xs" style="color:#B91C1C;">Transfer failed…</summary>
                        <form method="POST" action="{{ route('admin.seller-payouts.failed', $p) }}" class="mt-2 flex flex-wrap gap-2">@csrf
                            <input name="reason" required minlength="5" maxlength="300" placeholder="Why (e.g. account number not found)" class="flex-1 rounded-lg border px-2 py-1.5" style="border-color:#D5E1EA;min-width:150px;" aria-label="Reason">
                            <button class="rounded-lg px-3 py-1.5 text-xs font-bold text-white" style="background:#DC2626;">Failed: back to balance</button>
                        </form>
                    </details>
                </td>
            </tr>
        @empty
            <tr><td colspan="5" class="px-4 py-8 text-center" style="color:#6b90aa;">No withdrawals to send.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<h2 class="mb-3 font-display text-lg font-bold" style="color:#222;">Done</h2>
<div class="overflow-x-auto rounded-xl border bg-white" style="border-color:#E5EDF3;">
    <table class="w-full text-sm">
        <thead><tr style="background:#F6F9FB;color:#6b90aa;" class="text-left text-xs uppercase">
            <th class="px-4 py-3">When</th><th class="px-4 py-3">Seller</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">To</th><th class="px-4 py-3">Result</th><th class="px-4 py-3">By</th></tr></thead>
        <tbody>
        @forelse ($done as $p)
            <tr class="border-t" style="border-color:#EEF3F7;">
                <td class="px-4 py-3 whitespace-nowrap">{{ ($p->processed_at ?? $p->updated_at)->copy()->tz($tz)->format('M j, g:i A') }}</td>
                <td class="px-4 py-3">{{ $p->seller?->storeName() }}</td>
                <td class="px-4 py-3">{{ $peso($p->amount) }}</td>
                <td class="px-4 py-3">{{ $p->destination() ?? '—' }}</td>
                <td class="px-4 py-3">@if ($p->status === 'paid')<span style="color:#16a34a;font-weight:700;">Completed</span> · ref {{ $p->reference }}@else<span style="color:#DC2626;font-weight:700;">Failed</span>: {{ $p->reject_reason }}@endif</td>
                <td class="px-4 py-3">{{ $p->processor?->name ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="px-4 py-8 text-center" style="color:#6b90aa;">Nothing yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $done->links() }}</div>
</x-admin-layout>
