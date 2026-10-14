{{-- Sales by Seller: every seller for the period, or one seller's detail. Cancelled orders excluded. --}}
@php
    $range  = \Carbon\Carbon::parse($from)->format('M d, Y') . ' – ' . \Carbon\Carbon::parse($to)->format('M d, Y');
    $th     = 'pb-2 text-xs font-semibold';
    $thStyle = 'color:#6b90aa;';
@endphp

@if (! $seller)
    {{-- ── All sellers ─────────────────────────────── --}}
    <div class="grid gap-4 sm:grid-cols-4 mb-6">
        @foreach ([
            ['Gross Sales',      '₱' . number_format($sellers->sum('gross'), 2),      '#fa4e1c'],
            ['Orders',           number_format($sellers->sum('orders')),             '#002b4d'],
            ['ALVY Commission',  '₱' . number_format($sellers->sum('commission'), 2), '#002b4d'],
            ['Seller Earnings',  '₱' . number_format($sellers->sum('payout'), 2),     '#059669'],
        ] as [$label, $value, $color])
            <div class="card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">{{ $label }}</p>
                <p class="mt-1 font-display text-2xl font-bold" style="color:{{ $color }};">{{ $value }}</p>
                <p class="text-xs mt-1" style="color:#6b90aa;">{{ $range }}</p>
            </div>
        @endforeach
    </div>

    <div class="card p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-display text-base font-semibold" style="color:#002b4d;">Sales by Seller · {{ $range }}</h2>
            <span class="flex gap-2">
                <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn-outline !py-1.5 px-4 text-sm">Download PDF</a>
                <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn-outline !py-1.5 px-4 text-sm">Download CSV</a>
            </span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="border-bottom:1px solid #cfdce8;">
                        <th class="{{ $th }} text-left" style="{{ $thStyle }}">#</th>
                        <th class="{{ $th }} text-left" style="{{ $thStyle }}">Seller</th>
                        <th class="{{ $th }} text-right" style="{{ $thStyle }}">Orders</th>
                        <th class="{{ $th }} text-right" style="{{ $thStyle }}">Units</th>
                        <th class="{{ $th }} text-right" style="{{ $thStyle }}">Gross Sales</th>
                        <th class="{{ $th }} text-right" style="{{ $thStyle }}">Commission</th>
                        <th class="{{ $th }} text-right" style="{{ $thStyle }}">Seller Earnings</th>
                        <th class="{{ $th }}"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($sellers as $i => $row)
                    <tr style="border-bottom:1px solid #F5F0EB;{{ $row->units ? '' : 'opacity:.55;' }}">
                        <td class="py-2.5 text-xs" style="color:#6b90aa;">{{ $i + 1 }}</td>
                        <td class="py-2.5">
                            <div class="font-medium" style="color:#002b4d;">{{ $row->name }}</div>
                            <div class="text-xs" style="color:#6b90aa;">{{ $row->shop ?: $row->email }}</div>
                        </td>
                        <td class="py-2.5 text-right">{{ $row->orders }}</td>
                        <td class="py-2.5 text-right">{{ $row->units }}</td>
                        <td class="py-2.5 text-right font-semibold" style="color:#002b4d;">₱{{ number_format($row->gross, 2) }}</td>
                        <td class="py-2.5 text-right" style="color:#fa4e1c;">₱{{ number_format($row->commission, 2) }}</td>
                        <td class="py-2.5 text-right" style="color:#059669;">₱{{ number_format($row->payout, 2) }}</td>
                        <td class="py-2.5 text-right">
                            @if ($row->id)
                                <a href="{{ route('admin.reports.index', ['type' => 'sellers', 'from' => $from, 'to' => $to, 'seller' => $row->id]) }}"
                                   class="text-xs font-semibold" style="color:#fa4e1c;">Details →</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-6 text-center text-sm" style="color:#6b90aa;">No sellers yet.</td></tr>
                @endforelse
                </tbody>
                @if ($sellers->isNotEmpty())
                    <tfoot style="border-top:2px solid #cfdce8;">
                        <tr class="font-bold" style="color:#002b4d;">
                            <td colspan="2" class="pt-3 text-xs">Total</td>
                            <td class="pt-3 text-right">{{ $sellers->sum('orders') }}</td>
                            <td class="pt-3 text-right">{{ $sellers->sum('units') }}</td>
                            <td class="pt-3 text-right">₱{{ number_format($sellers->sum('gross'), 2) }}</td>
                            <td class="pt-3 text-right" style="color:#fa4e1c;">₱{{ number_format($sellers->sum('commission'), 2) }}</td>
                            <td class="pt-3 text-right" style="color:#059669;">₱{{ number_format($sellers->sum('payout'), 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <p class="mt-3 text-xs" style="color:#6b90aa;">Based on the order date. Cancelled orders are not counted. Gross sales are after store vouchers; ALVY vouchers are paid by ALVY and do not lower seller earnings. Sellers with no sales in this period are shown faded.</p>
    </div>

@else
    {{-- ── One seller ──────────────────────────────── --}}
    <a href="{{ route('admin.reports.index', ['type' => 'sellers', 'from' => $from, 'to' => $to]) }}"
       class="mb-4 inline-block text-sm font-semibold" style="color:#fa4e1c;">← All sellers</a>

    <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="font-display text-xl font-bold" style="color:#002b4d;">{{ $seller->name }}</h2>
            <p class="text-sm" style="color:#6b90aa;">{{ $seller->shop ? $seller->shop . ' · ' : '' }}{{ $seller->email }} · {{ $range }}</p>
        </div>
        <span class="flex gap-2">
                <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn-outline !py-1.5 px-4 text-sm">Download PDF</a>
                <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="btn-outline !py-1.5 px-4 text-sm">Download CSV</a>
            </span>
    </div>

    <div class="grid gap-4 sm:grid-cols-4 mb-6">
        @foreach ([
            ['Gross Sales',     '₱' . number_format($seller->gross, 2),      '#fa4e1c'],
            ['Orders · Units',  $seller->orders . ' · ' . $seller->units,    '#002b4d'],
            ['ALVY Commission', '₱' . number_format($seller->commission, 2), '#002b4d'],
            ['Seller Earnings', '₱' . number_format($seller->payout, 2),     '#059669'],
        ] as [$label, $value, $color])
            <div class="card p-5">
                <p class="text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">{{ $label }}</p>
                <p class="mt-1 font-display text-2xl font-bold" style="color:{{ $color }};">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card p-5 lg:col-span-2">
            <h3 class="font-display text-base font-semibold mb-4" style="color:#002b4d;">Products sold</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr style="border-bottom:1px solid #cfdce8;">
                            <th class="{{ $th }} text-left" style="{{ $thStyle }}">Product</th>
                            <th class="{{ $th }} text-right" style="{{ $thStyle }}">Units</th>
                            <th class="{{ $th }} text-right" style="{{ $thStyle }}">Gross</th>
                            <th class="{{ $th }} text-right" style="{{ $thStyle }}">Commission</th>
                            <th class="{{ $th }} text-right" style="{{ $thStyle }}">Earnings</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($products as $p)
                        <tr style="border-bottom:1px solid #F5F0EB;">
                            <td class="py-2.5">
                                <div class="font-medium" style="color:#002b4d;">{{ $p->title }}</div>
                                <div class="text-xs" style="color:#6b90aa;">{{ $p->product_code }}</div>
                            </td>
                            <td class="py-2.5 text-right">{{ $p->units }}</td>
                            <td class="py-2.5 text-right font-semibold">₱{{ number_format($p->gross, 2) }}</td>
                            <td class="py-2.5 text-right" style="color:#fa4e1c;">₱{{ number_format($p->commission, 2) }}</td>
                            <td class="py-2.5 text-right" style="color:#059669;">₱{{ number_format($p->payout, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-sm" style="color:#6b90aa;">No sales in this period.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card p-5">
            <h3 class="font-display text-base font-semibold mb-4" style="color:#002b4d;">Sales per day</h3>
            @forelse ($daily as $d)
                <div class="flex items-center justify-between border-b py-2 text-sm" style="border-color:#F5F0EB;">
                    <span style="color:#4a3f38;">{{ \Carbon\Carbon::parse($d->day)->format('M d, Y') }}</span>
                    <span><span class="text-xs" style="color:#6b90aa;">{{ $d->orders }} {{ \Illuminate\Support\Str::plural('order', $d->orders) }}</span>
                          <span class="ml-2 font-semibold" style="color:#002b4d;">₱{{ number_format($d->gross, 2) }}</span></span>
                </div>
            @empty
                <p class="text-sm" style="color:#6b90aa;">No sales in this period.</p>
            @endforelse
        </div>
    </div>
@endif
