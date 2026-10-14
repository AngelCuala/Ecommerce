<x-layout title="Product unavailable — ALVY">
<div class="mx-auto flex max-w-xl flex-col items-center px-4 py-24 text-center">
    <div class="flex h-16 w-16 items-center justify-center rounded-full" style="background:#F5F2EF;">
        <svg class="h-8 w-8" style="color:#a39890;" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
    </div>
    <h1 class="mt-5 font-display text-2xl font-extrabold" style="color:#222;">This product is unavailable</h1>
    <p class="mt-2 text-sm" style="color:#8a7f78;">
        {{ $product?->title ? '"' . $product->title . '" is' : 'This listing is' }} no longer sold, or the seller has taken it down for now.
    </p>
    <div class="mt-6 flex flex-wrap justify-center gap-3">
        @if ($product?->seller_id && $product->seller?->role === 'seller')
            <a href="{{ route('stores.show', $product->seller_id) }}" class="rounded-lg border px-5 py-2.5 text-sm font-semibold" style="border-color:#fa4e1c;color:#fa4e1c;">Visit the store</a>
        @endif
        <a href="{{ route('shop.index') }}" class="rounded-lg px-5 py-2.5 text-sm font-semibold text-white" style="background:#fa4e1c;">Browse other items</a>
    </div>
</div>
</x-layout>
