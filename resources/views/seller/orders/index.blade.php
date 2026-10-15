<x-seller-layout title="Orders" active="orders">
@php
    $statusStyle = [
        'Pending'    => 'background:#FFF7ED;color:#C2410C;',
        'Processing' => 'background:#FFF1EE;color:#D93D0E;',
        'Shipped'    => 'background:#EFF6FF;color:#1D4ED8;',
        'Delivered'  => 'background:#ECFDF5;color:#047857;',
        'Completed'  => 'background:#ECFDF5;color:#047857;',
        'Cancelled'  => 'background:#F5F5F4;color:#78716C;',
        'Return Requested' => 'background:#EFF6FF;color:#1D4ED8;',
        'Refunded'   => 'background:#F5F5F4;color:#57534E;',
        'In Dispute' => 'background:#FEF2F2;color:#B91C1C;',
    ];
    $stepLabel = ['new' => 'New', 'accepted' => 'Accepted', 'packed' => 'Packed', 'ready' => 'Ready for Pickup'];
    $tabs = ['all' => 'All'] + collect(\App\Models\Order::SELLER_TABS)->map(fn ($t) => $t['label'])->all();
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

    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold" style="color:#222;">Orders</h1>
            <p class="mt-0.5 text-sm" style="color:#8a7f78;">New → Accepted → Packed → Ready for Pickup. Then the courier takes over.</p>
        </div>
    </div>

    @if ($errors->any())<div class="mb-4 rounded-lg px-4 py-2.5 text-sm" style="background:#FEF2F2;color:#B91C1C;">{{ $errors->first() }}</div>@endif

    {{-- Filters --}}
    <form method="GET" class="mb-3 flex flex-wrap items-end gap-2 text-sm">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <label class="flex flex-col"><span class="order-label">From</span><input type="date" name="date_from" value="{{ request('date_from') }}" class="input !py-1.5"></label>
        <label class="flex flex-col"><span class="order-label">To</span><input type="date" name="date_to" value="{{ request('date_to') }}" class="input !py-1.5"></label>
        <label class="flex flex-1 flex-col"><span class="order-label">Order ID</span><input type="search" name="q" value="{{ request('q') }}" placeholder="e.g. 000123" class="input !py-1.5"></label>
        <button class="rounded-md px-4 py-2 font-semibold text-white" style="background:#222;">Filter</button>
        @if (request()->hasAny(['date_from', 'date_to', 'q']))<a href="{{ route('seller.orders.index', ['tab' => $tab]) }}" class="px-2 py-2 font-semibold" style="color:#8a7f78;">Clear</a>@endif
    </form>

    <div class="mb-5 flex flex-wrap border-b" style="border-color:#ECE7E3;">
        @foreach ($tabs as $key => $label)
            <a href="{{ route('seller.orders.index', array_merge(request()->only(['date_from', 'date_to', 'q']), ['tab' => $key])) }}"
               class="status-tab {{ $tab === $key ? 'is-active' : '' }}" data-tab="{{ $key }}">{{ $label }}<span class="tab-count">{{ $counts[$key] ?? 0 }}</span></a>
        @endforeach
    </div>

    <div class="space-y-4">
        @forelse ($orders as $order)
            @php
                $itemsTotal = $order->sellerItems->sum(fn ($i) => $i->price * $i->quantity);
                $earnTotal  = $order->sellerItems->sum('seller_earning');
                $step       = $order->sellerStep();
            @endphp
            <article class="order-card" data-order="{{ $order->id }}" data-status="{{ $order->status }}">
                <header class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b px-5 py-3.5" style="border-color:#F2EEEA;">
                    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1">
                        <a href="{{ route('seller.orders.show', $order->id) }}" class="font-semibold hover:underline" style="color:#222;">Order {{ $order->number() }}</a>
                        <span class="text-sm" style="color:#8a7f78;">{{ $order->created_at->format('M j, Y · g:i A') }}</span>
                    </div>
                    <div class="flex items-center gap-3 text-sm">
                        <span style="color:#8a7f78;">{{ $order->payment_method }} · {{ $order->payment_status }}</span>
                        <span class="rounded-md px-2 py-0.5 text-xs font-semibold" style="{{ $statusStyle[$order->status] ?? 'background:#F5F5F4;color:#57534E;' }}">
                            {{ isset($stepLabel[$step]) && in_array($order->status, ['Pending', 'Processing']) ? $stepLabel[$step] : $order->status }}
                        </span>
                    </div>
                </header>

                <div class="divide-y px-5" style="border-color:#F2EEEA;">
                    @foreach ($order->sellerItems as $item)
                        <div class="flex items-center gap-3 py-3" style="border-color:#F2EEEA;">
                            @if ($item->product?->image)
                                <img src="{{ asset('storage/' . $item->product->image) }}" alt="" class="h-12 w-12 shrink-0 rounded-md border object-cover" style="border-color:#ECE7E3;">
                            @else
                                <span class="h-12 w-12 shrink-0 rounded-md" style="background:#F5F2EF;"></span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium" style="color:#222;">{{ $item->product->title ?? 'Removed product' }}</p>
                                <p class="text-xs" style="color:#8a7f78;">@if ($item->variation_name){{ $item->variation_name }} · @endif₱{{ number_format($item->price, 2) }} × {{ $item->quantity }}</p>
                            </div>
                            <p class="text-sm font-medium" style="color:#222;">₱{{ number_format($item->price * $item->quantity, 2) }}</p>
                        </div>
                    @endforeach
                </div>

                <footer class="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-3 text-sm" style="border-color:#F2EEEA;background:#FCFBFA;border-radius:0 0 .75rem .75rem;">
                    <p style="color:#6f6560;">
                        {{ $order->full_name }} · Total <strong style="color:#222;">₱{{ number_format($itemsTotal, 2) }}</strong>
                        <span class="mx-1.5" style="color:#d6cec8;">|</span>
                        You earn <strong style="color:#047857;">₱{{ number_format($earnTotal, 2) }}</strong>
                    </p>
                    <div class="flex items-center gap-3">
                        @if ($step === 'new')
                            <form method="POST" action="{{ route('seller.orders.accept', $order->id) }}">@csrf<button class="rounded-md px-3 py-1.5 text-xs font-semibold text-white" style="background:#fa4e1c;">Accept</button></form>
                        @elseif ($step === 'accepted')
                            <form method="POST" action="{{ route('seller.orders.pack', $order->id) }}">@csrf<button class="rounded-md px-3 py-1.5 text-xs font-semibold text-white" style="background:#fa4e1c;">Mark Packed</button></form>
                        @endif
                        <a href="{{ route('seller.orders.show', $order->id) }}" class="font-semibold hover:underline" style="color:#fa4e1c;">View details</a>
                    </div>
                </footer>
            </article>
        @empty
            <div class="order-card px-6 py-14 text-center">
                <p class="font-medium" style="color:#222;">No orders here</p>
                <p class="mt-1 text-sm" style="color:#8a7f78;">{{ request()->hasAny(['date_from', 'date_to', 'q']) ? 'No orders match the filters.' : 'When buyers order your products, they\'ll show up here.' }}</p>
            </div>
        @endforelse
        <div>{{ $orders->links() }}</div>
    </div>
</div>
</x-seller-layout>
