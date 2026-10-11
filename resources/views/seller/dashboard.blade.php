<x-seller-layout title="Dashboard" active="dashboard">
@include('partials.panel.plain')
@php
    $me       = auth()->user();
    $shopName = $me->sellerApplication?->shop_name ?? $me->name;

    $active   = $books->where('is_archived', false);
    $outCount = $active->where('stock', 0)->count();
    $lowCount = $active->filter(fn ($b) => $b->stock > 0 && $b->stock <= 5)->count();
    $stockNote = collect([
        $outCount ? $outCount . ' sold out' : null,
        $lowCount ? $lowCount . ' low on stock' : null,
    ])->filter()->implode(', ') ?: 'All in stock';
    $maxBar   = max(1, (float) $earningsChart->max('value'));
    $tagClass = fn ($s) => match (strtolower((string) $s)) {
        'pending' => 'pending', 'processing' => 'processing', 'shipped' => 'shipped',
        'delivered' => 'delivered', 'cancelled' => 'cancelled', default => 'neutral',
    };
@endphp

<div class="mx-auto max-w-6xl space-y-5">

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold" style="color:#222;">{{ $shopName }}</h1>
            <p class="mt-0.5 text-sm pl-muted">Overview for {{ now()->format('F Y') }}</p>
        </div>
        <a href="{{ route('seller.books.create') }}" class="pl-btn">Add product</a>
    </div>

    {{-- Summary — the notes link to where you act on them --}}
    <div class="pl-card grid grid-cols-2 lg:grid-cols-4">
        @foreach ([
            ['This month',   '₱' . number_format($thisMonthEarnings, 2), '₱' . number_format($lastMonthEarnings, 2) . ' last month', route('seller.reports'), false],
            ['Total earned', '₱' . number_format($totalEarnings, 2),     'After the ' . $commissionRate . '% platform fee',          null, false],
            ['Orders',       number_format($totalOrders),                $toShip ? $toShip . ' waiting to ship' : 'Nothing to ship',  route('seller.orders.index'), $toShip > 0],
            ['Products',     number_format($active->count()),            $stockNote,                                                  route('seller.books.index', ['status' => $outCount ? 'out_of_stock' : 'low_stock']), ($outCount + $lowCount) > 0],
        ] as $i => [$label, $value, $note, $href, $flag])
            <div class="px-5 py-4 {{ $i > 0 ? 'border-l' : '' }} {{ $i === 2 ? 'max-lg:border-l-0 max-lg:border-t' : '' }} {{ $i === 3 ? 'max-lg:border-t' : '' }}" style="border-color:#F2EEEA;">
                <p class="text-xs font-medium" style="color:#8a7f78;">{{ $label }}</p>
                <p class="mt-1 text-2xl font-semibold tracking-tight" style="color:#222;">{{ $value }}</p>
                @if ($href && $flag)
                    <a href="{{ $href }}" class="mt-0.5 inline-block text-xs font-medium hover:underline" style="color:#C2410C;">{{ $note }}</a>
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
                <h2>Earnings by month</h2>
                <a href="{{ route('seller.reports') }}" class="pl-link">Reports</a>
            </div>
            <div class="px-5 pb-4 pt-5">
                <div class="flex h-36 items-end gap-2 border-b" style="border-color:#ECE7E3;">
                    @foreach ($earningsChart as $m)
                        @php $pct = $m->value > 0 ? max(3, round($m->value / $maxBar * 100)) : 0; @endphp
                        <div class="flex h-full flex-1 flex-col items-center justify-end" title="{{ $m->label }}: ₱{{ number_format($m->value, 2) }}">
                            @if ($m->value > 0)
                                <span class="mb-1 text-[11px] tabular-nums" style="color:#57534E;">₱{{ number_format($m->value, 0) }}</span>
                            @endif
                            <div class="w-full max-w-[2.5rem] rounded-t-sm"
                                 style="height:{{ $pct }}%;background:{{ $loop->last ? '#fa4e1c' : '#FDBA9F' }};"></div>
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

        {{-- Stock alerts --}}
        <section class="pl-card">
            <div class="pl-head">
                <h2>Stock alerts</h2>
                <a href="{{ route('seller.books.index') }}" class="pl-link">Inventory</a>
            </div>
            @forelse ($lowStock as $p)
                <a href="{{ route('seller.books.edit', $p->id) }}" class="flex items-center justify-between gap-3 border-b px-5 py-2.5 text-sm last:border-b-0 hover:bg-stone-50" style="border-color:#F2EEEA;">
                    <span class="truncate" style="color:#222;">{{ $p->title }}</span>
                    <span class="shrink-0 text-xs font-semibold" style="color:{{ $p->stock == 0 ? '#B91C1C' : '#B45309' }};">
                        {{ $p->stock == 0 ? 'Sold out' : $p->stock . ' left' }}
                    </span>
                </a>
            @empty
                <p class="px-5 py-8 text-center text-sm pl-muted">Nothing is running low.</p>
            @endforelse
        </section>
    </div>

    {{-- Recent sales --}}
    <section class="pl-card overflow-hidden">
        <div class="pl-head">
            <h2>Recent sales</h2>
            <a href="{{ route('seller.orders.index') }}" class="pl-link">All orders</a>
        </div>
        <div class="overflow-x-auto">
            <table class="pl-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Buyer</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="!text-right">You earn</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentOrders as $item)
                        <tr>
                            <td>
                                <a href="{{ route('seller.orders.show', $item->order_id) }}" class="font-medium hover:underline" style="color:#222;">
                                    {{ $item->product->title ?? 'Removed product' }}
                                </a>
                                <span class="pl-muted"> × {{ $item->quantity }}</span>
                            </td>
                            <td>{{ $item->order->user->name ?? $item->order->full_name ?? 'Guest' }}</td>
                            <td class="whitespace-nowrap pl-muted">{{ $item->created_at->format('M j') }}</td>
                            <td><span class="pl-tag {{ $tagClass($item->order?->status) }}">{{ $item->order?->status ?? '—' }}</span></td>
                            <td class="text-right font-medium" style="color:#222;">₱{{ number_format($item->seller_earning, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center pl-muted">No sales yet. Your first order will show up here.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
</x-seller-layout>
