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
    .co-off { color:#059669; }
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

    <form id="checkoutForm" action="{{ route('checkout.store') }}" method="POST" class="mt-6 grid gap-6 lg:grid-cols-[1fr_380px]"
          data-preview-url="{{ route('checkout.preview') }}">
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
                                    <p class="text-xs" style="color:#8a7f78;">@foreach ($line->priceParts() as [$partQty, $partUnit, $isFlash]){{ $loop->first ? '' : ' + ' }}{{ $peso($partUnit) }} × {{ $partQty }}{{ $isFlash && count($line->priceParts()) > 1 ? ' (flash)' : '' }}@endforeach</p>
                                </div>
                                <p class="text-sm font-semibold" style="color:#222;">{{ $peso($line->subtotal()) }}</p>
                            </div>
                        @endforeach
                    </div>

                    <div class="space-y-3 border-t px-5 py-4 text-sm" style="border-color:#F2EEEA;background:#FCFBFA;">
                        @if ($sellerId)
                            <div class="grid gap-1 sm:grid-cols-[9rem_1fr] sm:items-center">
                                <label class="text-xs font-semibold" style="color:#57534E;" for="sv-{{ $sellerId }}">Store voucher</label>
                                <select id="sv-{{ $sellerId }}" name="seller_vouchers[{{ $sellerId }}]" class="co-select v-select" data-kind="seller">
                                    <option value="">{{ count($storeVouchers) ? 'No store voucher' : 'No vouchers from this store' }}</option>
                                    @foreach ($storeVouchers as $v)
                                        <option value="{{ $v['code'] }}" @disabled($blocks($v)) @selected(($chosen['sellers'][$sellerId] ?? null) === $v['code'])>
                                            {{ $optionText($v) }}{{ $v['reason'] ? ' — ' . $v['reason'] : '' }}
                                        </option>
                                    @endforeach
                                    @if (($code = $chosen['sellers'][$sellerId] ?? null) && ! collect($storeVouchers)->contains('code', $code))
                                        <option value="{{ $code }}" selected>{{ $code }}</option>
                                    @endif
                                </select>
                            </div>
                        @endif
                        <div class="grid gap-1 sm:grid-cols-[9rem_1fr] sm:items-center">
                            <label class="text-xs font-semibold" style="color:#57534E;" for="note-{{ $sellerId }}">Note to seller</label>
                            <div>
                                <input id="note-{{ $sellerId }}" type="text" name="buyer_notes[{{ $sellerId }}]" maxlength="200"
                                       value="{{ old('buyer_notes.' . $sellerId) }}" class="co-select" placeholder="Optional, e.g. Paki-wrap po as gift"
                                       oninput="this.nextElementSibling.textContent = this.value.length + '/200'">
                                <span class="mt-0.5 block text-right text-[10px]" style="color:#a39890;">0/200</span>
                            </div>
                        </div>
                        <div class="co-row" style="color:#57534E;">
                            <span>Shipping <span class="text-xs" style="color:#a39890;" data-store-weight="{{ $sellerId }}">
                                @if ($ship)({{ rtrim(rtrim(number_format($ship['weight'], 2), '0'), '.') }} kg → {{ $ship['chargeable'] }} kg)@endif</span></span>
                            <span data-store-shipping="{{ $sellerId }}">{{ $ship ? $peso($ship['fee']) : '—' }}</span>
                        </div>
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
                    <h2 class="font-display text-lg font-extrabold text-white">Order summary</h2>
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

                        <label class="block text-xs font-semibold" style="color:#57534E;" for="platformVoucher">ALVY voucher</label>
                        <select id="platformVoucher" name="platform_voucher" class="co-select v-select" data-kind="platform">
                            <option value="">{{ count($vouchers['platform']) ? 'No ALVY voucher' : 'No ALVY vouchers right now' }}</option>
                            @foreach ($vouchers['platform'] as $v)
                                <option value="{{ $v['code'] }}" @disabled($blocks($v)) @selected($chosen['platform'] === $v['code'])>
                                    {{ $optionText($v) }}{{ $blocks($v) ? ' — ' . $v['reason'] : '' }}
                                </option>
                            @endforeach
                            @if ($chosen['platform'] && ! collect($vouchers['platform'])->contains('code', $chosen['platform']))
                                <option value="{{ $chosen['platform'] }}" selected>{{ $chosen['platform'] }}</option>
                            @endif
                        </select>

                        <label class="block text-xs font-semibold" style="color:#57534E;" for="shippingVoucher">Shipping voucher</label>
                        <select id="shippingVoucher" name="shipping_voucher" class="co-select v-select" data-kind="shipping">
                            <option value="">{{ count($vouchers['shipping']) ? 'No shipping voucher' : 'No shipping vouchers right now' }}</option>
                            @foreach ($vouchers['shipping'] as $v)
                                <option value="{{ $v['code'] }}" @disabled($blocks($v)) @selected($chosen['shipping'] === $v['code'])>
                                    {{ $optionText($v) }}{{ $blocks($v) ? ' — ' . $v['reason'] : '' }}
                                </option>
                            @endforeach
                            @if ($chosen['shipping'] && ! collect($vouchers['shipping'])->contains('code', $chosen['shipping']))
                                <option value="{{ $chosen['shipping'] }}" selected>{{ $chosen['shipping'] }}</option>
                            @endif
                        </select>

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
                        <div class="co-row"><span>Shipping</span><span data-total="shipping_total">{{ $summary['unserviceable'] ? '—' : $peso($q['shipping_total']) }}</span></div>
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

<script src="{{ asset('js/checkout.js') }}?v=3"></script>
</x-layout>
