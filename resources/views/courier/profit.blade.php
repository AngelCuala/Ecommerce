<x-layout title="My Earnings — ALVY">
<x-courier-nav active="profit" />
<div class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">

    <h1 class="font-display text-2xl font-bold mb-6" style="color:#222;">My Earnings</h1>

    {{-- Summary cards --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 mb-8">
        <div class="rounded-2xl p-5 text-center" style="background:#fff;border:1px solid #cfdce8;">
            <p class="text-2xl">💰</p>
            <p class="mt-1 font-display text-2xl font-bold" style="color:#fa4e1c;">
                ₱{{ number_format($courier->total_earnings,2) }}
            </p>
            <p class="text-xs mt-0.5" style="color:#6b90aa;">Total Earnings</p>
        </div>
        <div class="rounded-2xl p-5 text-center" style="background:#fff;border:1px solid #cfdce8;">
            <p class="text-2xl">📦</p>
            <p class="mt-1 font-display text-2xl font-bold" style="color:#fa4e1c;">{{ $totalDeliveries }}</p>
            <p class="text-xs mt-0.5" style="color:#6b90aa;">Completed Deliveries</p>
        </div>
        <div class="col-span-2 sm:col-span-1 rounded-2xl p-5 text-center" style="background:#fff;border:1px solid #cfdce8;">
            <p class="text-2xl">📊</p>
            <p class="mt-1 font-display text-2xl font-bold" style="color:#fa4e1c;">
                ₱{{ $totalDeliveries > 0 ? number_format($courier->total_earnings / $totalDeliveries, 2) : '0.00' }}
            </p>
            <p class="text-xs mt-0.5" style="color:#6b90aa;">Avg. Per Delivery</p>
        </div>
    </div>

    {{-- Monthly breakdown --}}
    @if($earningsByMonth->count())
        <h2 class="font-display text-lg font-bold mb-4" style="color:#222;">Monthly Breakdown</h2>
        <div class="rounded-2xl overflow-hidden mb-8" style="background:#fff;border:1px solid #cfdce8;">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background:#FFF6EE;border-bottom:1px solid #cfdce8;">
                        <th class="px-5 py-3 text-left text-xs font-semibold" style="color:#6b90aa;">Month</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold" style="color:#6b90aa;">Deliveries</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold" style="color:#6b90aa;">Earnings</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($earningsByMonth as $row)
                        <tr style="border-bottom:1px solid #cfdce8;">
                            <td class="px-5 py-3 font-medium" style="color:#222;">{{ $row->month }}</td>
                            <td class="px-5 py-3 text-right" style="color:#6b90aa;">{{ $row->count }}</td>
                            <td class="px-5 py-3 text-right font-bold" style="color:#fa4e1c;">
                                ₱{{ number_format($row->total,2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Recent deliveries --}}
    <h2 class="font-display text-lg font-bold mb-4" style="color:#222;">Recent Deliveries</h2>
    @forelse($recentDeliveries as $d)
        <div class="flex items-center justify-between rounded-xl px-5 py-3 mb-2"
             style="background:#fff;border:1px solid #cfdce8;">
            <div>
                <p class="text-sm font-medium" style="color:#222;">
                    Order #{{ str_pad($d->order_id,6,'0',STR_PAD_LEFT) }}
                </p>
                <p class="text-xs" style="color:#6b90aa;">
                    {{ $d->delivered_at?->format('M d, Y') ?? '—' }}
                </p>
            </div>
            <span class="font-bold text-sm" style="color:#fa4e1c;">
                +₱{{ number_format($d->delivery_fee,2) }}
            </span>
        </div>
    @empty
        <p class="text-sm text-center py-8" style="color:#6b90aa;">No completed deliveries yet.</p>
    @endforelse
</div>
</x-layout>
