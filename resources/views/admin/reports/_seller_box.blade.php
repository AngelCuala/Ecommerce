{{--
    "Sellers" box on Sales Reports: every seller with their sales for the chosen dates,
    and a "View sales" link to that seller's own report. Expects $sellerBox, $from, $to.
--}}
<div class="card mb-6 p-5" id="seller-box">
    <div class="mb-3 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="font-display text-2xl font-extrabold" style="color:#002b4d;">Sellers Sales Reports</h2>
            <p class="text-xs" style="color:#6b90aa;">
                {{ \Carbon\Carbon::parse($from)->format('M d, Y') }} – {{ \Carbon\Carbon::parse($to)->format('M d, Y') }}
                · {{ $sellerBox->where('units', '>', 0)->count() }} of {{ $sellerBox->count() }} sellers made sales
            </p>
        </div>
        <input type="search" placeholder="Search seller or shop…" class="input w-full py-2 text-sm sm:w-64"
               oninput="var q=this.value.toLowerCase();document.querySelectorAll('#sellerBoxRows tr[data-name]').forEach(function(r){r.style.display=r.dataset.name.indexOf(q)>-1?'':'none';});">
    </div>

    <div class="max-h-[380px] overflow-auto rounded-lg border" style="border-color:#dce8f0;">
        <table class="w-full text-sm">
            <thead class="sticky top-0" style="background:#e8f0f6;">
                <tr style="color:#6b90aa;">
                    <th class="px-3 py-2 text-left text-[11px] font-bold uppercase tracking-wider">Seller</th>
                    <th class="px-3 py-2 text-right text-[11px] font-bold uppercase tracking-wider">Orders</th>
                    <th class="px-3 py-2 text-right text-[11px] font-bold uppercase tracking-wider">Units</th>
                    <th class="px-3 py-2 text-right text-[11px] font-bold uppercase tracking-wider">Gross sales</th>
                    <th class="px-3 py-2 text-right text-[11px] font-bold uppercase tracking-wider">Earnings</th>
                    <th class="px-3 py-2"></th>
                </tr>
            </thead>
            <tbody id="sellerBoxRows">
            @forelse ($sellerBox as $row)
                <tr class="border-t" style="border-color:#eef3f7;{{ $row->units ? '' : 'opacity:.55;' }}"
                    data-name="{{ strtolower($row->name . ' ' . $row->shop . ' ' . $row->email) }}">
                    <td class="px-3 py-2">
                        <div class="font-semibold" style="color:#002b4d;">{{ $row->shop ?: $row->name }}</div>
                        <div class="text-xs" style="color:#6b90aa;">{{ $row->shop ? $row->name . ' · ' : '' }}{{ $row->email }}</div>
                    </td>
                    <td class="px-3 py-2 text-right">{{ $row->orders }}</td>
                    <td class="px-3 py-2 text-right">{{ $row->units }}</td>
                    <td class="px-3 py-2 text-right font-semibold" style="color:#002b4d;">₱{{ number_format($row->gross, 2) }}</td>
                    <td class="px-3 py-2 text-right" style="color:#059669;">₱{{ number_format($row->payout, 2) }}</td>
                    <td class="whitespace-nowrap px-3 py-2 text-right">
                        <a href="{{ route('admin.reports.index', ['type' => 'sellers', 'seller' => $row->id, 'from' => $from, 'to' => $to]) }}"
                           class="text-xs font-semibold" style="color:#fa4e1c;">View sales →</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-3 py-6 text-center text-sm" style="color:#6b90aa;">No sellers yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-xs" style="color:#6b90aa;">
        Faded rows had no sales in these dates. "View sales" opens the seller's report (products sold, sales per day, PDF/CSV).
    </p>
</div>
