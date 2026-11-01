<x-seller-layout title="Commission" active="commissions">
@include('partials.panel.plain')
@php
    $peso = fn ($v) => '₱' . number_format((float) $v, 2);
    $rate = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.') . '%';
    $badge = [
        'pending'   => 'background:#FFF7ED;color:#C2410C;',
        'finalized' => 'background:#ECFDF5;color:#047857;',
        'refunded'  => 'background:#FEF2F2;color:#B91C1C;',
        'reversed'  => 'background:#F5F5F4;color:#78716C;',
        'unpaid'    => 'background:#EFF6FF;color:#1D4ED8;',
    ];
    $base = $range->query();
@endphp

<div class="mx-auto max-w-6xl space-y-5">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <span class="section-eyebrow">Finance</span>
            <h1 class="text-2xl">Commission</h1>
            <p class="text-sm pl-muted">ALVY's fee on each item you sell. The rate shown is the one in force when the order was placed.
                Cancelled and unpaid orders are never charged; refunded items give the commission back.</p>
        </div>
        <a href="{{ route('seller.commissions', $base + array_filter(['status' => $status]) + ['export' => 'pdf']) }}" class="pl-btn-line inline-flex items-center gap-1.5" data-export-pdf>
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M12 18v-6M9 15l3 3 3-3"/></svg>
            Export PDF
        </a>
    </div>

    @if ($configuredRate === null || $configuredRate === '')
        <p class="rounded-lg px-4 py-2.5 text-sm" style="background:#FFFBEB;color:#92400E;">No commission rate is configured (MARKETPLACE_COMMISSION). New orders need one; ask the ALVY admin to set it.</p>
    @endif

    {{-- Period --}}
    <form method="GET" action="{{ route('seller.commissions') }}" class="pl-card flex flex-wrap items-end gap-3 px-5 py-3">
        <div>
            <label class="pl-label" for="period">Period</label>
            <select id="period" name="period" class="input !py-1.5" onchange="this.form.querySelectorAll('[data-custom]').forEach(e => e.hidden = this.value !== 'custom')">
                @foreach (\App\Support\DateRange::PERIODS as $key => $label)
                    <option value="{{ $key }}" @selected($range->period === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div data-custom @if ($range->period !== 'custom') hidden @endif>
            <label class="pl-label" for="from">From</label>
            <input type="date" id="from" name="from" value="{{ $range->from->toDateString() }}" class="input !py-1.5">
        </div>
        <div data-custom @if ($range->period !== 'custom') hidden @endif>
            <label class="pl-label" for="to">To</label>
            <input type="date" id="to" name="to" value="{{ $range->to->toDateString() }}" class="input !py-1.5">
        </div>
        <div>
            <label class="pl-label" for="status">Status</label>
            <select id="status" name="status" class="input !py-1.5">
                <option value="">All</option>
                @foreach (\App\Services\SellerCommission::STATUSES as $key => $label)
                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button class="pl-btn">Apply</button>
        <a href="{{ route('seller.commissions') }}" class="pl-btn-line">Reset</a>
        <span class="ml-auto self-center text-xs pl-muted">{{ $range->label() }}</span>
    </form>
    @if ($errors->any())
        <p class="rounded-lg px-4 py-2.5 text-sm" style="background:#FEF2F2;color:#B91C1C;">{{ $errors->first() }}</p>
    @endif

    {{-- Totals --}}
    <div class="pl-card grid grid-cols-2 lg:grid-cols-5">
        @foreach ([
            ['Gross sales', $summary['gross'], 'Pending + finalized items', 'gross'],
            ['Refunds', $summary['refunds'], 'Refunded items (no commission)', 'refunds'],
            ['Commission', $summary['commission'], $peso($summary['pending_commission']) . ' still pending', 'commission'],
            ['Finalized', $summary['finalized_commission'], 'Commission on completed orders', 'finalized'],
            ['Net earnings', $summary['net'], 'Gross minus commission', 'net'],
        ] as $i => [$label, $value, $note, $key])
            <div class="px-5 py-4 {{ $i > 0 ? 'border-l' : '' }} max-lg:border-t" style="border-color:#F2EEEA;">
                <p class="text-xs font-medium pl-muted">{{ $label }}</p>
                <p class="mt-1 text-xl font-semibold tabular-nums" style="color:#222;" data-total="{{ $key }}">{{ $peso($value) }}</p>
                <p class="mt-0.5 text-xs pl-muted">{{ $note }}</p>
            </div>
        @endforeach
    </div>

    {{-- Lines --}}
    <section class="pl-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="pl-table">
                <thead>
                    <tr>
                        <th>Order</th><th>Product</th><th class="!text-right">Qty</th><th class="!text-right">Gross</th>
                        <th class="!text-right">Rate</th><th class="!text-right">Commission</th><th class="!text-right">Net</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        @php $st = $commission->statusOf($item); $charged = in_array($st, ['pending', 'finalized'], true); @endphp
                        <tr data-line="{{ $item->id }}" data-status="{{ $st }}">
                            <td class="whitespace-nowrap">
                                <a href="{{ route('seller.orders.show', $item->order_id) }}" class="font-medium hover:underline" style="color:#222;">{{ $item->order?->number() }}</a>
                                <p class="text-xs pl-muted">{{ $item->order?->created_at?->format('M j, Y') }}</p>
                            </td>
                            <td class="max-w-[18rem]">
                                <p class="truncate">{{ $item->product->title ?? 'Removed product' }}</p>
                                @if ($item->variation_name)<p class="text-xs pl-muted">{{ $item->variation_name }}</p>@endif
                            </td>
                            <td class="text-right tabular-nums">{{ $item->quantity }}</td>
                            <td class="text-right tabular-nums">{{ $peso(\App\Services\SellerCommission::gross($item)) }}</td>
                            <td class="text-right tabular-nums">{{ $rate($item->commission_rate) }}</td>
                            <td class="text-right tabular-nums {{ $charged ? '' : 'line-through pl-muted' }}">{{ $peso($item->commission_amount) }}</td>
                            <td class="text-right tabular-nums font-medium {{ $charged ? '' : 'line-through pl-muted' }}" style="{{ $charged ? 'color:#222;' : '' }}">{{ $peso($item->seller_earning) }}</td>
                            <td><span class="pl-tag" style="{{ $badge[$st] }}">{{ \App\Services\SellerCommission::STATUSES[$st] }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-12 text-center pl-muted">No commission records for this period{{ $status ? ' and status' : '' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($items->hasPages())
            <div class="border-t px-5 py-3" style="border-color:#F2EEEA;">{{ $items->links() }}</div>
        @endif
    </section>
</div>
</x-seller-layout>
