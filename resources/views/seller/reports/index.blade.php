<x-seller-layout title="Sales Reports" active="reports">
<div>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <span class="section-eyebrow" style="color:#fa4e1c;">Seller Panel</span>
            <h1 class="mt-1 font-display text-2xl font-bold" style="color:#222222;">Sales Reports</h1>
        </div>
    </div>

    {{-- Date filter --}}
    <form method="GET" class="card mb-6 flex flex-wrap items-end gap-4 p-5">
        <div>
            <label class="text-xs font-semibold" style="color:#6b90aa;">From</label>
            <input type="date" name="from" value="{{ $from }}" class="input mt-1" style="border-color:#FFDCC2;">
        </div>
        <div>
            <label class="text-xs font-semibold" style="color:#6b90aa;">To</label>
            <input type="date" name="to" value="{{ $to }}" class="input mt-1" style="border-color:#FFDCC2;">
        </div>
        <button type="submit" class="rounded-xl px-6 py-2.5 text-sm font-bold text-white transition hover:opacity-90"
                style="background:#002b4d;">Generate Report</button>
        <a href="{{ route('seller.reports') }}" class="rounded-xl border px-5 py-2.5 text-sm font-semibold transition"
           style="border-color:#FFDCC2;color:#7A7A7A;">This Month</a>
    </form>

    {{-- Summary cards --}}
    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        <div class="card p-5 text-center">
            <p class="text-xs font-semibold uppercase tracking-widest" style="color:#6b90aa;">Gross Revenue</p>
            <p class="mt-2 font-display text-2xl font-extrabold" style="color:#fa4e1c;">
                ₱{{ number_format($totalRevenue, 2) }}
            </p>
            <p class="text-xs mt-1" style="color:#6b90aa;">Before {{ $commissionRate }}% commission</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-xs font-semibold uppercase tracking-widest" style="color:#6b90aa;">Commission Paid</p>
            <p class="mt-2 font-display text-2xl font-extrabold" style="color:#EF4444;">
                -₱{{ number_format($totalCommission, 2) }}
            </p>
            <p class="text-xs mt-1" style="color:#6b90aa;">Platform fee ({{ $commissionRate }}%)</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-xs font-semibold uppercase tracking-widest" style="color:#6b90aa;">Net Earnings</p>
            <p class="mt-2 font-display text-2xl font-extrabold" style="color:#059669;">
                ₱{{ number_format($totalEarnings, 2) }}
            </p>
            <p class="text-xs mt-1" style="color:#6b90aa;">Your take-home</p>
        </div>
        <div class="card p-5 text-center">
            <p class="text-xs font-semibold uppercase tracking-widest" style="color:#6b90aa;">Total Orders</p>
            <p class="mt-2 font-display text-2xl font-extrabold" style="color:#222222;">{{ $totalOrders }}</p>
            <p class="text-xs mt-1" style="color:#6b90aa;">In selected period</p>
        </div>
    </div>

    {{-- Sales trend bar chart --}}
    @if ($byDate->count())
    <div class="card p-6 mb-8">
        <h2 class="font-display text-base font-bold mb-4" style="color:#222222;">Daily Earnings Trend</h2>
        @php $maxVal = $byDate->max() ?: 1; @endphp
        <div class="flex h-32 items-end gap-1 overflow-x-auto pb-1">
            @foreach ($byDate as $date => $amount)
                @php $h = max(4, ($amount / $maxVal) * 112); @endphp
                <div class="group flex flex-1 min-w-[28px] flex-col items-center gap-1">
                    <span class="hidden rounded px-1 py-0.5 text-[9px] font-bold group-hover:block"
                          style="background:#222222;color:#fff;">₱{{ number_format($amount,0) }}</span>
                    <div class="w-full rounded-t transition" style="height:{{ $h }}px;background:#fa4e1c;opacity:.85;"></div>
                    <span class="text-[9px] whitespace-nowrap" style="color:#6b90aa;">{{ $date }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Product performance --}}
    <div class="card overflow-hidden">
        <div class="px-6 py-4" style="background:#e8f0f6;border-bottom:1px solid #cfdce8;">
            <h2 class="font-display text-base font-bold" style="color:#222222;">Product Performance</h2>
            <p class="text-xs mt-0.5" style="color:#6b90aa;">{{ $from }} → {{ $to }}</p>
        </div>
        @if ($byProduct->isEmpty())
            <div class="py-12 text-center">
                <p class="text-sm" style="color:#6b90aa;">No sales data for this period.</p>
            </div>
        @else
            <table class="w-full text-sm">
                <thead style="background:#FFFAF7;">
                    <tr>
                        <th class="px-5 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Product</th>
                        <th class="px-3 py-3 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Units Sold</th>
                        <th class="px-3 py-3 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Gross</th>
                        <th class="px-5 py-3 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Net Earnings</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($byProduct as $p)
                        <tr style="border-top:1px solid #fff1ee;"
                            onmouseover="this.style.background='#FFFBF7';" onmouseout="this.style.background='';">
                            <td class="px-5 py-3 font-semibold" style="color:#222222;">{{ $p['title'] }}</td>
                            <td class="px-3 py-3 text-right" style="color:#7A7A7A;">{{ $p['qty'] }}</td>
                            <td class="px-3 py-3 text-right" style="color:#fa4e1c;">₱{{ number_format($p['revenue'], 2) }}</td>
                            <td class="px-5 py-3 text-right font-bold" style="color:#059669;">₱{{ number_format($p['earnings'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot style="background:#e8f0f6;border-top:2px solid #cfdce8;">
                    <tr>
                        <td class="px-5 py-3 font-bold" style="color:#222222;">Total</td>
                        <td class="px-3 py-3 text-right font-bold" style="color:#222222;">{{ $byProduct->sum('qty') }}</td>
                        <td class="px-3 py-3 text-right font-bold" style="color:#fa4e1c;">₱{{ number_format($byProduct->sum('revenue'), 2) }}</td>
                        <td class="px-5 py-3 text-right font-bold" style="color:#059669;">₱{{ number_format($byProduct->sum('earnings'), 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>

</div>
</x-seller-layout>
