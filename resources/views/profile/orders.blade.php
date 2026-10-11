<x-layout title="My Orders — ALVY">
<div class="pf-page">
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">

    @include('profile._page-header', ['title' => 'My Orders', 'subtitle' => 'Track, manage and review your purchases.'])

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">

        @include('profile._sidebar')

        <div class="min-w-0 flex-1 space-y-5">

            {{-- ── Status filter tabs ─────────────────────────────── --}}
            @php
                $activeTab = request('tab', 'all');
                $tabs = [
                    'all'        => 'All',
                    'to-pay'     => 'To Pay',
                    'to-ship'    => 'To Ship',
                    'to-receive' => 'To Receive',
                    'completed'  => 'Completed',
                    'cancelled'  => 'Cancelled',
                ];
            @endphp
            <div class="pf-card p-1.5">
                <div class="pf-nav-scroll flex gap-1 overflow-x-auto">
                    @foreach ($tabs as $key => $label)
                        @php $isActive = $activeTab === $key; @endphp
                        <a href="{{ route('profile.orders', ['tab' => $key]) }}"
                           class="shrink-0 whitespace-nowrap rounded-xl px-4 py-2.5 text-sm transition {{ $isActive ? 'font-bold text-white' : 'font-semibold text-[#5a4d45] hover:bg-[#fff5f3] hover:text-[#fa4e1c]' }}"
                           @if ($isActive) style="background:#fa4e1c;box-shadow:0 6px 14px -6px rgba(250,78,28,.7);" @endif>
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- ── Filter ─────────────────────────────────────────── --}}
            @php
                $filteredOrders = $orders->filter(function ($order) use ($activeTab) {
                    return match ($activeTab) {
                        'to-pay'     => $order->payment_status !== 'Paid',
                        'to-ship'    => in_array($order->status, ['Pending', 'Processing']),
                        'to-receive' => $order->status === 'Shipped',
                        'completed'  => $order->status === 'Delivered',
                        'cancelled'  => $order->status === 'Cancelled',
                        default      => true,
                    };
                });
            @endphp

            {{-- ── Order cards ────────────────────────────────────── --}}
            @forelse ($filteredOrders as $order)
                @php
                    $isCancelled = $order->status === 'Cancelled';
                    $isDelivered = $order->status === 'Delivered';
                    $isShipped   = $order->status === 'Shipped';

                    $statusLabel = match($order->status) {
                        'Delivered'  => 'COMPLETED',
                        'Shipped'    => 'TO RECEIVE',
                        'Processing' => 'TO SHIP',
                        'Pending'    => 'TO PAY',
                        'Cancelled'  => 'CANCELLED',
                        default      => strtoupper($order->status),
                    };
                    $statusColor = match($order->status) {
                        'Delivered'  => '#059669',
                        'Shipped'    => '#2563EB',
                        'Processing' => '#fa4e1c',
                        'Cancelled'  => '#DC2626',
                        default      => '#B45309',
                    };
                    $statusBadge = match($order->status) {
                        'Delivered'  => 'pf-badge pf-badge-green',
                        'Shipped'    => 'pf-badge pf-badge-blue',
                        'Processing' => 'pf-badge',
                        'Cancelled'  => 'pf-badge pf-badge-red',
                        default      => 'pf-badge',
                    };
                    $statusBadgeBg = match($order->status) {
                        'Delivered', 'Shipped', 'Processing', 'Cancelled' => '',
                        default => 'background:#FFFBEB;',
                    };

                    // Status-line icon mapping
                    $statusLine = match($order->status) {
                        'Delivered'  => 'Parcel has been delivered',
                        'Shipped'    => 'Parcel is on its way',
                        'Processing' => 'Seller is preparing your order',
                        'Pending'    => 'Awaiting payment confirmation',
                        'Cancelled'  => 'Order was cancelled',
                        default      => $order->status,
                    };
                @endphp

                <div class="pf-card overflow-hidden">

                    {{-- ── Card header: seller info + status ──────── --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 sm:px-6"
                         style="border-bottom:1px solid #F0E4DC;background:linear-gradient(120deg,#fff5f3 0%,#fff 70%);">
                        {{-- Seller / store name --}}
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-lg text-xs font-extrabold text-white"
                                 style="background:#002b4d;">
                                A
                            </div>
                            <span class="font-display text-sm font-extrabold" style="color:#222;">ALVY Books</span>
                            <button type="button" onclick="openChatThread('order:{{ $order->id }}')"
                               class="pf-btn-ghost px-2.5 py-1 text-xs">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                                Chat
                            </button>
                        </div>

                        {{-- Status line + label --}}
                        <div class="flex flex-wrap items-center gap-2.5 text-xs">
                            <span class="inline-flex items-center pf-muted">
                                @if (!$isCancelled)
                                    <svg class="mr-1 h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17l-3-3m0 0l3-3m-3 3h12M3 12a9 9 0 1118 0 9 9 0 01-18 0z"/>
                                    </svg>
                                @endif
                                {{ $statusLine }}
                            </span>
                            <span class="{{ $statusBadge }}" style="{{ $statusBadgeBg }}color:{{ $statusColor }};">
                                <span class="h-1.5 w-1.5 rounded-full" style="background:{{ $statusColor }};"></span>
                                {{ $statusLabel }}
                            </span>
                        </div>
                    </div>

                    {{-- ── Items list ──────────────────────────────── --}}
                    <div class="space-y-3 px-5 py-4 sm:px-6">
                        @foreach ($order->items as $item)
                            <div class="flex items-start gap-4 rounded-xl p-3" style="background:#FDF8F5;border:1px solid #F5EBE4;">
                                <img src="{{ $item->product && $item->product->image ? asset('storage/'.$item->product->image) : 'https://placehold.co/64x86/F5F0EB/4A2C17?text=📖' }}"
                                     class="h-20 w-14 flex-shrink-0 rounded-lg bg-white object-cover"
                                     style="border:1px solid #F0E4DC;"
                                     alt="{{ $item->product->title ?? '' }}">
                                <div class="flex min-w-0 flex-1 items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-bold leading-snug" style="color:#222;">
                                            {{ $item->product->title ?? '—' }}
                                        </p>
                                        @if ($item->product && $item->product->author)
                                            <p class="mt-0.5 text-xs pf-muted">by {{ $item->product->author }}</p>
                                        @endif
                                        <p class="mt-1.5"><span class="pf-badge pf-badge-gray">x{{ $item->quantity }}</span></p>
                                    </div>
                                    <p class="shrink-0 text-sm font-bold" style="color:#222;">
                                        ₱{{ number_format($item->price, 2) }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- ── Order total ─────────────────────────────── --}}
                    <div class="flex items-center justify-end gap-2 px-5 py-3 text-sm sm:px-6"
                         style="border-top:1px dashed #F0E4DC;">
                        <span class="pf-muted">Order Total:</span>
                        <span class="font-display text-xl font-extrabold" style="color:#fa4e1c;">
                            ₱{{ number_format($order->total_price, 2) }}
                        </span>
                    </div>

                    {{-- ── Action buttons ──────────────────────────── --}}
                    <div class="flex flex-col gap-3 px-5 py-3.5 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:px-6"
                         style="border-top:1px solid #F0E4DC;background:#FDF8F5;">

                        {{-- Left: order date --}}
                        <p class="text-xs pf-muted">
                            <span class="font-semibold pf-text">Order #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}</span>
                            · {{ $order->created_at->format('m/d/Y H:i') }}
                        </p>

                        {{-- Right: action buttons --}}
                        <div class="flex flex-wrap items-center gap-2">

                            {{-- Cancel Order (Pending / Processing only) --}}
                            @if ($order->isCancellableByBuyer())
                                <button type="button"
                                        onclick="openCancelModal({{ $order->id }})"
                                        class="pf-btn-danger px-4 py-2 text-xs">
                                    Cancel Order
                                </button>
                            @endif

                            {{-- Confirm receipt (Shipped) --}}
                            @if ($isShipped)
                                <form action="{{ route('orders.confirm-delivery', $order->id) }}" method="POST"
                                      onsubmit="return confirm('Confirm that you have received this order?')">
                                    @csrf
                                    <button type="submit" class="pf-btn px-4 py-2 text-xs">
                                        Order Received
                                    </button>
                                </form>
                            @endif

                            {{-- Contact Seller --}}
                            <button type="button" onclick="openChatThread('order:{{ $order->id }}')"
                               class="pf-btn-ghost px-4 py-2 text-xs">
                                Contact Seller
                            </button>

                            {{-- Buy Again (Delivered / Cancelled) --}}
                            @if ($isDelivered || $isCancelled)
                                <a href="{{ route('shop.index') }}"
                                   class="pf-btn-ghost px-4 py-2 text-xs">
                                    Buy Again
                                </a>
                            @endif

                        </div>
                    </div>

                    {{-- ── Delivery address (collapsed, visible always) ── --}}
                    <div class="flex items-start gap-2 px-5 py-3 text-xs pf-muted sm:px-6" style="border-top:1px solid #F0E4DC;">
                        <svg class="mt-px h-3.5 w-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" style="color:#fa4e1c;"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <p class="min-w-0">
                            <span class="font-semibold pf-text">Deliver to:</span>
                            {{ $order->full_name }}
                            @if ($order->phone) · {{ $order->phone }} @endif
                            · {{ $order->fullAddress() }}
                        </p>
                    </div>

                </div>
            @empty
                <div class="pf-card flex flex-col items-center gap-3 px-6 py-16 text-center">
                    <span class="pf-icon h-16 w-16 rounded-2xl">
                        <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
                    </span>
                    <p class="pf-card-title mt-1">
                        {{ $activeTab === 'all' ? 'No orders yet' : 'No orders here' }}
                    </p>
                    <p class="max-w-sm text-sm pf-muted">
                        {{ $activeTab === 'all' ? 'When you place an order it will appear here.' : 'Try a different tab or start shopping.' }}
                    </p>
                    <a href="{{ route('shop.index') }}" class="pf-btn mt-2">
                        Start Shopping
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </a>
                </div>
            @endforelse

        </div>
    </div>
</div>
</div>

{{-- ══════════════════════ CANCEL ORDER MODAL ══════════════════════ --}}
<div id="cancel-order-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(34,24,18,.5);">
    <div class="w-full max-w-md overflow-hidden rounded-[1.25rem] shadow-xl" style="background:#fff;">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4"
             style="background:#FEF2F2;border-bottom:1px solid rgba(220,38,38,.15);">
            <div class="flex items-center gap-3">
                <span class="pf-icon h-9 w-9" style="background:#fff;color:#DC2626;">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </span>
                <h3 class="font-display text-base font-extrabold" style="color:#DC2626;">Cancel Order</h3>
            </div>
            <button onclick="closeCancelModal()" class="text-2xl leading-none pf-muted transition hover:text-[#222]">&times;</button>
        </div>

        {{-- Body --}}
        <form id="cancel-order-form" method="POST" class="px-6 py-5 space-y-4"
              data-orders-base="{{ url('/orders') }}">
            @csrf
            <p class="text-sm pf-muted">
                Please tell us why you want to cancel this order. This helps our sellers improve.
            </p>

            {{-- Quick-pick reasons --}}
            <div class="space-y-2">
                @foreach ([
                    'I ordered by mistake',
                    'I found a cheaper price elsewhere',
                    'I want to change my delivery address',
                    'I want to change the items in my order',
                    'The seller is taking too long to process',
                    'Other reason',
                ] as $preset)
                    <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-[#F0E4DC] px-4 py-2.5 transition hover:border-[#fa4e1c] hover:bg-[#fff5f3]">
                        <input type="radio" name="cancellation_reason" value="{{ $preset }}"
                               class="accent-orange-500 shrink-0"
                               onchange="toggleCustomReason(this.value)">
                        <span class="text-sm pf-text">{{ $preset }}</span>
                    </label>
                @endforeach
            </div>

            {{-- Custom reason (shown when "Other" is picked) --}}
            <div id="custom-reason-wrap" class="hidden">
                <textarea name="cancellation_reason_custom" id="custom-reason-text" rows="3"
                          placeholder="Please describe your reason…"
                          class="pf-input resize-none"></textarea>
            </div>

            {{-- Footer --}}
            <div class="flex justify-end gap-3 pt-1">
                <button type="button" onclick="closeCancelModal()" class="pf-btn-ghost">
                    Keep Order
                </button>
                <button type="submit" class="pf-btn" style="background:#DC2626;box-shadow:0 8px 18px -10px rgba(220,38,38,.7);">
                    Confirm Cancellation
                </button>
            </div>
        </form>
    </div>
</div>

<script src="{{ asset('js/profile-orders.js') }}"></script>
</x-layout>
