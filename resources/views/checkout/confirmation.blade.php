<x-layout title="Order Confirmed — ALVY">
<div class="mx-auto max-w-2xl px-4 py-16 sm:px-6 lg:px-8">

    {{-- Success icon --}}
    <div class="text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full"
             style="background:rgba(250,78,28,.12);">
            <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2.5"
                 viewBox="0 0 24 24" style="color:#fa4e1c;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
        </div>

        <h1 class="mt-5 font-display text-3xl font-bold" style="color:#222222;">Order Placed!</h1>
        <p class="mt-2 text-base" style="color:#555555;">
            Your order has been placed successfully.<br>
            Please prepare
            <strong style="color:#fa4e1c;">₱{{ number_format($order->total_price, 2) }}</strong>
            (including ₱50.00 delivery fee) upon delivery.
        </p>
        <p class="mt-2 text-sm" style="color:#6b90aa;">
            Order #{{ str_pad($order->id, 6, '0', STR_PAD_LEFT) }}
        </p>
    </div>

    {{-- Status badges --}}
    <div class="mt-6 flex flex-wrap justify-center gap-3">
        <span class="rounded-full px-3 py-1 text-xs font-semibold"
              style="background:{{ $order->statusColor()['bg'] }};color:{{ $order->statusColor()['text'] }};">
            Order: {{ $order->status }}
        </span>
        <span class="rounded-full px-3 py-1 text-xs font-semibold"
              style="background:#e8f0f6;color:#fa4e1c;">
            Payment: {{ $order->payment_status }}
        </span>
        <span class="rounded-full px-3 py-1 text-xs font-semibold"
              style="background:#F5F5F5;color:#666666;">
            💵 Cash on Delivery
        </span>
    </div>

    {{-- Order details card --}}
    <div class="card mt-8 overflow-hidden">

        {{-- Items --}}
        <div class="px-6 py-5">
            <h3 class="font-display text-base font-semibold mb-4" style="color:#222222;">Items Ordered</h3>
            <div class="space-y-3">
                @foreach ($order->items as $item)
                    <div class="flex items-center gap-3">
                        <img src="{{ $item->book && $item->book->image ? asset('storage/'.$item->book->image) : 'https://placehold.co/48x64/FF6300/FFFFFF?text=P' }}"
                             class="h-12 w-9 rounded object-cover flex-shrink-0"
                             alt="{{ $item->book->title ?? '' }}">
                        <div class="flex flex-1 items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold" style="color:#222222;">{{ $item->book->title ?? '—' }}</p>
                                <p class="text-xs" style="color:#6b90aa;">
                                    Qty: {{ $item->quantity }} × ₱{{ number_format($item->price, 2) }}
                                </p>
                            </div>
                            <span class="font-semibold text-sm" style="color:#fa4e1c;">
                                ₱{{ number_format($item->price * $item->quantity, 2) }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Price breakdown --}}
            <div class="mt-4 space-y-1.5 border-t pt-4 text-sm" style="border-color:#dce8f0;">
                <div class="flex justify-between" style="color:#555555;">
                    <span>Subtotal</span>
                    <span>₱{{ number_format($order->subtotal, 2) }}</span>
                </div>
                <div class="flex justify-between" style="color:#555555;">
                    <span>Delivery Fee</span>
                    <span>₱{{ number_format($order->shipping_fee, 2) }}</span>
                </div>
            </div>
            <div class="mt-3 flex justify-between border-t pt-3 font-display text-lg font-bold"
                 style="border-color:#dce8f0;color:#222222;">
                <span>Total (COD)</span>
                <span style="color:#fa4e1c;">₱{{ number_format($order->total_price, 2) }}</span>
            </div>
        </div>

        {{-- Shipping info --}}
        <div class="border-t px-6 py-5" style="border-color:#dce8f0;background:#e8f0f6;">
            <h3 class="font-display text-base font-semibold mb-3" style="color:#222222;">Shipping To</h3>
            <p class="text-sm font-semibold" style="color:#222222;">{{ $order->full_name }}</p>
            <p class="text-sm" style="color:#555555;">{{ $order->phone }} · {{ $order->email }}</p>
            <p class="text-sm mt-1" style="color:#555555;">{{ $order->fullAddress() }}</p>
        </div>
    </div>

    {{-- Track order note --}}
    <div class="mt-6 rounded-xl border p-4 text-sm text-center"
         style="background:rgba(250,78,28,.06);border-color:#fdb49e;">
        <p style="color:#555555;">
            Track your order anytime from
            <a href="{{ route('profile.show') }}" class="font-semibold underline" style="color:#fa4e1c;">
                My Profile → Order History
            </a>.
        </p>
    </div>

    <div class="mt-8 flex flex-wrap justify-center gap-4">
        <a href="{{ route('categories.page') }}"
           class="rounded-xl px-8 py-3 text-sm font-bold text-white transition hover:opacity-90"
           style="background:#002b4d;">Continue Shopping</a>
        <a href="{{ route('profile.show') }}"
           class="rounded-xl border px-8 py-3 text-sm font-bold transition"
           style="border-color:#fa4e1c;color:#fa4e1c;"
           onmouseover="this.style.background='#fff1ee';"
           onmouseout="this.style.background='';">View Orders</a>
    </div>
</div>
</x-layout>
