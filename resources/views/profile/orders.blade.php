<x-layout title="My Orders — ALVY">
<div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">

    <h1 class="font-display text-2xl font-bold mb-8" style="color:#002b4d;">My Account</h1>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">

        @include('profile._sidebar')

        <div class="flex-1">

            <h2 class="font-display text-xl font-bold mb-4" style="color:#002b4d;">My Orders</h2>

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
            <div class="mb-6 overflow-hidden" style="background:#fff;border:1px solid #cfdce8;border-radius:12px;">
                <div class="flex overflow-x-auto">
                    @foreach ($tabs as $key => $label)
                        @php $isActive = $activeTab === $key; @endphp
                        <a href="{{ route('profile.orders', ['tab' => $key]) }}"
                           class="relative shrink-0 px-5 py-3.5 text-sm font-semibold transition whitespace-nowrap"
                           style="color:{{ $isActive ? '#fa4e1c' : '#002b4d' }};"
                           @if(!$isActive)
                           onmouseover="this.style.color='#fa4e1c';"
                           onmouseout="this.style.color='#002b4d';"
                           @endif>
                            {{ $label }}
                            @if ($isActive)
                                <span class="absolute bottom-0 left-0 right-0 h-0.5"
                                      style="background:#fa4e1c;border-radius:2px 2px 0 0;"></span>
                            @endif
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

                <div class="mb-4 overflow-hidden" style="background:#fff;border:1px solid #cfdce8;border-radius:12px;">

                    {{-- ── Card header: seller info + status ──────── --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                         style="border-bottom:1px solid #cfdce8;">
                        {{-- Seller / store name --}}
                        <div class="flex items-center gap-2">
                            <div class="flex h-6 w-6 items-center justify-center rounded text-[10px] font-bold text-white"
                                 style="background:#002b4d;">
                                A
                            </div>
                            <span class="font-semibold text-sm" style="color:#222;">ALVY Books</span>
                            <a href="{{ route('messages.show', $order->id) }}"
                               class="flex items-center gap-1 rounded border px-2.5 py-1 text-xs font-semibold transition"
                               style="border-color:#cfdce8;color:#002b4d;"
                               onmouseover="this.style.background='#FFF6EE';" onmouseout="this.style.background='';">
                                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                                Chat
                            </a>
                        </div>

                        {{-- Status line + label --}}
                        <div class="flex items-center gap-3 text-xs">
                            <span style="color:#6B7280;">
                                @if (!$isCancelled)
                                    <svg class="inline h-4 w-4 mr-1 -mt-0.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" style="color:#6B7280;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17l-3-3m0 0l3-3m-3 3h12M3 12a9 9 0 1118 0 9 9 0 01-18 0z"/>
                                    </svg>
                                @endif
                                {{ $statusLine }}
                            </span>
                            <span class="font-bold tracking-wide text-xs" style="color:{{ $statusColor }};">
                                {{ $statusLabel }}
                            </span>
                        </div>
                    </div>

                    {{-- ── Items list ──────────────────────────────── --}}
                    <div class="px-5 py-4 space-y-4">
                        @foreach ($order->items as $item)
                            <div class="flex items-start gap-4">
                                <img src="{{ $item->book && $item->book->image ? asset('storage/'.$item->book->image) : 'https://placehold.co/64x86/F5F0EB/4A2C17?text=📖' }}"
                                     class="h-20 w-14 rounded-lg object-cover flex-shrink-0"
                                     style="border:1px solid #cfdce8;"
                                     alt="{{ $item->book->title ?? '' }}">
                                <div class="flex flex-1 items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-medium leading-snug" style="color:#222;">
                                            {{ $item->book->title ?? '—' }}
                                        </p>
                                        @if ($item->book && $item->book->author)
                                            <p class="text-xs mt-0.5" style="color:#6b90aa;">by {{ $item->book->author }}</p>
                                        @endif
                                        <p class="text-xs mt-1" style="color:#6b90aa;">x{{ $item->quantity }}</p>
                                    </div>
                                    <p class="shrink-0 text-sm font-semibold" style="color:#222;">
                                        ₱{{ number_format($item->price, 2) }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- ── Order total ─────────────────────────────── --}}
                    <div class="flex items-center justify-end gap-2 px-5 py-3 text-sm"
                         style="border-top:1px solid #cfdce8;">
                        <span style="color:#6B7280;">Order Total:</span>
                        <span class="font-display text-lg font-bold" style="color:#fa4e1c;">
                            ₱{{ number_format($order->total_price, 2) }}
                        </span>
                    </div>

                    {{-- ── Action buttons ──────────────────────────── --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3"
                         style="border-top:1px solid #cfdce8;background:#FAFAFA;">

                        {{-- Left: order date --}}
                        <p class="text-xs" style="color:#6b90aa;">
                            Order #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}
                            · {{ $order->created_at->format('m/d/Y H:i') }}
                        </p>

                        {{-- Right: action buttons --}}
                        <div class="flex flex-wrap items-center gap-2">

                            {{-- Cancel Order (Pending / Processing only) --}}
                            @if ($order->isCancellableByBuyer())
                                <button type="button"
                                        onclick="openCancelModal({{ $order->id }})"
                                        class="rounded border px-4 py-2 text-xs font-semibold transition"
                                        style="border-color:#FCA5A5;color:#DC2626;background:#fff;"
                                        onmouseover="this.style.background='#FEF2F2';" onmouseout="this.style.background='#fff';">
                                    Cancel Order
                                </button>
                            @endif

                            {{-- Confirm receipt (Shipped) --}}
                            @if ($isShipped)
                                <form action="{{ route('orders.confirm-delivery', $order->id) }}" method="POST"
                                      onsubmit="return confirm('Confirm that you have received this order?')">
                                    @csrf
                                    <button type="submit"
                                            class="rounded border px-4 py-2 text-xs font-semibold transition"
                                            style="background:#fa4e1c;border-color:#fa4e1c;color:#fff;"
                                            onmouseover="this.style.background='#E14F00';" onmouseout="this.style.background='#fa4e1c';">
                                        Order Received
                                    </button>
                                </form>
                            @endif

                            {{-- Contact Seller --}}
                            <a href="{{ route('messages.show', $order->id) }}"
                               class="rounded border px-4 py-2 text-xs font-semibold transition"
                               style="border-color:#D1D5DB;color:#374151;background:#fff;"
                               onmouseover="this.style.background='#F9FAFB';" onmouseout="this.style.background='#fff';">
                                Contact Seller
                            </a>

                            {{-- Buy Again (Delivered / Cancelled) --}}
                            @if ($isDelivered || $isCancelled)
                                <a href="{{ route('shop.index') }}"
                                   class="rounded border px-4 py-2 text-xs font-semibold transition"
                                   style="border-color:#D1D5DB;color:#374151;background:#fff;"
                                   onmouseover="this.style.background='#F9FAFB';" onmouseout="this.style.background='#fff';">
                                    Buy Again
                                </a>
                            @endif

                        </div>
                    </div>

                    {{-- ── Delivery address (collapsed, visible always) ── --}}
                    <div class="px-5 py-3 text-xs" style="border-top:1px solid #cfdce8;color:#6B7280;">
                        <span class="font-semibold" style="color:#374151;">Deliver to:</span>
                        {{ $order->full_name }}
                        @if ($order->phone) · {{ $order->phone }} @endif
                        · {{ $order->fullAddress() }}
                    </div>

                </div>
            @empty
                <div class="flex flex-col items-center gap-3 rounded-2xl py-16 text-center"
                     style="background:#fff;border:1px solid #cfdce8;">
                    <span class="text-5xl">📦</span>
                    <p class="font-display text-base font-semibold" style="color:#002b4d;">
                        {{ $activeTab === 'all' ? 'No orders yet' : 'No orders here' }}
                    </p>
                    <p class="text-sm" style="color:#6b90aa;">
                        {{ $activeTab === 'all' ? 'When you place an order it will appear here.' : 'Try a different tab or start shopping.' }}
                    </p>
                    <a href="{{ route('shop.index') }}"
                       class="mt-2 rounded-full px-6 py-2.5 text-sm font-semibold transition"
                       style="background:#fa4e1c;color:#fff;"
                       onmouseover="this.style.background='#E14F00';" onmouseout="this.style.background='#fa4e1c';">
                        Browse Books
                    </a>
                </div>
            @endforelse

        </div>
    </div>
</div>

{{-- ══════════════════════ CANCEL ORDER MODAL ══════════════════════ --}}
<div id="cancel-order-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     style="background:rgba(0,0,0,.5);">
    <div class="w-full max-w-md rounded-2xl shadow-xl overflow-hidden" style="background:#fff;">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4"
             style="background:#FEF2F2;border-bottom:1px solid rgba(220,38,38,.15);">
            <h3 class="font-display font-bold text-base" style="color:#DC2626;">Cancel Order</h3>
            <button onclick="closeCancelModal()" class="text-2xl leading-none" style="color:#6b90aa;">&times;</button>
        </div>

        {{-- Body --}}
        <form id="cancel-order-form" method="POST" class="px-6 py-5 space-y-4">
            @csrf
            <p class="text-sm" style="color:#6B7280;">
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
                    <label class="flex items-center gap-2.5 cursor-pointer rounded-xl border px-4 py-2.5 transition"
                           style="border-color:#cfdce8;"
                           onmouseover="this.style.background='#FFF8F3';" onmouseout="this.style.background='';">
                        <input type="radio" name="cancellation_reason" value="{{ $preset }}"
                               class="accent-orange-500 shrink-0"
                               onchange="toggleCustomReason(this.value)">
                        <span class="text-sm" style="color:#374151;">{{ $preset }}</span>
                    </label>
                @endforeach
            </div>

            {{-- Custom reason (shown when "Other" is picked) --}}
            <div id="custom-reason-wrap" class="hidden">
                <textarea name="cancellation_reason_custom" id="custom-reason-text" rows="3"
                          placeholder="Please describe your reason…"
                          class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none resize-none"
                          style="border-color:#cfdce8;color:#374151;"
                          onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"></textarea>
            </div>

            {{-- Footer --}}
            <div class="flex justify-end gap-3 pt-1">
                <button type="button" onclick="closeCancelModal()"
                        class="rounded-full border px-5 py-2 text-sm font-semibold transition"
                        style="border-color:#D1D5DB;color:#374151;"
                        onmouseover="this.style.background='#F9FAFB';" onmouseout="this.style.background='';">
                    Keep Order
                </button>
                <button type="submit"
                        class="rounded-full px-5 py-2 text-sm font-semibold transition"
                        style="background:#DC2626;color:#fff;"
                        onmouseover="this.style.background='#B91C1C';" onmouseout="this.style.background='#DC2626';">
                    Confirm Cancellation
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCancelModal(orderId) {
    var base = '{{ url("/orders") }}/';
    document.getElementById('cancel-order-form').action = base + orderId + '/cancel';
    // Reset state
    document.querySelectorAll('#cancel-order-form input[type=radio]').forEach(function(r){ r.checked = false; });
    document.getElementById('custom-reason-wrap').classList.add('hidden');
    document.getElementById('custom-reason-text').value = '';
    document.getElementById('cancel-order-modal').classList.remove('hidden');
}

function closeCancelModal() {
    document.getElementById('cancel-order-modal').classList.add('hidden');
}

function toggleCustomReason(val) {
    var wrap = document.getElementById('custom-reason-wrap');
    var txt  = document.getElementById('custom-reason-text');
    if (val === 'Other reason') {
        wrap.classList.remove('hidden');
        txt.name = 'cancellation_reason';
    } else {
        wrap.classList.add('hidden');
        txt.name = 'cancellation_reason_custom'; // disable submission
    }
}

// Close on backdrop click
document.getElementById('cancel-order-modal').addEventListener('click', function(e) {
    if (e.target === this) closeCancelModal();
});

// Handle form submit — ensure a reason is provided
document.getElementById('cancel-order-form').addEventListener('submit', function(e) {
    var chosen = this.querySelector('input[name="cancellation_reason"]:checked');
    if (!chosen || !chosen.value.trim()) {
        e.preventDefault();
        alert('Please select a cancellation reason.');
    }
});
</script>
</x-layout>
