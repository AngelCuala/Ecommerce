<x-layout title="Your Cart — ALVY">
<style>
    .ct-card { background:#fff; border:1px solid #ECE7E3; border-radius:.75rem; }
    .ct-qty { display:flex; align-items:center; overflow:hidden; border:1px solid #E7E5E4; border-radius:.5rem; }
    .ct-qty button { width:2rem; height:2rem; color:#57534E; }
    .ct-qty button:hover { background:#F5F2EF; }
    .ct-qty input { width:3rem; border:0; text-align:center; font-size:.875rem; }
    .ct-qty input::-webkit-outer-spin-button, .ct-qty input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
</style>

<div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
    <h1 class="font-display text-3xl font-bold" style="color:#222;">Shopping cart</h1>

    @if ($items->isEmpty())
        <div class="mt-16 flex flex-col items-center gap-4 text-center">
            <p class="font-display text-xl font-semibold" style="color:#222;">Your cart is empty</p>
            <p class="text-sm" style="color:#8a7f78;">Looks like you haven't added anything yet.</p>
            <a href="{{ route('shop.index') }}" class="btn-gold px-8" style="background:#fa4e1c;border-color:#fa4e1c;color:#fff;">Browse items</a>
        </div>
    @else
        {{-- Ticked lines go to checkout; the rest stay in the cart. --}}
        <form id="checkoutPick" action="{{ route('checkout.index') }}" method="GET"></form>

        <div class="mt-8 grid gap-6 lg:grid-cols-[1fr_340px]">
            <div class="space-y-4">
                <label class="flex items-center gap-2 text-sm font-medium" style="color:#57534E;">
                    <input type="checkbox" id="pickAll" class="h-4 w-4 accent-orange-600"> Select all available items
                </label>

                @foreach ($stores as $sellerId => $lines)
                    @php $first = $lines->first(); @endphp
                    <section class="ct-card overflow-hidden" data-store-group>
                        <div class="flex items-center gap-2 border-b px-5 py-3" style="border-color:#F2EEEA;">
                            @if ($sellerId >= 0)
                                <input type="checkbox" class="h-4 w-4 accent-orange-600" data-pick-store aria-label="Select all items from this store">
                            @endif
                            <span class="text-sm font-bold" style="color:#222;">
                                @if ($sellerId > 0)
                                    <a href="{{ route('stores.show', $sellerId) }}" class="hover:underline">{{ $first->product->seller?->storeName() }}</a>
                                @elseif ($sellerId === 0)
                                    ALVY
                                @else
                                    Unavailable items
                                @endif
                            </span>
                        </div>

                        @foreach ($lines as $item)
                            @php
                                $problem = $item->unavailableReason();
                                $stock   = $item->availableStock();
                                $blocked = $problem && ! str_starts_with($problem, 'Only ');
                            @endphp
                            <div class="flex gap-4 border-b px-5 py-4 last:border-b-0" style="border-color:#F7F3F0;{{ $blocked ? 'background:#FAFAF9;' : '' }}">
                                <input type="checkbox" name="items[]" value="{{ $item->id }}" form="checkoutPick"
                                       class="mt-1 h-4 w-4 shrink-0 accent-orange-600" data-pick data-amount="{{ $item->subtotal() }}" data-qty="{{ $item->quantity }}"
                                       @disabled($problem) aria-label="Select {{ $item->product->title ?? 'item' }}">

                                @if ($item->product)
                                    <a href="{{ route('products.show', $item->product->slug) }}" class="shrink-0">
                                        <img src="{{ $item->product->image ? asset('storage/' . $item->product->image) : 'https://placehold.co/96x96/f5f5f5/999?text=P' }}"
                                             class="h-20 w-20 rounded-lg border object-cover {{ $blocked ? 'opacity-50' : '' }}" style="border-color:#F2EEEA;" alt="">
                                    </a>
                                @else
                                    <span class="h-20 w-20 shrink-0 rounded-lg" style="background:#F5F2EF;"></span>
                                @endif

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            @if ($item->product)
                                                <a href="{{ route('products.show', $item->product->slug) }}" class="line-clamp-2 text-sm font-medium hover:text-[#fa4e1c]" style="color:#222;">{{ $item->product->title }}</a>
                                            @else
                                                <p class="text-sm font-medium" style="color:#78716C;">This product was removed by the seller</p>
                                            @endif
                                            @if ($item->variation)
                                                <p class="mt-0.5 text-xs" style="color:#8a7f78;">{{ $item->variation->name }}</p>
                                            @endif
                                            @if ($problem)
                                                <p class="mt-1 inline-block rounded px-1.5 py-0.5 text-[11px] font-semibold" style="background:#FEF2F2;color:#B91C1C;">{{ $problem }}</p>
                                            @endif
                                        </div>
                                        <form action="{{ route('cart.destroy', $item->id) }}" method="POST">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs font-semibold hover:underline" style="color:#DC2626;">Delete</button>
                                        </form>
                                    </div>

                                    @if ($item->product)
                                        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                                            <form action="{{ route('cart.update', $item->id) }}" method="POST" class="ct-qty" data-qty-form>
                                                @csrf @method('PATCH')
                                                <button type="button" data-step="-1" aria-label="Decrease">−</button>
                                                <input type="number" name="quantity" value="{{ $item->quantity }}" min="1" max="{{ max(1, $stock) }}" aria-label="Quantity">
                                                <button type="button" data-step="1" aria-label="Increase">+</button>
                                            </form>
                                            <div class="text-right">
                                                <p class="text-xs" style="color:#8a7f78;">₱{{ number_format($item->unit_price, 2) }} each</p>
                                                <p class="text-base font-bold" style="color:#222;">₱{{ number_format($item->subtotal(), 2) }}</p>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </section>
                @endforeach
            </div>

            <aside class="lg:sticky lg:top-24 lg:self-start">
                <div class="ct-card p-5">
                    <h2 class="font-display text-lg font-bold" style="color:#222;">Summary</h2>
                    <div class="mt-4 flex justify-between text-sm" style="color:#57534E;">
                        <span>Selected (<span id="pickCount">0</span> items)</span>
                        <span class="font-semibold" style="color:#222;" id="pickTotal">₱0.00</span>
                    </div>
                    <p class="mt-2 text-xs" style="color:#8a7f78;">Shipping (per store, by weight and address) and vouchers are added at checkout.</p>
                    <button type="submit" form="checkoutPick" id="pickCheckout" disabled
                            class="mt-5 w-full rounded-xl py-3 text-base font-bold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                            style="background:#fa4e1c;">Check out</button>
                    <a href="{{ route('shop.index') }}" class="mt-3 block text-center text-sm font-medium" style="color:#fa4e1c;">Continue shopping</a>
                </div>
            </aside>
        </div>
    @endif
</div>

<script>
(function () {
    var picks = Array.prototype.slice.call(document.querySelectorAll('[data-pick]'));
    if (!picks.length) return;
    var peso = function (n) { return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

    function update() {
        var total = 0, count = 0;
        picks.forEach(function (p) { if (p.checked) { total += +p.dataset.amount; count += +p.dataset.qty; } });
        document.getElementById('pickTotal').textContent = peso(total);
        document.getElementById('pickCount').textContent = count;
        document.getElementById('pickCheckout').disabled = count === 0;
        document.querySelectorAll('[data-store-group]').forEach(function (g) {
            var box = g.querySelector('[data-pick-store]');
            var lines = Array.prototype.filter.call(g.querySelectorAll('[data-pick]'), function (p) { return !p.disabled; });
            if (box) { box.checked = lines.length > 0 && lines.every(function (p) { return p.checked; }); box.disabled = !lines.length; }
        });
        var enabled = picks.filter(function (p) { return !p.disabled; });
        document.getElementById('pickAll').checked = enabled.length > 0 && enabled.every(function (p) { return p.checked; });
    }

    picks.forEach(function (p) { p.addEventListener('change', update); });
    document.querySelectorAll('[data-pick-store]').forEach(function (box) {
        box.addEventListener('change', function () {
            box.closest('[data-store-group]').querySelectorAll('[data-pick]').forEach(function (p) { if (!p.disabled) p.checked = box.checked; });
            update();
        });
    });
    document.getElementById('pickAll').addEventListener('change', function (e) {
        picks.forEach(function (p) { if (!p.disabled) p.checked = e.target.checked; });
        update();
    });

    // Quantity stepper: never below 1 or above the stock; saves on change.
    document.querySelectorAll('[data-qty-form]').forEach(function (f) {
        var input = f.querySelector('input[name="quantity"]');
        f.querySelectorAll('[data-step]').forEach(function (b) {
            b.addEventListener('click', function () {
                var v = Math.min(Math.max((+input.value || 1) + (+b.dataset.step), 1), +input.max || 99999);
                if (v !== +input.value) { input.value = v; f.submit(); }
            });
        });
        input.addEventListener('change', function () { f.submit(); });
    });

    update();
})();
</script>
</x-layout>
