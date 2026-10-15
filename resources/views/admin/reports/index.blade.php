<x-admin-layout title="Sales Reports" active="reports">

{{-- ── Date range + type filter ─────────────────────────────────── --}}
<form method="GET" class="flex flex-wrap items-end gap-3 mb-6">
    <div>
        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">From</label>
        <input type="date" name="from" value="{{ $from }}"
               class="input" style="width:155px;">
    </div>
    <div>
        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">To</label>
        <input type="date" name="to" value="{{ $to }}"
               class="input" style="width:155px;">
    </div>
    <div>
        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Report Type</label>
        <select name="type" class="input" style="width:180px;">
            <option value="sales"      @selected($type==='sales')>Sales Summary</option>
            <option value="commission" @selected($type==='commission')>Commission Report</option>
            <option value="sellers"    @selected($type==='sellers')>Sales by Seller</option>
        </select>
    </div>
    @if (request('seller') && $type === 'sellers')
        <input type="hidden" name="seller" value="{{ request('seller') }}">
    @endif
    <button class="btn-gold px-5 py-2 text-sm">Apply</button>
    <a href="{{ route('admin.reports.index', array_filter(['type' => $type, 'from' => $from, 'to' => $to, 'seller' => request('seller'), 'export' => 'pdf'])) }}"
       class="btn-outline inline-flex items-center gap-1.5 px-4 py-2 text-sm"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"/></svg> Download PDF</a>
    @if ($type === 'sellers')
        <a href="{{ route('admin.reports.index', array_filter(['type' => $type, 'from' => $from, 'to' => $to, 'seller' => request('seller'), 'export' => 'csv'])) }}" class="btn-outline inline-flex items-center px-4 py-2 text-sm">CSV</a>
        <a href="{{ route('admin.reports.index', array_filter(['type' => $type, 'from' => $from, 'to' => $to, 'seller' => request('seller'), 'export' => 'xlsx'])) }}" class="btn-outline inline-flex items-center px-4 py-2 text-sm">XLSX</a>
    @endif
</form>
<p class="-mt-4 mb-6 text-xs" style="color:#6b90aa;">Cancelled orders are not counted as sales in any report.</p>

{{-- ════════════════════════════════════════
     SALES SUMMARY
════════════════════════════════════════ --}}
@if($type === 'sales')

    {{-- Stat cards --}}
    <div class="grid gap-4 sm:grid-cols-3 mb-6">
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Total Revenue</p>
            <p class="mt-1 font-display text-2xl font-bold" style="color:#fa4e1c;">₱{{ number_format($totalRevenue, 2) }}</p>
            <p class="text-xs mt-1" style="color:#6b90aa;">{{ $from }} → {{ $to }}</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Total Orders</p>
            <p class="mt-1 font-display text-2xl font-bold" style="color:#002b4d;">{{ number_format($totalOrders) }}</p>
            <p class="text-xs mt-1" style="color:#6b90aa;">In selected period</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Items Sold</p>
            <p class="mt-1 font-display text-2xl font-bold" style="color:#002b4d;">{{ number_format($totalItems) }}</p>
            <p class="text-xs mt-1" style="color:#6b90aa;">Units across all orders</p>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2 mb-6">

        {{-- Orders by status --}}
        <div class="card p-5">
            <h2 class="font-display text-base font-semibold mb-4" style="color:#002b4d;">Orders by Status</h2>
            <table class="w-full text-sm">
                <thead>
                    <tr style="border-bottom:1px solid #cfdce8;">
                        <th class="pb-2 text-left text-xs font-semibold" style="color:#6b90aa;">Status</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Orders</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($byStatus as $row)
                    @php
                        $color = match(strtolower($row->status)) {
                            'delivered'  => '#059669',
                            'shipped'    => '#2563EB',
                            'processing' => '#fa4e1c',
                            'cancelled'  => '#DC2626',
                            default      => '#B45309',
                        };
                    @endphp
                    <tr style="border-bottom:1px solid #F5F0EB;">
                        <td class="py-2">
                            <span class="inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                  style="background:{{ $color }}1a;color:{{ $color }};">
                                {{ $row->status }}
                            </span>
                        </td>
                        <td class="py-2 text-right font-semibold" style="color:#002b4d;">{{ $row->count }}</td>
                        <td class="py-2 text-right font-semibold" style="color:#fa4e1c;">₱{{ number_format($row->total, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-6 text-center text-sm" style="color:#6b90aa;">No orders in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Daily sales chart --}}
        <div class="card p-5">
            <h2 class="font-display text-base font-semibold mb-4" style="color:#002b4d;">Daily Sales</h2>
            @if($dailySales->isEmpty())
                <div class="flex items-center justify-center h-32 text-sm" style="color:#6b90aa;">No sales data.</div>
            @else
                @php $maxSale = $dailySales->max('total') ?: 1; @endphp
                <div class="flex items-end gap-1.5" style="height:120px;">
                    @foreach($dailySales as $day)
                        @php $h = max(4, round(($day->total / $maxSale) * 100)); @endphp
                        <div class="flex flex-1 flex-col items-center gap-1 h-full justify-end">
                            <div class="w-full rounded-t"
                                 style="height:{{ $h }}%;background:#fa4e1c;opacity:.85;min-height:4px;"
                                 title="{{ $day->day }}: ₱{{ number_format($day->total, 2) }}"></div>
                            <span class="text-[9px]" style="color:#6b90aa;white-space:nowrap;">{{ $day->day }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="mt-3 text-xs text-right" style="color:#6b90aa;">
                    Peak: ₱{{ number_format($dailySales->max('total'), 2) }}
                </div>
            @endif
        </div>
    </div>

    {{-- Top products --}}
    <div class="card p-5">
        <h2 class="font-display text-base font-semibold mb-4" style="color:#002b4d;">Top Products by Revenue</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="border-bottom:1px solid #cfdce8;">
                        <th class="pb-2 text-left text-xs font-semibold" style="color:#6b90aa;">#</th>
                        <th class="pb-2 text-left text-xs font-semibold" style="color:#6b90aa;">Book</th>
                        <th class="pb-2 text-left text-xs font-semibold" style="color:#6b90aa;">Seller</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Units</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Revenue</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($topProducts as $i => $item)
                    <tr style="border-bottom:1px solid #F5F0EB;">
                        <td class="py-2.5 text-xs" style="color:#6b90aa;">{{ $i + 1 }}</td>
                        <td class="py-2.5 font-medium" style="color:#002b4d;">
                            {{ $item->product->title ?? '—' }}
                        </td>
                        <td class="py-2.5 text-xs" style="color:#6b90aa;">
                            {{ $item->product->seller->name ?? '—' }}
                        </td>
                        <td class="py-2.5 text-right" style="color:#002b4d;">{{ $item->units_sold }}</td>
                        <td class="py-2.5 text-right font-semibold" style="color:#fa4e1c;">
                            ₱{{ number_format($item->revenue, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-sm" style="color:#6b90aa;">No product sales in this period.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

{{-- ════════════════════════════════════════
     COMMISSION REPORT
════════════════════════════════════════ --}}
@elseif($type === 'commission')

    {{-- Stat cards --}}
    <div class="grid gap-4 sm:grid-cols-3 mb-6">
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Total Commission</p>
            <p class="mt-1 font-display text-2xl font-bold" style="color:#fa4e1c;">₱{{ number_format($totalCommission, 2) }}</p>
            <p class="text-xs mt-1" style="color:#6b90aa;">{{ $commissionRate }}% platform rate</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Total Seller Payout</p>
            <p class="mt-1 font-display text-2xl font-bold" style="color:#002b4d;">₱{{ number_format($totalPayout, 2) }}</p>
            <p class="text-xs mt-1" style="color:#6b90aa;">After commission deducted</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Commission Rate</p>
            <p class="mt-1 font-display text-2xl font-bold" style="color:#002b4d;">{{ $commissionRate }}%</p>
            <p class="text-xs mt-1" style="color:#6b90aa;">Current platform rate</p>
        </div>
    </div>

    {{-- Daily commission chart --}}
    @if($dailyCommission->isNotEmpty())
    <div class="card p-5 mb-6">
        <h2 class="font-display text-base font-semibold mb-4" style="color:#002b4d;">Daily Commission</h2>
        @php $maxC = $dailyCommission->max('commission') ?: 1; @endphp
        <div class="flex items-end gap-1.5" style="height:100px;">
            @foreach($dailyCommission as $day)
                @php $h = max(4, round(($day->commission / $maxC) * 100)); @endphp
                <div class="flex flex-1 flex-col items-center gap-1 h-full justify-end">
                    <div class="w-full rounded-t"
                         style="height:{{ $h }}%;background:#fa4e1c;opacity:.85;min-height:4px;"
                         title="{{ $day->day }}: ₱{{ number_format($day->commission, 2) }}"></div>
                    <span class="text-[9px]" style="color:#6b90aa;white-space:nowrap;">{{ $day->day }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Per-seller commission table --}}
    <div class="card p-5">
        <h2 class="font-display text-base font-semibold mb-4" style="color:#002b4d;">Commission by Seller</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="border-bottom:1px solid #cfdce8;">
                        <th class="pb-2 text-left text-xs font-semibold" style="color:#6b90aa;">#</th>
                        <th class="pb-2 text-left text-xs font-semibold" style="color:#6b90aa;">Seller</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Units</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Gross Sales</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Commission</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Seller Payout</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($bySellerCommission as $i => $row)
                    <tr style="border-bottom:1px solid #F5F0EB;">
                        <td class="py-2.5 text-xs" style="color:#6b90aa;">{{ $i + 1 }}</td>
                        <td class="py-2.5 font-medium" style="color:#002b4d;">{{ $row['name'] }}</td>
                        <td class="py-2.5 text-right" style="color:#002b4d;">{{ $row['units'] }}</td>
                        <td class="py-2.5 text-right" style="color:#002b4d;">₱{{ number_format($row['gross'], 2) }}</td>
                        <td class="py-2.5 text-right font-semibold" style="color:#fa4e1c;">₱{{ number_format($row['commission'], 2) }}</td>
                        <td class="py-2.5 text-right font-semibold" style="color:#059669;">₱{{ number_format($row['payout'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-sm" style="color:#6b90aa;">No commission data in this period.</td></tr>
                @endforelse
                </tbody>
                @if($bySellerCommission->isNotEmpty())
                <tfoot style="border-top:2px solid #cfdce8;">
                    <tr>
                        <td colspan="4" class="pt-3 text-xs font-bold" style="color:#002b4d;">Total</td>
                        <td class="pt-3 text-right font-bold" style="color:#fa4e1c;">₱{{ number_format($totalCommission, 2) }}</td>
                        <td class="pt-3 text-right font-bold" style="color:#059669;">₱{{ number_format($totalPayout, 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

@else
    @include('admin.reports._sellers')

@endif

{{-- Every seller, with a "View sales" link to their own report (below the report) --}}
@if ($type !== 'sellers' && isset($sellerBox))
    <div class="mt-6">
        @include('admin.reports._seller_box')
    </div>
@endif

</x-admin-layout>
