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

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-display text-base font-semibold" style="color:#002b4d;">Sales by Seller · {{ $range }}</h2>
        <span class="flex flex-wrap items-center gap-2">
            <input type="text" id="sellerBoxSearch" placeholder="Find a seller…" class="input !py-1.5 text-sm" style="width:13rem;">
            <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn-outline !py-1.5 px-4 text-sm">Download PDF</a>
        </span>
    </div>

    {{-- One box per seller; click a box for that seller's full report --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3" id="sellerBoxes">
        @forelse ($sellers as $i => $row)
            @php
                $box = $row->id
                    ? route('admin.reports.index', ['type' => 'sellers', 'from' => $from, 'to' => $to, 'seller' => $row->id])
                    : null;
            @endphp
            <{{ $box ? 'a' : 'div' }} @if ($box) href="{{ $box }}" @endif class="card seller-box block p-5 transition hover:shadow-md"
                 style="{{ $row->units ? '' : 'opacity:.6;' }}"
                 data-search="{{ strtolower($row->name . ' ' . $row->shop . ' ' . $row->email) }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate font-display text-base font-bold" style="color:#002b4d;">{{ $row->name }}</p>
                        <p class="truncate text-xs" style="color:#6b90aa;">{{ $row->shop ?: $row->email }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-bold" style="background:#fff1ee;color:#fa4e1c;">#{{ $i + 1 }}</span>
                </div>

                <p class="mt-3 text-xs font-semibold uppercase tracking-wide" style="color:#6b90aa;">Gross Sales</p>
                <p class="font-display text-2xl font-bold" style="color:#fa4e1c;">₱{{ number_format($row->gross, 2) }}</p>

                <div class="mt-3 grid grid-cols-3 gap-2 border-t pt-3 text-center" style="border-color:#F5F0EB;">
                    <div>
                        <p class="text-[11px]" style="color:#6b90aa;">Orders</p>
                        <p class="font-semibold" style="color:#002b4d;">{{ $row->orders }}</p>
                    </div>
                    <div>
                        <p class="text-[11px]" style="color:#6b90aa;">Units</p>
                        <p class="font-semibold" style="color:#002b4d;">{{ $row->units }}</p>
                    </div>
                    <div>
                        <p class="text-[11px]" style="color:#6b90aa;">Earnings</p>
                        <p class="font-semibold" style="color:#059669;">₱{{ number_format($row->payout, 0) }}</p>
                    </div>
                </div>
                <p class="mt-2 text-xs" style="color:#6b90aa;">Commission ₱{{ number_format($row->commission, 2) }}</p>
                @if ($box)
                    <p class="mt-3 text-xs font-semibold" style="color:#fa4e1c;">View sales →</p>
                @endif
            </{{ $box ? 'a' : 'div' }}>
        @empty
            <p class="col-span-full py-6 text-center text-sm" style="color:#6b90aa;">No sellers yet.</p>
        @endforelse
    </div>
    <p id="sellerBoxEmpty" class="hidden py-6 text-center text-sm" style="color:#6b90aa;">No seller matches your search.</p>

    <p class="mt-4 text-xs" style="color:#6b90aa;">Based on the order date. Cancelled orders are not counted. Gross sales are after store vouchers; ALVY vouchers are paid by ALVY and do not lower seller earnings. Sellers with no sales in this period are shown faded.</p>

    <script>
        document.getElementById('sellerBoxSearch').addEventListener('input', function () {
            var q = this.value.trim().toLowerCase(), shown = 0;
            document.querySelectorAll('#sellerBoxes .seller-box').forEach(function (box) {
                var ok = !q || box.dataset.search.indexOf(q) !== -1;
                box.style.display = ok ? '' : 'none';
                if (ok) shown++;
            });
            document.getElementById('sellerBoxEmpty').classList.toggle('hidden', shown > 0);
        });
    </script>

@else
    {{-- ── One seller ──────────────────────────────── --}}
    @php $sellerApp = $seller->id ? \App\Models\SellerApplication::where('user_id', $seller->id)->first(['logo_path', 'line_of_business', 'municipality', 'province']) : null; @endphp
    <a href="{{ route('admin.reports.index', ['type' => 'sellers', 'from' => $from, 'to' => $to]) }}"
       class="mb-4 inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-semibold transition hover:bg-white"
       style="border-color:#E7E5E4;color:#57534E;">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>
        All sellers
    </a>

    {{-- Seller header --}}
    <div class="card mb-6 overflow-hidden" data-seller-report-head>
        <div class="h-1.5" style="background:linear-gradient(90deg,#fa4e1c,#fb7048 45%,#002b4d);"></div>
        <div class="flex flex-wrap items-center gap-4 p-5">
            @if ($sellerApp?->logo_path)
                <img src="{{ asset('storage/' . $sellerApp->logo_path) }}" alt="" class="h-14 w-14 shrink-0 rounded-2xl object-cover" style="border:1px solid #ECE7E3;">
            @else
                <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl font-display text-2xl font-bold"
                      style="background:#FFF1EE;color:#fa4e1c;">{{ strtoupper(mb_substr($seller->shop ?: $seller->name, 0, 1)) }}</span>
            @endif

            <div class="min-w-0 flex-1">
                <h2 class="truncate font-display text-xl font-bold" style="color:#002b4d;">{{ $seller->shop ?: $seller->name }}</h2>
                <p class="mt-0.5 truncate text-sm" style="color:#57534E;">
                    @if ($seller->shop)<span>Owned by <strong style="color:#222;">{{ $seller->name }}</strong></span> · @endif
                    <a href="mailto:{{ $seller->email }}" class="hover:underline" style="color:#6b90aa;">{{ $seller->email }}</a>
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs font-semibold">
                    @if ($sellerApp?->line_of_business)
                        <span class="rounded-full px-2.5 py-1" style="background:#FFF7ED;color:#C2410C;">{{ $sellerApp->line_of_business }}</span>
                    @endif
                    @if ($sellerApp?->municipality)
                        <span class="rounded-full px-2.5 py-1" style="background:#F1F5F9;color:#475569;">{{ $sellerApp->municipality }}{{ $sellerApp->province ? ', ' . $sellerApp->province : '' }}</span>
                    @endif
                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1" style="background:#e8f0f6;color:#1a4d6e;">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                        {{ $range }}
                    </span>
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap gap-2">
                @if ($seller->id)
                    <a href="{{ route('admin.sellers.show', $seller->id) }}" class="btn-outline inline-flex items-center !py-1.5 px-4 text-sm">View seller</a>
                @endif
                <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="btn-gold inline-flex items-center gap-1.5 !py-1.5 px-4 text-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#fff;">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M5 21h14"/></svg>
                    Download PDF
                </a>
            </div>
        </div>
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
