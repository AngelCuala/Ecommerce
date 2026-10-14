<x-seller-layout title="Flash Sales" active="flash">
@include('partials.panel.plain')

<div class="mx-auto max-w-5xl space-y-5">
    <div>
        <h1 class="font-display text-2xl font-bold" style="color:#222;">Flash sales</h1>
        <p class="text-sm pl-muted">Sell a product at a flash price for a short time, for a limited number of units. While units are left,
            the flash price beats your sale price; buyers can ask to be reminded before it starts.</p>
    </div>

    @if (session('success'))<div class="rounded-md px-4 py-2.5 text-sm" style="background:#ECFDF5;color:#047857;">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="rounded-md px-4 py-2.5 text-sm" style="background:#FEF2F2;color:#B91C1C;">{{ session('error') }}</div>@endif

    <section class="pl-card">
        <div class="pl-head"><h2>New flash sale</h2></div>
        <form action="{{ route('seller.flash-sales.store') }}" method="POST" class="pl-body grid gap-3 sm:grid-cols-2">
            @csrf
            <div class="sm:col-span-2">
                <label class="pl-label" for="fs-product">Product</label>
                <select id="fs-product" name="product_id" class="input text-sm" required>
                    <option value="">Choose a product</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->title }} — now ₱{{ number_format($p->regular_price, 2) }} · {{ $p->stock }} in stock</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs pl-muted">Products with options (e.g. sizes) can't have a flash sale yet.</p>
                @error('product_id')<p class="text-xs" style="color:#B91C1C;">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="pl-label" for="fs-price">Flash price (₱)</label>
                <input id="fs-price" type="number" name="flash_price" step="0.01" min="1" value="{{ old('flash_price') }}" class="input text-sm" required>
                @error('flash_price')<p class="text-xs" style="color:#B91C1C;">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="pl-label" for="fs-qty">Units at this price</label>
                <input id="fs-qty" type="number" name="quantity" min="1" value="{{ old('quantity') }}" class="input text-sm" required>
                @error('quantity')<p class="text-xs" style="color:#B91C1C;">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="pl-label" for="fs-start">Starts</label>
                <input id="fs-start" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" min="{{ now()->format('Y-m-d\TH:i') }}" class="input text-sm" required>
                @error('starts_at')<p class="text-xs" style="color:#B91C1C;">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="pl-label" for="fs-end">Ends</label>
                <input id="fs-end" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="input text-sm" required>
                @error('ends_at')<p class="text-xs" style="color:#B91C1C;">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2"><button class="pl-btn">Schedule flash sale</button></div>
        </form>
    </section>

    <section class="pl-card overflow-hidden">
        <div class="pl-head"><h2>Your flash sales</h2></div>
        <table class="pl-table w-full text-sm">
            <thead><tr><th class="text-left">Product</th><th class="text-left">When</th><th class="text-right">Price</th><th class="text-right">Sold</th><th class="text-right">Reminders</th><th class="text-left">State</th><th></th></tr></thead>
            <tbody>
            @forelse ($sales as $s)
                <tr>
                    <td>{{ $s->product->title ?? 'Removed product' }}</td>
                    <td class="whitespace-nowrap text-xs">{{ $s->starts_at->format('M j, g:i A') }} – {{ $s->ends_at->format('M j, g:i A') }}</td>
                    <td class="text-right">₱{{ number_format($s->flash_price, 2) }}</td>
                    <td class="text-right">{{ $s->sold }} / {{ $s->quantity }}</td>
                    <td class="text-right">{{ $s->reminders_count }}</td>
                    <td>{{ $s->stateLabel() }}</td>
                    <td class="text-right">
                        @if ($s->sold === 0)
                            <form action="{{ route('seller.flash-sales.destroy', $s) }}" method="POST" onsubmit="return confirm('Remove this flash sale?')">
                                @csrf @method('DELETE')
                                <button class="text-xs font-semibold" style="color:#DC2626;">Remove</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center pl-muted">No flash sales yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
    <div>{{ $sales->links() }}</div>
</div>
</x-seller-layout>
