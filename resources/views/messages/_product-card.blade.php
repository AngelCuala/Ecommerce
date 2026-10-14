{{-- The product a chat is about. --}}
<a href="{{ $product->trashed() ? '#' : route('products.show', $product->slug) }}" class="mb-1 flex w-64 items-center gap-2 rounded-xl border bg-white p-2" style="border-color:#ECE7E3;">
    <img src="{{ $product->image ? asset('storage/' . $product->image) : 'https://placehold.co/48x48/f5f5f5/999?text=P' }}" alt="" class="h-12 w-12 shrink-0 rounded-md object-cover">
    <span class="min-w-0">
        <span class="block truncate text-xs font-semibold" style="color:#222;">{{ $product->title }}</span>
        <span class="block text-xs font-bold" style="color:#fa4e1c;">₱{{ number_format($product->effective_price, 2) }}</span>
    </span>
</a>
