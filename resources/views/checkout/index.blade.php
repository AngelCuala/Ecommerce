<x-layout title="Checkout — ALVY">
@php
    $q = $summary['quote'];
    $peso = fn ($n) => '₱' . number_format((float) $n, 2);
    $optionText = function (array $v) {
        $text = $v['code'] . ' — ' . $v['label'];
        if ($v['min_spend'] > 0) $text .= ' · min ₱' . number_format($v['min_spend'], 0);
        return $text;
    };
    // A minimum-spend reason can change as other vouchers change, so those stay selectable.
    $blocks = fn (array $v) => $v['reason'] && ! str_starts_with($v['reason'], 'spend ');
@endphp
<style>
    .co-card { background:#fff; border:1px solid #ECE7E3; border-radius:.75rem; }
    .co-addr { display:flex; gap:.75rem; border:1.5px solid #ECE7E3; border-radius:.6rem; padding:.85rem 1rem; cursor:pointer; transition:border-color .15s, background .15s; }
    .co-addr:has(input:checked) { border-color:#fa4e1c; background:#FFF8F4; }
    .co-select { width:100%; border:1px solid #E6D9CF; border-radius:.5rem; padding:.45rem .6rem; font-size:.8125rem; background:#fff; }
    .co-row { display:flex; justify-content:space-between; gap:1rem; }
    .co-row.hidden { display:none; }   /* zero discounts are hidden (checkout.js toggles this) */
    .co-off { color:#059669; }
    .co-qty { display:inline-flex; align-items:center; overflow:hidden; border:1px solid #E7E5E4; border-radius:.5rem; }
    .co-qty button { width:1.75rem; height:1.75rem; color:#57534E; }
    .co-qty button:hover:not(:disabled) { background:#F5F2EF; }
    .co-qty button:disabled { opacity:.35; cursor:not-allowed; }
    .co-qty input { width:3rem; height:1.75rem; border:0; border-left:1px solid #E7E5E4; border-right:1px solid #E7E5E4; text-align:center; font-size:.8125rem; font-weight:600; color:#222; background:#fff; -moz-appearance:textfield; }
    .co-qty input:focus { outline:2px solid #fa4e1c; outline-offset:-2px; }
    .co-qty input::-webkit-outer-spin-button, .co-qty input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }
    /* Voucher pickers */
    .vp-bar { display:flex; width:100%; align-items:center; gap:.6rem; border:1px solid #ECE7E3; border-radius:.6rem; background:#fff; padding:.6rem .75rem; text-align:left; transition:border-color .15s, background .15s; }
    .vp-bar:hover:not(:disabled) { border-color:#F5C4B2; background:#FFFAF8; }
    .vp-bar:disabled { cursor:default; opacity:.75; }
    .vp-ico { display:flex; width:1.9rem; height:1.9rem; flex-shrink:0; align-items:center; justify-content:center; border-radius:.45rem; }
    .vp-ico svg { width:1.05rem; height:1.05rem; }
    .vp-ico-seller, .vp-ico-platform { background:#FFF1EB; color:#E0431A; }
    .vp-ico-shipping { background:#E8F7F4; color:#0F9F8A; }
    .vp-title { flex:1; font-size:.8125rem; font-weight:600; color:#222; }
    .vp-state { font-size:.75rem; color:#a39890; white-space:nowrap; }
    .vp-state.is-applied { border:1px solid #F5A88C; border-radius:.3rem; background:#FFF6F2; padding:.12rem .45rem; font-weight:700; color:#E0431A; }
    .vp-chev { width:1rem; height:1rem; flex-shrink:0; color:#c4b8b0; }
    .vp-modal { position:fixed; inset:0; z-index:70; display:flex; align-items:flex-end; justify-content:center; background:rgba(34,24,18,.5); }
    @media (min-width:640px) { .vp-modal { align-items:center; padding:1.5rem; } }
    .vp-modal.hidden { display:none; }
    .vp-panel { display:flex; width:100%; max-width:34rem; max-height:85vh; flex-direction:column; overflow:hidden; border-radius:1rem 1rem 0 0; background:#F7F5F3; }
    @media (min-width:640px) { .vp-panel { border-radius:1rem; } }
    .vp-head { display:flex; align-items:center; justify-content:space-between; background:#fff; padding:.9rem 1.1rem; border-bottom:1px solid #ECE7E3; }
    .vp-head h3 { font-family:'Nunito',ui-sans-serif,system-ui; font-size:1.05rem; font-weight:800; color:#222; }
    .vp-x { font-size:1.6rem; line-height:1; color:#8a7f78; }
    .vp-list { flex:1; overflow-y:auto; padding:.9rem 1.1rem; display:flex; flex-direction:column; gap:.6rem; }
    .vp-none { display:flex; align-items:center; gap:.6rem; cursor:pointer; border:1px solid #ECE7E3; border-radius:.625rem; background:#fff; padding:.75rem .9rem; font-size:.8125rem; font-weight:600; color:#57534E; }
    .vp-none input, .vp-radio { width:1.1rem; height:1.1rem; accent-color:#fa4e1c; }
    .vp-item { display:block; cursor:pointer; }
    .vp-item.is-blocked { cursor:not-allowed; }
    .vp-item:has(input:checked) .vt, .vp-none:has(input:checked) { border-color:#fa4e1c; box-shadow:0 0 0 1px #fa4e1c; }
    .vp-empty { font-size:.8125rem; color:#8a7f78; } .vp-empty a { color:#fa4e1c; font-weight:600; }
    .vp-foot { display:flex; align-items:center; justify-content:space-between; gap:1rem; background:#fff; border-top:1px solid #ECE7E3; padding:.8rem 1.1rem; }
    .vp-more { font-size:.8125rem; font-weight:600; color:#fa4e1c; } .vp-more:hover { text-decoration:underline; }
    .vp-ok { min-width:6.5rem; border-radius:.5rem; background:#fa4e1c; padding:.55rem 1rem; font-size:.875rem; font-weight:700; color:#fff; } .vp-ok:hover { background:#e2400f; }
</style>

<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">

    <h1 class="font-display text-3xl font-extrabold" style="color:#222;">Checkout</h1>

    @if ($errors->any())
        <div class="mt-5 rounded-lg border p-4 text-sm" style="background:rgba(208,2,27,.06);border-color:rgba(208,2,27,.2);color:#D0021B;">
            <ul class="list-inside list-disc space-y-1">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif
    @if (session('error'))
        <div class="mt-5 rounded-lg border p-4 text-sm" style="background:rgba(208,2,27,.06);border-color:rgba(208,2,27,.2);color:#D0021B;">{{ session('error') }}</div>
    @endif
    @if ($buyNow)
        <div class="mt-5 rounded-lg border p-3 text-sm" style="background:#FFF8F4;border-color:#FDD5C8;color:#9A3412;">
            <strong>Buy now:</strong> you're checking out only this item. Your cart stays as it is.
        </div>
    @endif

    <form id="checkoutForm" action="{{ route('checkout.store') }}" method="POST" enctype="multipart/form-data" class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]"
          data-preview-url="{{ route('checkout.preview') }}" data-quantity-url="{{ route('checkout.quantity') }}">
        @csrf
        <input type="hidden" name="checkout_token" value="{{ $token }}">
        @if ($buyNow)<input type="hidden" name="buy_now" value="1">@endif
        @foreach ($prices as $lineId => $price)
            <input type="hidden" name="prices[{{ $lineId }}]" value="{{ $price }}">
        @endforeach

        <div class="space-y-5">

            {{-- ── Delivery address ── --}}
            <section class="co-card p-5">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 class="font-display text-lg font-extrabold" style="color:#222;">Delivery address</h2>
                    <a href="{{ route('profile.addresses', ['return' => 'checkout']) }}" class="text-sm font-semibold" style="color:#fa4e1c;">+ Add / manage addresses</a>
                </div>

                @forelse ($addresses as $a)
                    <label class="co-addr mb-2">
                        <input type="radio" name="address_id" value="{{ $a->id }}" class="mt-1 accent-orange-600" @checked($address && $address->id === $a->id) required>
                        <span class="min-w-0 text-sm">
                            <span class="font-semibold" style="color:#222;">{{ $a->full_name }}</span>
                            <span style="color:#8a7f78;">· {{ $a->phone }}</span>
                            <span class="ml-1 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase" style="background:#F5F2EF;color:#57534E;">{{ $a->label }}</span>
                            @if ($a->is_default)<span class="ml-1 rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase" style="background:#FFF1EB;color:#E0431A;">Default</span>@endif
                            <span class="mt-0.5 block" style="color:#57534E;">{{ $a->full_address }}</span>
                            @unless ($a->municipality_code)
                                <span class="mt-1 block text-xs" style="color:#B45309;">Edit this address and pick the region, city and barangay from the lists to use it.</span>
                            @endunless
                        </span>
                    </label>
                @empty
                    <div class="rounded-lg border border-dashed p-5 text-center text-sm" style="border-color:#E7E5E4;color:#57534E;">
                        You have no saved address yet.
                        <a href="{{ route('profile.addresses', ['return' => 'checkout']) }}" class="font-semibold" style="color:#fa4e1c;">Add your address</a> to continue.
                    </div>
                @endforelse

                <p id="unserviceableMsg" class="mt-2 rounded-lg px-3 py-2 text-sm {{ $summary['unserviceable'] ? '' : 'hidden' }}"
                   style="background:#FEF2F2;color:#B91C1C;">{{ $summary['unserviceable'] }}</p>
            </section>

            {{-- ── One block per store ── --}}
            @foreach ($summary['groups'] as $sellerId => $group)
                @php $ship = $summary['shipping'][$sellerId] ?? null; $storeVouchers = $vouchers['sellers'][$sellerId]['vouchers'] ?? []; @endphp
                <section class="co-card overflow-hidden" data-store="{{ $sellerId }}">
                    <div class="flex items-center gap-2 border-b px-5 py-3" style="border-color:#F2EEEA;">
                        <svg class="h-4 w-4" style="color:#fa4e1c;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9l1.5-5h15L21 9M3 9h18M3 9a3 3 0 006 0 3 3 0 006 0 3 3 0 006 0M5 12v8h14v-8"/></svg>
                        @if ($sellerId)
                            <a href="{{ route('stores.show', $sellerId) }}" class="text-sm font-bold hover:underline" style="color:#222;">{{ $group['name'] }}</a>
                        @else
                            <span class="text-sm font-bold" style="color:#222;">{{ $group['name'] }}</span>
                        @endif
                    </div>

                    <div class="divide-y px-5" style="--tw-divide-opacity:1;">
                        @foreach ($group['items'] as $line)
                            <div class="flex items-center gap-3 py-3" style="border-color:#F7F3F0;">
                                <img src="{{ $line->product->image ? asset('storage/' . $line->product->image) : 'https://placehold.co/64x64/f5f5f5/999?text=P' }}"
                                     class="h-14 w-14 shrink-0 rounded-md border object-cover" style="border-color:#F2EEEA;" alt="">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium" style="color:#222;">{{ $line->product->title }}</p>
                                    @if ($line->variation)<p class="text-xs" style="color:#8a7f78;">{{ $line->variation->name }}</p>@endif
                                    @php $lineKey = $buyNow ? 'buy' : (string) $line->id; $stock = $line->availableStock(); @endphp
                                    <p class="text-xs" style="color:#8a7f78;" data-line-price="{{ $lineKey }}">@foreach ($line->priceParts() as [$partQty, $partUnit, $isFlash]){{ $loop->first ? '' : ' + ' }}{{ $peso($partUnit) }} × {{ $partQty }}{{ $isFlash && count($line->priceParts()) > 1 ? ' (flash)' : '' }}@endforeach</p>
                                    {{-- − / + or type the amount: checkout.js saves the new quantity and updates the page in place. --}}
                                    <div class="co-qty mt-1.5" data-qty-line="{{ $lineKey }}" data-qty="{{ $line->quantity }}" data-stock="{{ $stock }}">
                                        <button type="button" data-qty-step="-1" aria-label="Decrease" @disabled($line->quantity <= 1)>−</button>
                                        <input type="number" data-qty-value value="{{ $line->quantity }}" min="1" max="{{ max(1, $stock) }}" step="1" inputmode="numeric" aria-label="Quantity">
                                        <button type="button" data-qty-step="1" aria-label="Increase" @disabled($line->quantity >= $stock)>+</button>
                                    </div>
                                    <p class="mt-1 hidden text-xs" style="color:#DC2626;" data-qty-msg="{{ $lineKey }}"></p>
                                </div>
                                <p class="text-sm font-semibold" style="color:#222;" data-line-subtotal="{{ $lineKey }}">{{ $peso($line->subtotal()) }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="space-y-3 border-t px-5 py-4 text-sm" style="border-color:#F2EEEA;background:#FCFBFA;">
                        @if ($sellerId)
                            @include('checkout._voucher-picker', [
                                'kind' => 'seller', 'id' => 'sv-' . $sellerId, 'name' => 'seller_vouchers[' . $sellerId . ']',
                                'title' => 'Store voucher', 'options' => $storeVouchers, 'chosen' => $chosen['sellers'][$sellerId] ?? null,
                                'where' => 'Only at ' . $group['name'], 'blocks' => $blocks,
                            ])
                        @endif
                        <div>
                            <label class="mb-1 flex items-center justify-between text-xs font-semibold" style="color:#57534E;" for="note-{{ $sellerId }}">
                                <span>Message to seller <span class="font-normal" style="color:#a39890;">(optional)</span></span>
                                <span class="font-normal" style="color:#a39890;" data-note-count>{{ mb_strlen(old('buyer_notes.' . $sellerId, '')) }}/200</span>
                            </label>
                            <input id="note-{{ $sellerId }}" type="text" name="buyer_notes[{{ $sellerId }}]" maxlength="200"
                                   value="{{ old('buyer_notes.' . $sellerId) }}" class="co-select" placeholder="e.g. Paki-wrap po as gift"
                                   oninput="this.previousElementSibling.querySelector('[data-note-count]').textContent = this.value.length + '/200'">
                        </div>
                        {{-- Always shown: the fee depends on how far the buyer is from this store. --}}
                        <div class="co-row" style="color:#57534E;" data-store-shipping-row="{{ $sellerId }}">
                            <span>Shipping <span class="text-xs" style="color:#a39890;" data-store-weight="{{ $sellerId }}">
                                @if ($ship)· {{ $ship['route'] }} · {{ $ship['chargeable'] }} kg @endif</span></span>
                            <span data-store-shipping="{{ $sellerId }}" @unless ($ship) style="color:#a39890;" @endunless>{{ $ship ? $peso($ship['fee']) : 'Select an address' }}</span>
                        </div>
                        @php $storePromo = $q['groups'][$sellerId] ?? null; @endphp
                        @if ($storePromo && $storePromo['promo'] > 0)
                            <div class="co-row co-off" data-store-promo-row="{{ $sellerId }}">
                                <span>{{ implode(', ', $storePromo['promo_labels']) }}</span><span>−{{ $peso($storePromo['promo']) }}</span>
                            </div>
                        @endif
                        <div class="co-row co-off {{ ($q['seller_discounts'][$sellerId] ?? 0) > 0 ? '' : 'hidden' }}" data-store-discount-row="{{ $sellerId }}">
                            <span>Store voucher</span><span data-store-discount="{{ $sellerId }}">−{{ $peso($q['seller_discounts'][$sellerId] ?? 0) }}</span>
                        </div>

                    </div>
                </section>
            @endforeach

            {{-- ── Payment ── --}}
            <section class="co-card p-5">
                <h2 class="mb-3 font-display text-lg font-extrabold" style="color:#222;">Payment method</h2>
                <label class="co-addr mb-2">
                    <input type="radio" name="payment_method" value="cod" class="mt-1 accent-orange-600" @checked(old('payment_method', 'cod') === 'cod') required>
                    <span class="text-sm">
                        <span class="font-semibold" style="color:#222;">Cash on Delivery (COD)</span>
                        <span class="block text-xs" style="color:#8a7f78;">Pay in cash when each parcel arrives. No advance payment needed.</span>
                    </span>
                </label>
                @if ($gcash)
                    <label class="co-addr mb-2">
                        <input type="radio" name="payment_method" value="gcash_receipt" class="mt-1 accent-orange-600" @checked(old('payment_method') === 'gcash_receipt') data-gcash-radio>
                        <span class="text-sm">
                            <span class="font-semibold" style="color:#222;">GCash</span>
                            <span class="block text-xs" style="color:#8a7f78;">Scan ALVY's GCash QR, send the total, then upload the receipt and its Ref No. ALVY checks the payment, then the {{ count($summary['groups']) > 1 ? 'sellers prepare' : 'seller prepares' }} your order.</span>
                        </span>
                    </label>

                    {{-- GCash (shown when GCash is chosen): ALVY's QR, the total, and the receipt for it. --}}
                    <div class="mb-2 hidden rounded-xl border p-4" style="border-color:#BFDBFE;background:#F5F9FF;" data-gcash-box>
                        <p class="mb-3 flex items-center gap-2 text-sm font-bold" style="color:#0B2A5B;">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md text-xs font-extrabold" style="color:#fff;background:#007DFE;" aria-hidden="true">G</span>
                            Pay ALVY with GCash
                        </p>
                        <div class="grid gap-4 sm:grid-cols-[150px_1fr]">
                            <a href="{{ $gcash['qr_url'] }}" target="_blank" rel="noopener" title="Open the QR bigger">
                                <img src="{{ $gcash['qr_url'] }}" alt="ALVY GCash QR" class="w-full rounded-lg border bg-white object-contain" style="border-color:#BFDBFE;" data-gcash-qr>
                            </a>
                            <div class="min-w-0 space-y-3">
                                <div class="text-sm" style="color:#1E3A8A;">
                                    <p>Scan the QR in your GCash app and send exactly</p>
                                    <p class="font-display text-2xl font-extrabold" style="color:#0B2A5B;" data-gcash-pay>{{ $peso($gcashTotal) }}</p>
                                    <p class="text-xs" style="color:#3B5B8C;">to {{ $gcash['name'] }}@if ($gcash['number']) · <span class="font-mono">{{ $gcash['number'] }}</span>
                                        <button type="button" class="ml-1 rounded px-1.5 py-0.5 font-sans text-[11px] font-semibold" style="color:#007DFE;background:#E0EDFF;"
                                                onclick="navigator.clipboard && navigator.clipboard.writeText('{{ $gcash['number'] }}').then(() => { this.textContent = 'Copied'; setTimeout(() => this.textContent = 'Copy', 1500); })">Copy</button>@endif
                                    </p>
                                    <p class="mt-1 text-xs" style="color:#3B5B8C;" data-gcash-exact>Send this exact amount, centavos included: they mark your payment so ALVY finds it fast. ALVY covers the centavos below your total (<span data-gcash-orders>₱{{ number_format($gcashOrders, 2) }}</span>).</p>
                                    @if (count($summary['groups']) > 1)
                                        <p class="mt-1 text-xs" style="color:#3B5B8C;">One payment for all {{ count($summary['groups']) }} stores, shipping included.</p>
                                    @endif
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold" style="color:#1E3A8A;" for="gcash-proof">Screenshot of the GCash receipt *</label>
                                    <input type="file" id="gcash-proof" name="payment_proof" accept="image/jpeg,image/png,image/webp" data-gcash-file
                                           class="block w-full rounded-lg border bg-white px-3 py-2 text-xs" style="border-color:#BFDBFE;">
                                    @error('payment_proof')<p class="mt-1 text-xs font-medium" style="color:#B91C1C;">{{ $message }}</p>@else<p class="mt-1 text-[11px]" style="color:#5B7BA8;">JPG, PNG or WebP, up to 5 MB. The amount and Ref No. must be visible.</p>@enderror
                                    <img alt="Receipt preview" class="mt-2 hidden max-h-40 rounded-lg border" style="border-color:#BFDBFE;" data-gcash-preview>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold" style="color:#1E3A8A;" for="gcash-ref">GCash Ref No. *</label>
                                    {{-- Numbers only: anything else is removed as it is typed or pasted. --}}
                                    <input type="text" id="gcash-ref" name="payment_reference" value="{{ old('payment_reference') }}" inputmode="numeric" maxlength="13" pattern="\d{13}"
                                           title="The 13 numbers of the GCash Ref No." data-gcash-ref oninput="this.value = this.value.replace(/\D/g, '').slice(0, 13)"
                                           placeholder="13 numbers, e.g. 1012345678901" autocomplete="off" class="block w-full rounded-lg border bg-white px-3 py-2 font-mono text-sm" style="border-color:#BFDBFE;">
                                    @error('payment_reference')<p class="mt-1 text-xs font-medium" style="color:#B91C1C;">{{ $message }}</p>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                    <script>
                    (function () {
                        // GCash chosen: show ALVY's QR box; the receipt and Ref No. become required.
                        var box = document.querySelector('[data-gcash-box]');
                        var file = box.querySelector('[data-gcash-file]'), preview = box.querySelector('[data-gcash-preview]');
                        function sync() {
                            var on = document.querySelector('[data-gcash-radio]').checked;
                            box.classList.toggle('hidden', !on);
                            box.querySelectorAll('[data-gcash-file], [data-gcash-ref]').forEach(function (i) { i.required = on; });
                        }
                        document.querySelectorAll('input[name="payment_method"]').forEach(function (r) { r.addEventListener('change', sync); });
                        file.addEventListener('change', function () {
                            var f = file.files && file.files[0];
                            if (!f) { preview.classList.add('hidden'); return; }
                            preview.src = URL.createObjectURL(f); preview.classList.remove('hidden');
                        });
                        sync();
                    })();
                    </script>
                @endif
                @foreach ($paymentMethods as $code => $label)
                    <label class="co-addr mb-2">
                        <input type="radio" name="payment_method" value="{{ $code }}" class="mt-1 accent-orange-600" @checked(old('payment_method') === $code)>
                        <span class="text-sm">
                            <span class="font-semibold" style="color:#222;">{{ $label }}</span>
                            <span class="block text-xs" style="color:#8a7f78;">Pay now on the secure payment page. Unpaid orders are cancelled after {{ config('payments.unpaid_minutes') }} minutes.</span>
                        </span>
                    </label>
                @endforeach
                @if (config('payments.gateway') === 'sandbox')
                    <p class="rounded-md px-3 py-2 text-xs font-semibold" style="background:#FEF3C7;color:#92400E;">TEST MODE: online payments use the test gateway — no real money moves.</p>
                @endif
            </section>
        </div>

        {{-- ── Summary ── --}}
        <aside class="lg:sticky lg:top-24 lg:self-start">
            <div class="co-card overflow-hidden">
                <div class="px-5 py-4" style="background:#002b4d;">
                    <h2 class="font-display text-lg font-extrabold" style="color:#fff;">Order summary</h2>
                </div>
                <div class="space-y-4 p-5">

                    {{-- Vouchers --}}
                    <div class="space-y-2">
                        <p class="text-sm font-bold" style="color:#222;">Vouchers</p>
                        @php $best = $vouchers['best']; @endphp
                        @if ($best['discount'] > 0 && ! session()->has('checkout_vouchers') && $chosen['platform'] === $best['platform'])
                            <p class="rounded-md px-2.5 py-1.5 text-xs font-semibold" style="background:#ECFDF5;color:#047857;">
                                ✓ Best vouchers applied automatically — you save ₱{{ number_format($best['discount'], 2) }}.
                                <span class="font-normal">You can change them below.</span>
                            </p>
                        @endif
                        <div id="voucherNotices" class="space-y-1">
                            @foreach ($q['notices'] as $notice)
                                <p class="rounded-md px-2.5 py-1.5 text-xs" style="background:#FFFBEB;color:#92400E;">{{ $notice }}</p>
                            @endforeach
                        </div>

                        @include('checkout._voucher-picker', [
                            'kind' => 'platform', 'id' => 'platformVoucher', 'name' => 'platform_voucher',
                            'title' => 'ALVY discount voucher', 'options' => $vouchers['platform'], 'chosen' => $chosen['platform'],
                            'where' => 'All stores', 'blocks' => $blocks,
                        ])
                        @include('checkout._voucher-picker', [
                            'kind' => 'shipping', 'id' => 'shippingVoucher', 'name' => 'shipping_voucher',
                            'title' => 'Free shipping voucher', 'options' => $vouchers['shipping'], 'chosen' => $chosen['shipping'],
                            'where' => 'Any order', 'blocks' => $blocks,
                        ])

                        <div class="flex gap-2 pt-1">
                            <input type="text" id="voucherCode" maxlength="30" placeholder="Have a code?" class="co-select uppercase" autocomplete="off">
                            <button type="button" id="voucherApply" class="shrink-0 rounded-lg border px-3 text-sm font-semibold" style="border-color:#fa4e1c;color:#fa4e1c;">Apply</button>
                        </div>
                        <p id="voucherCodeMsg" class="hidden text-xs"></p>
                    </div>

                    {{-- Totals --}}
                    <div class="space-y-1.5 border-t pt-4 text-sm" style="border-color:#F2EEEA;color:#57534E;">
                        <div class="co-row"><span>Merchandise subtotal</span><span data-total="subtotal">{{ $peso($q['merchandise']) }}</span></div>
                        <div class="co-row co-off {{ $q['promo_discount'] > 0 ? '' : 'hidden' }}" data-row="promo_discount"><span>Bundle deals &amp; promos</span><span data-total="promo_discount">−{{ $peso($q['promo_discount']) }}</span></div>
                        <div class="co-row" data-row="shipping_total"><span>Shipping</span><span data-total="shipping_total" @if ($summary['unserviceable']) style="color:#a39890;" @endif>{{ $summary['unserviceable'] ? 'Select an address' : $peso($q['shipping_total']) }}</span></div>
                        <div class="co-row co-off {{ $q['seller_discount'] > 0 ? '' : 'hidden' }}" data-row="seller_discount"><span>Store vouchers</span><span data-total="seller_discount">−{{ $peso($q['seller_discount']) }}</span></div>
                        <div class="co-row co-off {{ $q['platform_discount'] > 0 ? '' : 'hidden' }}" data-row="platform_discount"><span>ALVY voucher</span><span data-total="platform_discount">−{{ $peso($q['platform_discount']) }}</span></div>
                        <div class="co-row co-off {{ $q['shipping_discount'] > 0 ? '' : 'hidden' }}" data-row="shipping_discount"><span>Shipping voucher</span><span data-total="shipping_discount">−{{ $peso($q['shipping_discount']) }}</span></div>
                    </div>
                    <div class="co-row border-t pt-3 font-display text-xl font-extrabold" style="border-color:#F2EEEA;color:#222;">
                        <span>Total</span><span style="color:#fa4e1c;" data-total="total">{{ $peso($q['total']) }}</span>
                    </div>
                    @if (count($summary['groups']) > 1)
                        <p class="text-xs" style="color:#8a7f78;">Each store ships separately, so this becomes {{ count($summary['groups']) }} orders.</p>
                    @endif

                    <button type="submit" id="placeOrder" @disabled($summary['unserviceable'])
                            class="w-full rounded-xl py-3.5 text-base font-bold text-white transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50"
                            style="background:#002b4d;">Place Order</button>
                    <p class="text-center text-[11px]" style="color:#a39890;">🔒 Secure checkout</p>
                </div>
            </div>
        </aside>
    </form>

</div>

<script src="{{ asset('js/checkout.js') }}?v=10"></script>
<script src="{{ asset('js/voucher-picker.js') }}?v=2"></script>
</x-layout>
