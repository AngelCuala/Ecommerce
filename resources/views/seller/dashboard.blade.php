<x-seller-layout title="Dashboard" active="dashboard">
@include('partials.panel.plain')
@php
    $me       = auth()->user();
    $shopName = $me->sellerApplication?->shop_name ?? $me->name;

    $active   = $books->where('is_archived', false);
    $outCount = $active->where('stock', 0)->count();
    $lowCount = $active->filter(fn ($b) => $b->isLowStock())->count();
    $stockNote = collect([
        $outCount ? $outCount . ' sold out' : null,
        $lowCount ? $lowCount . ' low on stock' : null,
    ])->filter()->implode(', ') ?: 'All in stock';
    $peso     = fn ($v) => '₱' . number_format((float) $v, 2);
    $maxBar   = max(1, (float) $earningsChart->max('value'));
    $tagClass = fn ($s) => match (strtolower((string) $s)) {
        'pending', 'awaiting payment' => 'pending', 'processing' => 'processing', 'shipped' => 'shipped',
        'delivered', 'completed' => 'delivered', 'cancelled', 'refunded' => 'cancelled', default => 'neutral',
    };
    $q = $range->query();
@endphp
<style>
    .db-period { display:inline-flex; flex-wrap:wrap; gap:.5rem; }
    .db-period a, .db-period button { padding:.4rem .9rem; font-size:.8125rem; font-weight:500; color:#57534E; background:#fff; border:1px solid #E7E5E4; border-radius:999px; transition:background .15s, border-color .15s, color .15s; }
    .db-period a:hover, .db-period button:hover { border-color:#D6D3D1; background:#FAFAF9; color:#222; }
    .db-period .on, .db-period .on:hover { background:#FFF1EE; border-color:#FDBA9F; color:#D93D0E; font-weight:600; }
    .db-stat p.k { font-size:.75rem; font-weight:500; color:#8a7f78; }
    .db-stat p.v { margin-top:.25rem; font-size:1.5rem; font-weight:600; letter-spacing:-.01em; color:#222; font-variant-numeric:tabular-nums; }
    .db-count { display:flex; align-items:center; justify-content:space-between; gap:.75rem; padding:.7rem 1.25rem; border-bottom:1px solid #F2EEEA; font-size:.875rem; }
    .db-count:last-child { border-bottom:0; }
    .db-dot { width:.5rem; height:.5rem; border-radius:999px; display:inline-block; margin-right:.5rem; }
</style>

{{-- Warnings from ALVY (Admin → Reports & Complaints) stay here until the seller acknowledges them. --}}
@foreach (\App\Models\SellerViolation::where('seller_id', $me->id)->where('acknowledged', false)->with('product:id,title')->latest()->get() as $v)
    <div class="mb-4 flex flex-wrap items-start gap-3 rounded-xl border p-4" style="background:#FEF2F2;border-color:#FCA5A5;" data-violation-warning>
        <svg class="mt-0.5 h-5 w-5 shrink-0" style="color:#DC2626;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
        <div class="min-w-0 flex-1 text-sm">
            <p class="font-bold" style="color:#991B1B;">{{ \App\Models\SellerViolation::actionLabels()[$v->action] ?? 'Warning' }} from ALVY · {{ \App\Models\SellerViolation::typeLabels()[$v->type] ?? $v->type }}</p>
            <p class="mt-0.5" style="color:#7F1D1D;">@if ($v->product)"{{ $v->product->title }}": @endif{{ $v->note }}</p>
            <p class="mt-1 text-xs" style="color:#B91C1C;">{{ $v->created_at->format('M j, Y g:i A') }} · Repeated violations can lead to suspension.</p>
        </div>
        <form method="POST" action="{{ route('seller.violations.acknowledge', $v->id) }}">@csrf
            <button class="rounded-lg px-3 py-1.5 text-xs font-semibold text-white" style="background:#DC2626;">I understand</button>
        </form>
    </div>
@endforeach

<div class="mx-auto max-w-6xl space-y-5" x-data="{ custom: {{ $range->period === 'custom' ? 'true' : 'false' }} }">

    {{-- Header + period --}}
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold" style="color:#222;">{{ $shopName }}</h1>
            <p class="mt-0.5 text-sm pl-muted" data-period-label>{{ $range->label() }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <nav class="db-period" aria-label="Period">
                @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month'] as $key => $label)
                    <a href="{{ route('seller.dashboard', ['period' => $key]) }}" class="{{ $range->period === $key ? 'on' : '' }}">{{ $label }}</a>
                @endforeach
                <button type="button" @click="custom = !custom" class="{{ $range->period === 'custom' ? 'on' : '' }}">Custom</button>
            </nav>
            <a href="{{ route('seller.books.create') }}" class="pl-btn">Add product</a>
        </div>
    </div>

    <form method="GET" action="{{ route('seller.dashboard') }}" x-show="custom" @if ($range->period !== 'custom') style="display:none;" @endif class="pl-card flex flex-wrap items-end gap-3 px-5 py-3">
        <input type="hidden" name="period" value="custom">
        <div>
            <label class="pl-label" for="from">From</label>
            <input type="date" id="from" name="from" value="{{ $range->from->toDateString() }}" max="{{ now()->toDateString() }}" class="input !py-1.5" required>
        </div>
        <div>
            <label class="pl-label" for="to">To</label>
            <input type="date" id="to" name="to" value="{{ $range->to->toDateString() }}" max="{{ now()->toDateString() }}" class="input !py-1.5" required>
        </div>
        <button class="pl-btn">Apply</button>
        <a href="{{ route('seller.dashboard') }}" class="pl-btn-line">Reset</a>
    </form>
    @if ($errors->any())
        <p class="rounded-lg px-4 py-2.5 text-sm" style="background:#FEF2F2;color:#B91C1C;">{{ $errors->first() }}</p>
    @endif

    {{-- Money --}}
    <div class="pl-card grid grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['Sales',        $peso($money['gross']),      $money['refunds'] > 0 ? $peso($money['refunds']) . ' refunded' : 'What buyers paid for your items'],
            ['Commission',   $peso($money['commission']), 'ALVY fee (' . rtrim(rtrim(number_format($commissionRate, 2), '0'), '.') . '% per sale)'],
            ['Net earnings', $peso($money['net']),        'Sales minus commission'],
            ['Orders',       number_format($orderCounts['total']), $toShip ? $toShip . ' waiting to ship' : 'Nothing to ship'],
        ] as $i => [$label, $value, $note])
            <div class="db-stat px-5 py-4 {{ $i > 0 ? 'border-l' : '' }} {{ $i === 2 ? 'max-lg:border-l-0 max-lg:border-t' : '' }} {{ $i === 3 ? 'max-lg:border-t' : '' }}" style="border-color:#F2EEEA;" data-stat="{{ \Illuminate\Support\Str::slug($label) }}">
                <p class="k">{{ $label }}</p>
                <p class="v">{{ $value }}</p>
                @if ($i === 3 && $toShip)
                    <a href="{{ route('seller.orders.index') }}" class="mt-0.5 inline-block text-xs font-medium hover:underline" style="color:#C2410C;">{{ $note }}</a>
                @else
                    <p class="mt-0.5 text-xs pl-muted">{{ $note }}</p>
                @endif
            </div>
        @endforeach
    </div>

    <div class="grid items-start gap-5 lg:grid-cols-3">
        {{-- Earnings by month --}}
        <section class="pl-card lg:col-span-2">
            <div class="pl-head">
                <h2>Net earnings by month</h2>
                <a href="{{ route('seller.commissions', $q) }}" class="pl-link">Commission details</a>
            </div>
            <div class="px-5 pb-4 pt-5">
                <div class="flex h-36 items-end gap-2 border-b" style="border-color:#ECE7E3;">
                    @foreach ($earningsChart as $m)
                        @php $pct = $m->value > 0 ? max(3, round($m->value / $maxBar * 100)) : 0; @endphp
                        <div class="flex h-full flex-1 flex-col items-center justify-end" title="{{ $m->label }}: {{ $peso($m->value) }}">
                            @if ($m->value > 0)
                                <span class="mb-1 text-[11px] tabular-nums" style="color:#57534E;">₱{{ number_format($m->value, 0) }}</span>
                            @endif
                            <div class="w-full max-w-[2.5rem] rounded-t-sm" style="height:{{ $pct }}%;background:{{ $loop->last ? '#fa4e1c' : '#FDBA9F' }};"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-1.5 flex gap-2">
                    @foreach ($earningsChart as $m)
                        <span class="flex-1 text-center text-xs" style="color:{{ $loop->last ? '#44403C' : '#a39890' }};">{{ $m->label }}</span>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Orders by status --}}
        <section class="pl-card">
            <div class="pl-head">
                <h2>Orders</h2>
                <a href="{{ route('seller.orders.index') }}" class="pl-link">All orders</a>
            </div>
            @foreach ([
                ['in_progress', 'In progress', '#F59E0B'],
                ['completed',   'Completed',   '#10B981'],
                ['cancelled',   'Cancelled',   '#A8A29E'],
                ['refunded',    'Refunded',    '#EF4444'],
            ] as [$key, $label, $dot])
                <div class="db-count" data-orders="{{ $key }}">
                    <span style="color:#44403C;"><span class="db-dot" style="background:{{ $dot }};"></span>{{ $label }}</span>
                    <span class="font-semibold tabular-nums" style="color:#222;">{{ number_format($orderCounts[$key]) }}</span>
                </div>
            @endforeach
        </section>
    </div>

    <div class="grid items-start gap-5 lg:grid-cols-3">
        {{-- Best sellers --}}
        <section class="pl-card lg:col-span-2 overflow-hidden">
            <div class="pl-head"><h2>Best sellers</h2><span class="text-xs pl-muted">{{ $range->label() }}</span></div>
            <table class="pl-table">
                <thead><tr><th>Product</th><th class="!text-right">Units</th><th class="!text-right">Sales</th></tr></thead>
                <tbody>
                    @forelse ($bestSellers as $b)
                        <tr data-best-seller="{{ $b->product_id }}">
                            <td class="max-w-[16rem] truncate font-medium" style="color:#222;">{{ $b->title }}</td>
                            <td class="text-right tabular-nums">{{ number_format($b->units) }}</td>
                            <td class="text-right tabular-nums">{{ $peso($b->sales) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="py-8 text-center pl-muted">No sales in this period yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </section>

        {{-- Products & stock --}}
        <section class="pl-card">
            <div class="pl-head">
                <h2>Products</h2>
                <a href="{{ route('seller.books.index') }}" class="pl-link">Inventory</a>
            </div>
            <div class="grid grid-cols-2 border-b text-center" style="border-color:#F2EEEA;">
                <a href="{{ route('seller.books.index', ['status' => 'active']) }}" class="px-3 py-3 hover:bg-stone-50">
                    <p class="text-lg font-semibold" style="color:#222;" data-products="active">{{ $productCounts['active'] }}</p><p class="text-xs pl-muted">Active</p>
                </a>
                <a href="{{ route('seller.books.index', ['status' => 'archived']) }}" class="border-l px-3 py-3 hover:bg-stone-50" style="border-color:#F2EEEA;">
                    <p class="text-lg font-semibold" style="color:#222;" data-products="inactive">{{ $productCounts['inactive'] }}</p><p class="text-xs pl-muted">Inactive / draft</p>
                </a>
            </div>
            @forelse ($lowStock as $p)
                <a href="{{ route('seller.books.edit', $p->id) }}" class="flex items-center justify-between gap-3 border-b px-5 py-2.5 text-sm last:border-b-0 hover:bg-stone-50" style="border-color:#F2EEEA;" data-low-stock="{{ $p->id }}">
                    <span class="truncate" style="color:#222;">{{ $p->title }}</span>
                    <span class="pl-tag {{ $p->stock == 0 ? 'cancelled' : 'pending' }}" @if ($p->stock == 0) style="background:#FEF2F2;color:#B91C1C;" @endif>
                        {{ $p->stock == 0 ? 'Out of stock' : $p->stock . ' left' }}
                    </span>
                </a>
            @empty
                <p class="px-5 py-6 text-center text-sm pl-muted">Nothing is running low.</p>
            @endforelse
        </section>
    </div>

    {{-- Recent orders --}}
    <section class="pl-card overflow-hidden">
        <div class="pl-head">
            <h2>Recent orders</h2>
            <a href="{{ route('seller.orders.index') }}" class="pl-link">All orders</a>
        </div>
        <div class="overflow-x-auto">
            <table class="pl-table">
                <thead>
                    <tr><th>Order</th><th>Product</th><th>Buyer</th><th>Date</th><th>Status</th><th class="!text-right">You earn</th></tr>
                </thead>
                <tbody>
                    @forelse ($recentOrders as $item)
                        <tr>
                            <td class="whitespace-nowrap"><a href="{{ route('seller.orders.show', $item->order_id) }}" class="font-medium hover:underline" style="color:#222;">{{ $item->order?->number() }}</a></td>
                            <td>{{ $item->product->title ?? 'Removed product' }}<span class="pl-muted"> × {{ $item->quantity }}</span></td>
                            <td>{{ $item->order->user->name ?? $item->order->full_name ?? 'Guest' }}</td>
                            <td class="whitespace-nowrap pl-muted">{{ $item->created_at->format('M j') }}</td>
                            <td><span class="pl-tag {{ $tagClass($item->order?->status) }}">{{ $item->order?->status ?? '—' }}</span></td>
                            @php $counts = in_array(app(\App\Services\SellerCommission::class)->statusOf($item), ['pending', 'finalized'], true); @endphp
                            <td class="text-right font-medium" style="color:#222;">
                                @if ($counts){{ $peso($item->seller_earning) }}@else<span class="line-through pl-muted">{{ $peso($item->seller_earning) }}</span><span class="block text-[11px] pl-muted">not earned</span>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-10 text-center pl-muted">No orders in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
</x-seller-layout>
