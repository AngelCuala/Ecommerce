<x-admin-layout title="Hub Remittances" active="hub-remittances">
@php
    $peso = fn ($v) => ((float) $v < 0 ? '−' : '') . '₱' . number_format(abs((float) $v), 2);
    $tz = config('app.business_timezone');
@endphp

<div class="mb-5">
    <p class="text-sm" style="color:#6b90aa;">
        Each logistics company sends ALVY the item price of the COD orders its riders delivered, with a report of every order (they keep the shipping
        fee; for prepaid orders ALVY owes them the shipping fee, taken off the total). Open a report, check the deposit in ALVY's bank and enter what
        arrived: it is confirmed when the amount matches and every order matches, otherwise it is flagged.
    </p>
</div>

@if (session('success'))
    <div class="mb-5 rounded-xl border p-3 text-sm" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">✓ {{ session('success') }}</div>
@endif
@if (session('error'))
    <div class="mb-5 rounded-xl border p-3 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.25);color:#DC2626;">{{ session('error') }}</div>
@endif

@foreach ([['To check', $waiting], ['Checked', $done]] as [$title, $list])
    <h2 class="mb-3 font-display text-lg font-bold" style="color:#222;">{{ $title }} <span class="text-sm font-semibold" style="color:#6b90aa;">({{ $list instanceof \Illuminate\Pagination\AbstractPaginator ? $list->total() : $list->count() }})</span></h2>
    <div class="mb-8 overflow-x-auto rounded-xl border bg-white" style="border-color:#E5EDF3;">
        <table class="w-full text-sm">
            <thead><tr style="background:#F6F9FB;color:#6b90aa;" class="text-left text-xs uppercase">
                <th class="px-4 py-3">Reference</th><th class="px-4 py-3">Company</th><th class="px-4 py-3">Sent</th><th class="px-4 py-3">Orders</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Status</th></tr></thead>
            <tbody>
            @forelse ($list as $r)
                <tr class="border-t" style="border-color:#EEF3F7;" data-hub-remittance="{{ $r->id }}">
                    <td class="px-4 py-3"><a href="{{ route('admin.hub-remittances.show', $r) }}" class="font-bold" style="color:#fa4e1c;">{{ $r->reference() }}</a></td>
                    <td class="px-4 py-3">{{ $r->sortingCenter ? \App\Support\SortingCenters::displayName($r->sortingCenter) : '—' }}</td>
                    <td class="px-4 py-3 whitespace-nowrap">{{ $r->created_at->copy()->tz($tz)->format('M j, g:i A') }}</td>
                    <td class="px-4 py-3">{{ $r->items_count }}</td>
                    <td class="px-4 py-3 font-bold">{{ $r->amount >= 0 ? $peso($r->amount) : 'ALVY owes ' . $peso(-$r->amount) }}</td>
                    <td class="px-4 py-3">{{ $r->statusLabel() }}@if ($r->note)<div class="text-xs" style="color:#DC2626;">{{ $r->note }}</div>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center" style="color:#6b90aa;">Nothing here.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endforeach
@if ($done->hasPages())<div class="mt-3">{{ $done->links() }}</div>@endif
</x-admin-layout>
