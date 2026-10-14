<x-layout title="Order Confirmed — ALVY">
@php $grand = $orders->sum('total_price'); @endphp
<div class="mx-auto max-w-2xl px-4 py-14 sm:px-6 lg:px-8">

    <div class="text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full" style="background:rgba(250,78,28,.12);">
            <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="color:#fa4e1c;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <h1 class="mt-5 font-display text-3xl font-bold" style="color:#222;">Order placed!</h1>
        <p class="mt-2 text-base" style="color:#555;">
            @if ($orders->count() > 1)
                Your items come from {{ $orders->count() }} stores, so we made {{ $orders->count() }} orders. Each one ships on its own.<br>
            @endif
            Please prepare <strong style="color:#fa4e1c;">₱{{ number_format($grand, 2) }}</strong> in total (cash on delivery).
        </p>
    </div>

    <div class="mt-8 space-y-4">
        @foreach ($orders as $order)
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b px-5 py-3" style="border-color:#F2EEEA;">
                    <div>
                        <p class="text-sm font-bold" style="color:#222;">Order {{ $order->number() }}</p>
                        <p class="text-xs" style="color:#8a7f78;">{{ $order->seller?->storeName() ?? 'ALVY' }}</p>
                    </div>
                    <a href="{{ route('profile.orders.show', $order->id) }}" class="text-sm font-semibold" style="color:#fa4e1c;">View order →</a>
                </div>
                <div class="space-y-2 px-5 py-3">
                    @foreach ($order->items as $item)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span style="color:#222;">{{ $item->product->title ?? '—' }}@if ($item->variation_name) <span style="color:#8a7f78;">({{ $item->variation_name }})</span>@endif × {{ $item->quantity }}</span>
                            <span style="color:#57534E;">₱{{ number_format($item->price * $item->quantity, 2) }}</span>
                        </div>
                    @endforeach
                </div>
                <dl class="space-y-1 border-t px-5 py-3 text-sm" style="border-color:#F2EEEA;background:#FCFBFA;color:#57534E;">
                    <div class="flex justify-between"><dt>Shipping</dt><dd>₱{{ number_format($order->shipping_fee, 2) }}</dd></div>
                    @if ($order->voucherDiscount() + (float) $order->shipping_discount > 0)
                        <div class="flex justify-between" style="color:#059669;"><dt>Vouchers</dt><dd>−₱{{ number_format($order->voucherDiscount() + (float) $order->shipping_discount, 2) }}</dd></div>
                    @endif
                    <div class="flex justify-between font-semibold" style="color:#222;"><dt>Order total (COD)</dt><dd>₱{{ number_format($order->total_price, 2) }}</dd></div>
                </dl>
            </div>
        @endforeach
    </div>

    <div class="mt-8 flex flex-wrap justify-center gap-3">
        <a href="{{ route('profile.orders') }}" class="rounded-lg px-6 py-3 text-sm font-semibold text-white" style="background:#002b4d;">My purchases</a>
        <a href="{{ route('home') }}" class="rounded-lg border px-6 py-3 text-sm font-semibold" style="border-color:#fa4e1c;color:#fa4e1c;">Continue shopping</a>
    </div>
</div>
</x-layout>
