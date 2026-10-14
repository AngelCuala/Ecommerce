<x-layout title="My Purchases — ALVY">
<div class="pf-page">
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
    @include('profile._page-header', ['title' => 'My Purchases', 'subtitle' => 'Track, receive, return and rate your orders.'])

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        @include('profile._sidebar')

        <div class="min-w-0 flex-1 space-y-4">
            @if (session('success'))<div class="pf-alert pf-alert-success">{{ session('success') }}</div>@endif
            @if (session('error'))<div class="pf-alert pf-alert-error">{{ session('error') }}</div>@endif

            {{-- Tabs --}}
            <nav class="pf-card pf-nav-scroll flex gap-1 overflow-x-auto p-1.5 text-sm">
                <a href="{{ route('profile.orders') }}" class="whitespace-nowrap rounded-lg px-3 py-2 font-medium" style="{{ $tab === 'all' ? 'background:#fa4e1c;color:#fff;' : 'color:#57534E;' }}">All</a>
                @foreach (\App\Models\Order::TABS as $key => $t)
                    <a href="{{ route('profile.orders', ['tab' => $key]) }}" class="whitespace-nowrap rounded-lg px-3 py-2 font-medium" style="{{ $tab === $key ? 'background:#fa4e1c;color:#fff;' : 'color:#57534E;' }}">
                        {{ $t['label'] }}@if ($counts[$key] > 0) <span class="ml-0.5 text-xs opacity-80">({{ $counts[$key] }})</span>@endif
                    </a>
                @endforeach
            </nav>

            @forelse ($orders as $order)
                @php $c = $order->statusColor(); @endphp
                <article class="pf-card overflow-hidden">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-3" style="border-color:#F0E4DC;">
                        <div class="text-sm">
                            <span class="font-semibold" style="color:#222;">{{ $order->seller?->storeName() ?? 'ALVY' }}</span>
                            <span class="pf-muted">· Order {{ $order->number() }} · {{ $order->created_at->format('M j, Y') }}</span>
                        </div>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" style="background:{{ $c['bg'] }};color:{{ $c['text'] }};">{{ $order->buyerStatusLabel() }}</span>
                    </div>

                    <a href="{{ route('profile.orders.show', $order->id) }}" class="block divide-y px-5" style="--tw-divide-opacity:1;">
                        @foreach ($order->items->take(3) as $item)
                            <div class="flex items-center gap-3 py-3" style="border-color:#F7F3F0;">
                                <img src="{{ $item->product?->image ? asset('storage/' . $item->product->image) : 'https://placehold.co/64x64/f5f5f5/999?text=P' }}" class="h-14 w-14 shrink-0 rounded-md border object-cover" style="border-color:#F2EEEA;" alt="">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm" style="color:#222;">{{ $item->product->title ?? 'Removed product' }}</p>
                                    <p class="text-xs pf-muted">@if ($item->variation_name){{ $item->variation_name }} · @endif× {{ $item->quantity }}</p>
                                </div>
                                <p class="text-sm" style="color:#57534E;">₱{{ number_format($item->price * $item->quantity, 2) }}</p>
                            </div>
                        @endforeach
                        @if ($order->items->count() > 3)
                            <p class="py-2 text-xs pf-muted">+ {{ $order->items->count() - 3 }} more item(s)</p>
                        @endif
                    </a>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t px-5 py-3" style="border-color:#F0E4DC;background:#FDFAF8;">
                        <p class="text-sm" style="color:#57534E;">Order total: <strong style="color:#fa4e1c;">₱{{ number_format($order->total_price, 2) }}</strong></p>
                        <div class="flex flex-wrap gap-2">
                            @if ($order->canBePaid())
                                <form action="{{ route('orders.pay', $order->id) }}" method="POST">@csrf
                                    <button class="pf-btn !py-1.5 px-4 text-sm">{{ $order->payment_status === 'Failed' ? 'Pay again' : 'Pay now' }}</button>
                                </form>
                            @endif
                            @if ($order->canConfirmReceived())
                                <form action="{{ route('orders.confirm-delivery', $order->id) }}" method="POST">@csrf
                                    <button class="pf-btn !py-1.5 px-4 text-sm" onclick="return confirm('Confirm you received this order? It will be marked completed.')">Order Received</button>
                                </form>
                            @endif
                            @if ($order->status === \App\Models\Order::COMPLETED && $order->reviewWindowOpen() && $order->items->contains(fn ($i) => ! $i->review))
                                <a href="{{ route('profile.orders.show', $order->id) }}#rate" class="pf-btn !py-1.5 px-4 text-sm">Rate</a>
                            @endif
                            <a href="{{ route('profile.orders.show', $order->id) }}" class="pf-btn-ghost text-sm">View details</a>
                        </div>
                    </div>
                </article>
            @empty
                <div class="pf-card p-10 text-center">
                    <p class="font-semibold" style="color:#222;">No orders here yet</p>
                    <a href="{{ route('shop.index') }}" class="pf-btn mt-4 inline-flex px-6">Start shopping</a>
                </div>
            @endforelse

            <div>{{ $orders->links() }}</div>
        </div>
    </div>
</div>
</div>
</x-layout>
