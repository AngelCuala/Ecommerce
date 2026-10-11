<x-seller-layout title="Orders" active="orders">
@php
    $tabs   = ['All', 'Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];
    $counts = $orders->countBy('status');
    $statusStyle = [
        'Pending'    => 'background:#FFF7ED;color:#C2410C;',
        'Processing' => 'background:#FFF1EE;color:#D93D0E;',
        'Shipped'    => 'background:#EFF6FF;color:#1D4ED8;',
        'Delivered'  => 'background:#ECFDF5;color:#047857;',
        'Cancelled'  => 'background:#F5F5F4;color:#78716C;',
    ];
@endphp
<style>
    .status-tab { position: relative; padding: .6rem .25rem; margin-right: 1.25rem; font-size: .875rem; font-weight: 600; color: #8a7f78; transition: color .15s; }
    .status-tab:hover { color: #222; }
    .status-tab.is-active { color: #222; }
    .status-tab.is-active::after { content: ''; position: absolute; left: 0; right: 0; bottom: -1px; height: 2px; background: #fa4e1c; border-radius: 2px; }
    .status-tab .tab-count { margin-left: .35rem; font-size: .75rem; font-weight: 600; color: #b3a79f; }
    .status-tab.is-active .tab-count { color: #fa4e1c; }
    .order-card { background: #fff; border: 1px solid #ECE7E3; border-radius: .75rem; }
    .order-label { font-size: .7rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #a39890; }
</style>

<div class="mx-auto max-w-5xl">

    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold" style="color:#222;">Orders</h1>
            <p class="mt-0.5 text-sm" style="color:#8a7f78;">Orders that include your products.</p>
        </div>
    </div>

    {{-- Status filter (client-side, see js/seller-orders.js) --}}
    <div class="mb-5 flex flex-wrap border-b" style="border-color:#ECE7E3;" id="statusTabs">
        @foreach ($tabs as $tab)
            <button type="button" class="status-tab {{ $tab === 'All' ? 'is-active' : '' }}" data-status="{{ $tab }}">
                {{ $tab }}<span class="tab-count">{{ $tab === 'All' ? $orders->count() : ($counts[$tab] ?? 0) }}</span>
            </button>
        @endforeach
    </div>

    <div class="space-y-4">
        @forelse ($orders as $order)
            @php
                $itemsTotal = $order->sellerItems->sum(fn ($i) => $i->price * $i->quantity);
                $earnTotal  = $order->sellerItems->sum('seller_earning');
            @endphp
            <article class="order-card" data-status="{{ $order->status }}">

                {{-- Header --}}
                <header class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b px-5 py-3.5" style="border-color:#F2EEEA;">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <a href="{{ route('seller.orders.show', $order->id) }}" class="font-semibold hover:underline" style="color:#222;">
                            Order #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}
                        </a>
                        <span class="text-sm" style="color:#8a7f78;">{{ $order->created_at->format('M j, Y · g:i A') }}</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <span style="color:#8a7f78;">{{ $order->payment_method }} · {{ $order->payment_status }}</span>
                        <span class="rounded-md px-2 py-0.5 text-xs font-semibold" style="{{ $statusStyle[$order->status] ?? 'background:#F5F5F4;color:#57534E;' }}">
                            {{ $order->status }}
                        </span>
                    </div>
                </header>

                <div class="grid md:grid-cols-[1fr_17rem]">
                    {{-- Items --}}
                    <div class="divide-y px-5" style="--tw-divide-opacity:1;border-color:#F2EEEA;">
                        @foreach ($order->sellerItems as $item)
                            <div class="flex items-center gap-3 py-3.5" style="border-color:#F2EEEA;">
                                @if ($item->product?->image)
                                    <img src="{{ asset('storage/' . $item->product->image) }}" alt=""
                                         class="h-14 w-14 shrink-0 rounded-md border object-cover" style="border-color:#ECE7E3;">
                                @else
                                    <span class="h-14 w-14 shrink-0 rounded-md" style="background:#F5F2EF;"></span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium" style="color:#222;">{{ $item->product->title ?? 'Removed product' }}</p>
                                    <p class="text-xs" style="color:#8a7f78;">₱{{ number_format($item->price, 2) }} × {{ $item->quantity }}</p>
                                </div>
                                <p class="text-sm font-medium" style="color:#222;">₱{{ number_format($item->price * $item->quantity, 2) }}</p>
                            </div>
                        @endforeach
                    </div>

                    {{-- Ship to + status --}}
                    <div class="border-t px-5 py-4 md:border-l md:border-t-0" style="border-color:#F2EEEA;">
                        <p class="order-label">Ship to</p>
                        <p class="mt-1.5 text-sm font-medium" style="color:#222;">{{ $order->full_name }}</p>
                        <p class="mt-0.5 text-sm" style="color:#6f6560;">{{ $order->phone }}</p>
                        <p class="mt-1.5 text-sm leading-snug" style="color:#6f6560;">{{ $order->fullAddress() }}</p>

                        @if (! in_array($order->status, ['Delivered', 'Cancelled']))
                            <form action="{{ route('seller.orders.update', $order->id) }}" method="POST" class="mt-4">
                                @csrf @method('PATCH')
                                <label for="status-{{ $order->id }}" class="order-label">Update status</label>
                                <div class="mt-1.5 flex gap-2">
                                    <select id="status-{{ $order->id }}" name="status" class="input flex-1 !py-1.5 text-sm">
                                        @foreach (['Pending', 'Processing', 'Shipped', 'Delivered'] as $s)
                                            <option value="{{ $s }}" @selected($order->status === $s)>{{ $s }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="rounded-md px-3 text-sm font-semibold text-white transition hover:opacity-90" style="background:#222;">Save</button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Footer --}}
                <footer class="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-3 text-sm" style="border-color:#F2EEEA;background:#FCFBFA;border-radius:0 0 .75rem .75rem;">
                    <p style="color:#6f6560;">
                        Total <strong style="color:#222;">₱{{ number_format($itemsTotal, 2) }}</strong>
                        <span class="mx-1.5" style="color:#d6cec8;">|</span>
                        You earn <strong style="color:#047857;">₱{{ number_format($earnTotal, 2) }}</strong>
                    </p>
                    <a href="{{ route('seller.orders.show', $order->id) }}" class="font-semibold hover:underline" style="color:#fa4e1c;">View details</a>
                </footer>
            </article>
        @empty
            <div class="order-card px-6 py-14 text-center">
                <p class="font-medium" style="color:#222;">No orders yet</p>
                <p class="mt-1 text-sm" style="color:#8a7f78;">When buyers order your products, they'll show up here.</p>
            </div>
        @endforelse

        @if ($orders->isNotEmpty())
            <div id="noTabResults" class="order-card hidden px-6 py-10 text-center text-sm" style="color:#8a7f78;">
                No orders with this status.
            </div>
        @endif
    </div>
</div>

<script src="{{ asset('js/seller-orders.js') }}"></script>
</x-seller-layout>
