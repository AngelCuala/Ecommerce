<x-admin-layout title="Hub Remittance {{ $r->reference() }}" active="hub-remittances">
@php
    $peso = fn ($v) => ((float) $v < 0 ? '−' : '') . '₱' . number_format(abs((float) $v), 2);
    $tz = config('app.business_timezone');
@endphp

<a href="{{ route('admin.hub-remittances.index') }}" class="text-sm" style="color:#6b90aa;">← Hub Remittances</a>
<div class="mb-5 mt-2">
    <p class="text-sm" style="color:#47657c;">
        <strong>{{ $r->sortingCenter ? \App\Support\SortingCenters::displayName($r->sortingCenter) : '—' }}</strong> · sent {{ $r->created_at->copy()->tz($tz)->format('M j, Y g:i A') }}@if ($r->submitter) by {{ $r->submitter->name }}@endif
        · {{ $r->amount >= 0 ? 'to ALVY ' . $peso($r->amount) : 'ALVY owes ' . $peso(-$r->amount) }}@if ($r->deposit_reference) · deposit ref <strong>{{ $r->deposit_reference }}</strong>@endif
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

@if ($r->status === 'submitted')
    <form method="POST" action="{{ route('admin.hub-remittances.review', $r) }}" class="mb-6 flex flex-wrap items-center gap-3 rounded-xl border bg-white p-4" style="border-color:#E5EDF3;" data-review>
        @csrf
        <label class="text-sm font-semibold">{{ $r->amount >= 0 ? 'Amount ALVY received' : 'Amount ALVY paid the hub (negative)' }}
            <input type="number" name="received" step="0.01" value="{{ number_format((float) $r->amount, 2, '.', '') }}" required class="ml-2 rounded-lg border px-2 py-1.5" style="border-color:#D5E1EA;width:150px;">
        </label>
        <button class="rounded-lg px-4 py-2 text-sm font-bold text-white" style="background:#16a34a;">Check and confirm</button>
        <span class="text-xs" style="color:#6b90aa;">A different amount, or an order marked below, flags it.</span>
    </form>
@else
    <div class="mb-6 rounded-xl border bg-white p-4 text-sm" style="border-color:#E5EDF3;">
        <strong>{{ $r->statusLabel() }}</strong> · received {{ $peso($r->received_amount) }} · checked {{ $r->confirmed_at?->copy()->tz($tz)->format('M j, g:i A') }}@if ($r->confirmer) by {{ $r->confirmer->name }}@endif
        @if ($r->note)<div class="mt-1" style="color:#DC2626;">{{ $r->note }}</div>@endif
    </div>
@endif

<div class="overflow-x-auto rounded-xl border bg-white" style="border-color:#E5EDF3;">
    <table class="w-full text-sm">
        <thead><tr style="background:#F6F9FB;color:#6b90aa;" class="text-left text-xs uppercase">
            <th class="px-4 py-3">Tracking</th><th class="px-4 py-3">Order</th><th class="px-4 py-3">Paid by</th><th class="px-4 py-3">COD expected</th><th class="px-4 py-3">COD collected</th>
            <th class="px-4 py-3">Shipping fee</th><th class="px-4 py-3">Due to ALVY</th></tr></thead>
        <tbody>
        @foreach ($r->items as $i)
            <tr class="border-t" style="border-color:#EEF3F7;{{ $i->mismatch ? 'background:#FEF2F2;' : '' }}" data-line="{{ $i->id }}">
                <td class="px-4 py-3 font-bold">{{ $i->tracking_number }}@if ($i->mismatch)<div class="text-xs" style="color:#DC2626;">COD doesn't match the order</div>@endif</td>
                <td class="px-4 py-3">@if ($i->order)<a href="{{ route('admin.orders.show', $i->order_id) }}" style="color:#fa4e1c;">{{ $i->order->number() }}</a>@else — @endif</td>
                <td class="px-4 py-3">{{ $i->cod ? 'COD' : 'Prepaid' }}</td>
                <td class="px-4 py-3">{{ $i->cod ? $peso($i->cod_expected) : '—' }}</td>
                <td class="px-4 py-3">{{ $i->cod ? $peso($i->cod_collected) : '—' }}</td>
                <td class="px-4 py-3">{{ $peso($i->shipping_fee) }}</td>
                <td class="px-4 py-3 font-bold">{{ $peso($i->amount_due) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
</x-admin-layout>
