<x-layout title="Your Cart — ALVY">

<div class="mx-auto max-w-6xl px:4 py-12 sm:px-6 lg:px-8 px-4">

    {{-- Breadcrumb --}}
    <nav class="mb-2 text-xs" style="color:rgba(0,0,0,.45);">
        <a href="{{ route('home') }}" style="color:#fa4e1c;" onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">Home</a>
        <span class="mx-1.5">›</span>
        <span style="color:#222222;">Cart</span>
    </nav>
    <h1 class="font-display text-3xl font-bold" style="color:#222222;">Shopping Cart</h1>

    {{-- Progress steps --}}
    <div class="mt-6 flex items-center">
        @foreach (['Cart','Checkout','Confirmation'] as $i => $step)
            @php $active = $i === 0; @endphp
            @if ($i > 0)
                <div class="h-px flex-1" style="background:#dce8f0;"></div>
            @endif
            <div class="flex shrink-0 flex-col items-center gap-1">
                <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
                      style="{{ $active ? 'background:#fa4e1c;color:#FFFFFF;' : 'background:#F5F5F5;color:#6b90aa;' }}">
                    {{ $i + 1 }}
                </span>
                <span class="text-[10px] font-semibold" style="{{ $active ? 'color:#fa4e1c;' : 'color:#6b90aa;' }}">{{ $step }}</span>
            </div>
            @if ($i < 2)
                <div class="h-px flex-1" style="background:#dce8f0;"></div>
            @endif
        @endforeach
    </div>

    @if ($items->isEmpty())
        <div class="mt-20 flex flex-col items-center gap-5 text-center">
            <div class="flex h-20 w-20 items-center justify-center rounded-full" style="background:#e8f0f6;">
                <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" style="color:#fa4e1c;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3h1.6l1.7 10.4a2 2 0 002 1.6h8.6a2 2 0 002-1.6L20.4 7H6"/>
                    <circle cx="9.5" cy="20" r="1.3" fill="currentColor" stroke="none"/>
                    <circle cx="17" cy="20" r="1.3" fill="currentColor" stroke="none"/>
                </svg>
            </div>
            <div>
                <p class="font-display text-xl font-semibold" style="color:#222222;">Your cart is empty</p>
                <p class="mt-1 text-sm" style="color:#6b90aa;">Looks like you haven't added anything yet.</p>
            </div>
            <a href="{{ route('shop.index') }}" class="btn-gold px-8" style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;">Browse Books</a>
        </div>

    @else
        <div class="mt-10 grid gap-8 lg:grid-cols-[1fr_360px]">

            {{-- Items --}}
            <div class="space-y-4">
                @foreach ($items as $item)
                    @php $lineTotal = $item->quantity * $item->book->price; @endphp
                    <div class="card flex gap-5 p-5">
                        {{-- Cover --}}
                        <a href="{{ route('products.show', $item->book->slug) }}" class="shrink-0">
                            <img src="{{ $item->book->image ? asset('storage/' . $item->book->image) : 'https://placehold.co/100x130/FF6300/FFFFFF?text=Book' }}"
                                 class="h-32 w-24 rounded-xl object-cover"
                                 alt="{{ $item->book->title }}">
                        </a>

                        <div class="flex flex-1 flex-col">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <a href="{{ route('products.show', $item->book->slug) }}"
                                       class="font-display font-semibold leading-snug transition-colors"
                                       style="color:#222222;"
                                       onmouseover="this.style.color='#fa4e1c';"
                                       onmouseout="this.style.color='#222222';">
                                        {{ $item->book->title }}
                                    </a>
                                    <p class="mt-0.5 text-xs" style="color:#6b90aa;">by {{ $item->book->author }}</p>
                                    <p class="mt-1 text-sm font-semibold" style="color:#fa4e1c;">${{ number_format($item->book->price, 2) }}</p>
                                </div>

                                {{-- Remove --}}
                                <form action="{{ route('cart.destroy', $item->id) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button type="submit"
                                            class="flex h-7 w-7 items-center justify-center rounded-full border transition"
                                            style="border-color:rgba(220,38,38,.2);color:#DC2626;"
                                            onmouseover="this.style.background='#DC2626';this.style.color='#fff';"
                                            onmouseout="this.style.background='';this.style.color='#DC2626';"
                                            title="Remove" aria-label="Remove {{ $item->book->title }}">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" d="M18 6 6 18M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>

                            {{-- Qty stepper + line total --}}
                            <div class="mt-auto flex items-center justify-between pt-4">
                                <form action="{{ route('cart.update', $item->id) }}" method="POST">
                                    @csrf @method('PATCH')
                                    <div class="flex items-center overflow-hidden rounded-full border" style="border-color:#FFDCC2;background:#e8f0f6;">
                                        <button type="button" aria-label="Decrease"
                                                onclick="const i=this.nextElementSibling;if(+i.value>1){i.value=+i.value-1;this.form.submit();}"
                                                class="w-9 py-1.5 text-center transition"
                                                style="color:#555555;"
                                                onmouseover="this.style.background='#FFE4CC';"
                                                onmouseout="this.style.background='';">−</button>
                                        <input type="number" name="quantity" value="{{ $item->quantity }}" min="1"
                                               onchange="this.form.submit()"
                                               class="w-10 border-0 bg-transparent text-center text-sm font-medium focus:outline-none"
                                               style="color:#222222;">
                                        <button type="button" aria-label="Increase"
                                                onclick="const i=this.previousElementSibling;i.value=+i.value+1;this.form.submit()"
                                                class="w-9 py-1.5 text-center transition"
                                                style="color:#555555;"
                                                onmouseover="this.style.background='#FFE4CC';"
                                                onmouseout="this.style.background='';">+</button>
                                    </div>
                                </form>
                                <span class="font-display text-lg font-bold" style="color:#222222;">${{ number_format($lineTotal, 2) }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="pt-2">
                    <a href="{{ route('shop.index') }}"
                       class="inline-flex items-center gap-2 text-sm font-medium transition"
                       style="color:#fa4e1c;"
                       onmouseover="this.style.color='#d93d0e';"
                       onmouseout="this.style.color='#fa4e1c';">
                        <svg class="h-4 w-4 rotate-180" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L11.29 6.155a.75.75 0 111.02-1.1l4.5 4.25a.75.75 0 010 1.1l-4.5 4.25a.75.75 0 11-1.02-1.1l3.098-3.095H3.75A.75.75 0 013 10z" clip-rule="evenodd"/></svg>
                        Continue Shopping
                    </a>
                </div>
            </div>

            {{-- Order Summary --}}
            <div class="lg:sticky lg:top-24 lg:self-start">
                <div class="card overflow-hidden">
                    <div class="px-6 py-4" style="background:#002b4d;">
                        <h2 class="font-display text-lg font-semibold" style="color:#FFFFFF;">Order Summary</h2>
                    </div>

                    <div class="p-6">
                        {{-- Free shipping bar --}}
                        @php $freeAt = 50; $pct = min(100, round($subtotal / $freeAt * 100)); @endphp
                        @if ($subtotal < $freeAt)
                            <div class="mb-5 rounded-xl p-4" style="background:rgba(250,78,28,.10);">
                                <p class="text-xs font-semibold" style="color:#fa4e1c;">
                                    Add <strong>${{ number_format($freeAt - $subtotal, 2) }}</strong> more for free shipping
                                </p>
                                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full" style="background:#dce8f0;">
                                    <div class="h-full rounded-full transition-all duration-500" style="width:{{ $pct }}%;background:#fa4e1c;"></div>
                                </div>
                            </div>
                        @else
                            <div class="mb-5 flex items-center gap-2 rounded-xl p-4 text-xs font-semibold" style="background:#ECFDF5;color:#059669;">
                                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
                                Free shipping unlocked!
                            </div>
                        @endif

                        <div class="space-y-3 text-sm">
                            <div class="flex justify-between" style="color:#555555;">
                                <span>Subtotal ({{ $items->sum('quantity') }} items)</span>
                                <span class="font-semibold" style="color:#222222;">${{ number_format($subtotal, 2) }}</span>
                            </div>
                            <div class="flex justify-between" style="color:#555555;">
                                <span>Shipping</span>
                                <span class="font-semibold" style="color:{{ $subtotal >= $freeAt ? '#059669' : '#222222' }};">
                                    {{ $subtotal >= $freeAt ? 'Free' : '$5.99' }}
                                </span>
                            </div>
                            <div class="flex justify-between" style="color:#555555;">
                                <span>Tax (8%)</span>
                                <span class="font-semibold" style="color:#222222;">${{ number_format($subtotal * 0.08, 2) }}</span>
                            </div>
                        </div>

                        <div class="mt-4 flex justify-between border-t pt-4" style="border-color:#dce8f0;">
                            <span class="font-display text-lg font-bold" style="color:#222222;">Total</span>
                            <span class="font-display text-xl font-bold" style="color:#fa4e1c;">
                                ${{ number_format($subtotal + ($subtotal >= $freeAt ? 0 : 5.99) + $subtotal * 0.08, 2) }}
                            </span>
                        </div>

                        <a href="{{ route('checkout.index') }}" class="btn-gold mt-6 flex w-full items-center justify-center gap-2 py-3.5 text-base" style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;">
                            Proceed to Checkout
                            <svg class="h-4 w-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M3 10a.75.75 0 01.75-.75h10.638L11.29 6.155a.75.75 0 111.02-1.1l4.5 4.25a.75.75 0 010 1.1l-4.5 4.25a.75.75 0 11-1.02-1.1l3.098-3.095H3.75A.75.75 0 013 10z" clip-rule="evenodd"/></svg>
                        </a>

                        <p class="mt-4 text-center text-[11px]" style="color:#6b90aa;">🔒 Secure checkout</p>
                    </div>
                </div>
            </div>

        </div>
    @endif
</div>

</x-layout>