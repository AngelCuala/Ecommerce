<x-admin-layout title="Dashboard" active="dashboard">

{{-- ══════════════════════════════
     STAT CARDS
══════════════════════════════ --}}
<div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">

    {{-- Total Sales --}}
    <div class="relative overflow-hidden rounded-2xl p-6 text-white"
         style="background:linear-gradient(135deg,#002b4d 0%,#004a80 100%);">
        <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full" style="background:rgba(255,255,255,.12);"></div>
        <div class="absolute -bottom-6 -left-4 h-20 w-20 rounded-full" style="background:rgba(255,255,255,.06);"></div>
        <p class="relative text-xs font-semibold uppercase tracking-widest" style="color:rgba(255,255,255,.75);">Total Sales</p>
        <p class="relative mt-2 font-display text-3xl font-bold text-white">${{ number_format($totalSales, 2) }}</p>
        <p class="relative mt-2 flex items-center gap-1 text-xs font-medium" style="color:rgba(255,255,255,.7);">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"/></svg>
            Revenue to date
        </p>
    </div>

    {{-- Total Orders --}}
    <div class="relative overflow-hidden rounded-2xl p-6"
         style="background:linear-gradient(135deg,#fa4e1c 0%,#fb7048 100%);">
        <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full" style="background:rgba(255,255,255,.12);"></div>
        <p class="relative text-xs font-semibold uppercase tracking-widest" style="color:rgba(255,255,255,.75);">Total Orders</p>
        <p class="relative mt-2 font-display text-3xl font-bold text-white">{{ $totalOrders }}</p>
        <p class="relative mt-2 text-xs font-medium" style="color:rgba(255,255,255,.7);">All time orders</p>
    </div>

    {{-- Customers --}}
    <div class="relative overflow-hidden rounded-2xl p-6"
         style="background:linear-gradient(135deg,#003d6b 0%,#005799 100%);">
        <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full" style="background:rgba(255,255,255,.12);"></div>
        <p class="relative text-xs font-semibold uppercase tracking-widest" style="color:rgba(255,255,255,.75);">Customers</p>
        <p class="relative mt-2 font-display text-3xl font-bold text-white">{{ $totalCustomers }}</p>
        <p class="relative mt-2 text-xs font-medium" style="color:rgba(255,255,255,.7);">Registered accounts</p>
    </div>

    {{-- Products --}}
    <div class="relative overflow-hidden rounded-2xl p-6"
         style="background:linear-gradient(135deg,#d93d0e 0%,#fa4e1c 100%);">
        <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full" style="background:rgba(255,255,255,.12);"></div>
        <p class="relative text-xs font-semibold uppercase tracking-widest" style="color:rgba(255,255,255,.75);">Products Listed</p>
        <p class="relative mt-2 font-display text-3xl font-bold text-white">{{ $totalProducts }}</p>
        <p class="relative mt-2 flex items-center gap-1 text-xs font-medium" style="color:rgba(255,255,255,.7);">
            @if ($lowStock->count())
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 9v4m0 4h.01"/><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                {{ $lowStock->count() }} low stock
            @else
                All stock healthy
            @endif
        </p>
    </div>
</div>

{{-- ══════════════════════════════
     RECENT ORDERS + LOW STOCK
══════════════════════════════ --}}
<div class="mt-8 grid gap-6 lg:grid-cols-3">

    {{-- Recent Orders --}}
    <div class="card overflow-hidden lg:col-span-2">
        <div class="flex items-center justify-between px-6 py-4" style="border-bottom:1px solid #dce8f0;">
            <div>
                <h2 class="font-display text-base font-bold" style="color:#222222;">Recent Orders</h2>
                <p class="text-xs" style="color:#6b90aa;">Latest customer transactions</p>
            </div>
            <a href="{{ route('admin.orders.index') }}"
               class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
               style="background:rgba(250,78,28,.12);color:#fa4e1c;"
               onmouseover="this.style.background='#fa4e1c';this.style.color='#FFFFFF';"
               onmouseout="this.style.background='rgba(250,78,28,.12)';this.style.color='#fa4e1c';">
                View all →
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background:#e8f0f6;">
                        <th class="px-6 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Order</th>
                        <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Customer</th>
                        <th class="px-3 py-3 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Status</th>
                        <th class="px-6 py-3 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentOrders as $order)
                        @php
                            // Handle both 'Pending' (DB) and 'pending' (legacy)
                            $statusKey = strtolower($order->status);
                            $sc = match($statusKey) {
                                'delivered'  => ['bg'=>'#ECFDF5','text'=>'#059669'],
                                'shipped'    => ['bg'=>'#FFF7ED','text'=>'#d93d0e'],
                                'processing' => ['bg'=>'#FFEEDD','text'=>'#d93d0e'],
                                'pending'    => ['bg'=>'#e8f0f6','text'=>'#fa4e1c'],
                                'cancelled'  => ['bg'=>'#FEF2F2','text'=>'#B91C1C'],
                                default      => ['bg'=>'#F5F5F5','text'=>'#666666'],
                            };
                        @endphp
                        <tr style="border-bottom:1px solid #dce8f0;transition:background .15s;"
                            onmouseover="this.style.background='#e8f0f6';"
                            onmouseout="this.style.background='';">
                            <td class="px-6 py-3.5">
                                <a href="{{ route('admin.orders.show', $order->id) }}"
                                   class="font-semibold transition"
                                   style="color:#fa4e1c;"
                                   onmouseover="this.style.color='#d93d0e';"
                                   onmouseout="this.style.color='#fa4e1c';">#{{ str_pad($order->id, 5, '0', STR_PAD_LEFT) }}</a>
                            </td>
                            <td class="px-3 py-3.5" style="color:#555555;">{{ $order->user->name ?? 'Guest' }}</td>
                            <td class="px-3 py-3.5">
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                      style="background:{{ $sc['bg'] }};color:{{ $sc['text'] }};">
                                    {{ ucfirst($order->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-3.5 text-right font-bold" style="color:#222222;">${{ number_format($order->total_price, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-6 py-10 text-center text-sm" style="color:#6b90aa;">No orders yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Low Stock --}}
    <div class="card overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4" style="border-bottom:1px solid #dce8f0;">
            <div>
                <h2 class="font-display text-base font-bold" style="color:#222222;">Low Stock</h2>
                <p class="text-xs" style="color:#6b90aa;">Items needing restock</p>
            </div>
            <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
                  style="background:#FEF2F2;color:#DC2626;">
                {{ $lowStock->count() }}
            </span>
        </div>
        <div class="p-4 space-y-2">
            @forelse ($lowStock as $product)
                <div class="flex items-center justify-between rounded-xl p-3" style="background:#e8f0f6;">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-semibold" style="color:#222222;">{{ Str::limit($product->title, 22) }}</p>
                        <p class="text-xs" style="color:#6b90aa;">{{ $product->format ?? '' }}</p>
                    </div>
                    <span class="ml-3 shrink-0 rounded-full px-2.5 py-1 text-xs font-bold"
                          style="background:{{ $product->stock <= 2 ? '#FEF2F2' : 'rgba(250,78,28,.12)' }};
                                 color:{{ $product->stock <= 2 ? '#DC2626' : '#fa4e1c' }};">
                        {{ $product->stock }} left
                    </span>
                </div>
            @empty
                <div class="flex flex-col items-center gap-2 py-8 text-center">
                    <span class="text-2xl">✅</span>
                    <p class="text-sm" style="color:#6b90aa;">All stock levels healthy</p>
                </div>
            @endforelse
        </div>
    </div>
</div>

{{-- ══════════════════════════════
     SALES CHART
══════════════════════════════ --}}
<div class="card mt-8 overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4" style="border-bottom:1px solid #dce8f0;">
        <div>
            <h2 class="font-display text-base font-bold" style="color:#222222;">Sales by Month</h2>
            <p class="text-xs" style="color:#6b90aa;">Monthly revenue overview</p>
        </div>
        <span class="rounded-full px-3 py-1 text-xs font-semibold"
              style="background:rgba(250,78,28,.12);color:#fa4e1c;">
            {{ date('Y') }}
        </span>
    </div>
    <div class="px-6 py-6">
        @php $max = $salesByMonth->max('total') ?: 1; @endphp
        <div class="flex h-48 items-end gap-2">
            @forelse ($salesByMonth as $row)
                @php $h = max(8, ($row->total / $max) * 176); @endphp
                <div class="group flex flex-1 flex-col items-center gap-1.5">
                    <span class="mb-1 rounded px-1.5 py-0.5 text-[10px] font-semibold opacity-0 transition-opacity group-hover:opacity-100"
                          style="background:#222222;color:#FFFFFF;">
                        ${{ number_format($row->total, 0) }}
                    </span>
                    <div class="w-full rounded-t-xl transition-all duration-300"
                         style="height:{{ $h }}px;background:linear-gradient(180deg,#fa4e1c 0%,#002b4d 100%);opacity:.9;">
                    </div>
                    <span class="text-[10px] font-medium" style="color:#6b90aa;">{{ $row->month }}</span>
                </div>
            @empty
                <p class="text-sm" style="color:#6b90aa;">No sales data yet.</p>
            @endforelse
        </div>
    </div>
</div>

</x-admin-layout>