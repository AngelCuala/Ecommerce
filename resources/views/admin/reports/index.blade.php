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
        {{-- Picking a report type opens it right away (a seller's report only stays open under "Sales by Seller"). --}}
        <select name="type" class="input" style="width:210px;" data-report-type
                onchange="if (this.form.seller && this.value !== 'sellers') this.form.seller.disabled = true; this.form.submit();">
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

    @php
        $peso = fn ($v) => '₱' . number_format((float) $v, 2);
        $effective = $totalGross > 0 ? round($totalCommission / $totalGross * 100, 1) : 0;
        $topCommission = max(0.01, (float) $bySellerCommission->max('commission'));
    @endphp

    {{-- Stat cards --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 mb-6" data-commission-cards>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Gross Sales</p>
            <p class="mt-1 font-display text-2xl font-bold" style="color:#002b4d;" data-card="gross">{{ $peso($totalGross) }}</p>
            <p class="text-xs mt-1" style="color:#6b90aa;">Items sold, before commission</p>
        </div>
        <div class="card relative overflow-hidden p-5">
            <div class="absolute inset-x-0 top-0 h-1" style="background:#fa4e1c;"></div>
            <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">ALVY Commission</p>
            <p class="mt-1 font-display text-2xl font-bold" style="color:#fa4e1c;" data-card="commission">{{ $peso($totalCommission) }}</p>
            <p class="text-xs mt-1" style="color:#6b90aa;">ALVY's earnings for the period</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Seller Payout</p>
            <p class="mt-1 font-display text-2xl font-bold" style="color:#059669;" data-card="payout">{{ $peso($totalPayout) }}</p>
            <p class="text-xs mt-1" style="color:#6b90aa;">What sellers keep</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Commission Rate</p>
            <p class="mt-1 font-display text-2xl font-bold" style="color:#002b4d;">{{ $commissionRate }}%</p>
            <p class="text-xs mt-1" style="color:#6b90aa;" data-card="effective">Collected this period: {{ $effective }}% of gross</p>
        </div>
    </div>

    {{-- Gross split: commission vs payout --}}
    @if ($totalGross > 0)
        @php $commPct = round($totalCommission / $totalGross * 100, 1); @endphp
        <div class="card p-5 mb-6" data-commission-split>
            <div class="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                <h2 class="font-display text-base font-semibold" style="color:#002b4d;">Where the sales went</h2>
                <span class="text-xs" style="color:#6b90aa;">{{ $peso($totalGross) }} gross</span>
            </div>
            <div class="flex h-3 overflow-hidden rounded-full" style="background:#F1F5F9;">
                <div style="width:{{ $commPct }}%;background:#fa4e1c;" title="Commission {{ $commPct }}%"></div>
                <div style="width:{{ 100 - $commPct }}%;background:#10b981;" title="Seller payout {{ round(100 - $commPct, 1) }}%"></div>
            </div>
            <div class="mt-2 flex flex-wrap gap-4 text-xs" style="color:#57534E;">
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#fa4e1c;"></span>ALVY commission {{ $commPct }}% · {{ $peso($totalCommission) }}</span>
                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background:#10b981;"></span>Seller payout {{ round(100 - $commPct, 1) }}% · {{ $peso($totalPayout) }}</span>
            </div>
        </div>
    @endif

    {{-- Daily commission chart --}}
    @if($dailyCommission->isNotEmpty())
    <div class="card p-5 mb-6">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
            <h2 class="font-display text-base font-semibold" style="color:#002b4d;">Daily Commission</h2>
            <span class="text-xs" style="color:#6b90aa;">{{ $dailyCommission->count() }} {{ \Illuminate\Support\Str::plural('day', $dailyCommission->count()) }} with sales · best day {{ $peso($dailyCommission->max('commission')) }}</span>
        </div>
        @php $maxC = $dailyCommission->max('commission') ?: 1; @endphp
        <div class="overflow-x-auto">
            <div class="flex items-end gap-2" style="height:150px;min-width:{{ max(1, $dailyCommission->count()) * 52 }}px;">
                @foreach($dailyCommission as $day)
                    @php $h = max(4, round(($day->commission / $maxC) * 100)); @endphp
                    <div class="flex flex-1 flex-col items-center gap-1 h-full justify-end" data-day="{{ $day->day }}">
                        <span class="text-[10px] font-semibold" style="color:#002b4d;white-space:nowrap;">{{ $peso($day->commission) }}</span>
                        <div class="w-full rounded-t-md" style="max-width:36px;height:{{ $h }}%;background:linear-gradient(180deg,#fb7048,#fa4e1c);min-height:4px;"
                             title="{{ $day->day }}: commission {{ $peso($day->commission) }} · payout {{ $peso($day->payout) }}"></div>
                        <span class="text-[10px]" style="color:#6b90aa;white-space:nowrap;">{{ $day->day }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- Per-seller commission table --}}
    <div class="card p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
            <h2 class="font-display text-base font-semibold" style="color:#002b4d;">Commission by Seller</h2>
            <span class="text-xs" style="color:#6b90aa;">{{ $bySellerCommission->count() }} {{ \Illuminate\Support\Str::plural('seller', $bySellerCommission->count()) }} with sales</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="border-bottom:1px solid #cfdce8;">
                        <th class="pb-2 text-left text-xs font-semibold" style="color:#6b90aa;">#</th>
                        <th class="pb-2 text-left text-xs font-semibold" style="color:#6b90aa;">Seller</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Units</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Gross Sales</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Commission</th>
                        <th class="pb-2 pl-4 text-left text-xs font-semibold" style="color:#6b90aa;min-width:150px;">Share of commission</th>
                        <th class="pb-2 text-right text-xs font-semibold" style="color:#6b90aa;">Seller Payout</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($bySellerCommission as $i => $row)
                    @php $share = $totalCommission > 0 ? round($row['commission'] / $totalCommission * 100, 1) : 0; @endphp
                    <tr style="border-bottom:1px solid #F5F0EB;" data-commission-row>
                        <td class="py-3 text-xs" style="color:#6b90aa;">{{ $i + 1 }}</td>
                        <td class="py-3">
                            <span class="inline-flex items-center gap-2 font-medium" style="color:#002b4d;">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold" style="background:#FFF1EE;color:#fa4e1c;">{{ strtoupper(mb_substr($row['name'], 0, 1)) }}</span>
                                {{ $row['name'] }}
                            </span>
                        </td>
                        <td class="py-3 text-right" style="color:#002b4d;">{{ $row['units'] }}</td>
                        <td class="py-3 text-right" style="color:#002b4d;">{{ $peso($row['gross']) }}</td>
                        <td class="py-3 text-right font-semibold" style="color:#fa4e1c;">{{ $peso($row['commission']) }}</td>
                        <td class="py-3 pl-4">
                            <div class="flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full" style="background:#F1F5F9;">
                                    <div class="h-full rounded-full" style="width:{{ round($row['commission'] / $topCommission * 100) }}%;background:#fa4e1c;"></div>
                                </div>
                                <span class="w-12 text-right text-xs font-semibold" style="color:#57534E;" data-share>{{ $share }}%</span>
                            </div>
                        </td>
                        <td class="py-3 text-right font-semibold" style="color:#059669;">{{ $peso($row['payout']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-10 text-center text-sm" style="color:#6b90aa;">No sales, so no commission, in this period. Try a wider date range.</td></tr>
                @endforelse
                </tbody>
                @if($bySellerCommission->isNotEmpty())
                <tfoot style="border-top:2px solid #cfdce8;">
                    <tr>
                        <td colspan="3" class="pt-3 text-xs font-bold" style="color:#002b4d;">Total</td>
                        <td class="pt-3 text-right font-bold" style="color:#002b4d;">{{ $peso($totalGross) }}</td>
                        <td class="pt-3 text-right font-bold" style="color:#fa4e1c;">{{ $peso($totalCommission) }}</td>
                        <td class="pt-3 pl-4 text-xs font-semibold" style="color:#57534E;">100%</td>
                        <td class="pt-3 text-right font-bold" style="color:#059669;">{{ $peso($totalPayout) }}</td>
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
