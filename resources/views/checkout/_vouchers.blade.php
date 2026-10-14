{{--
    Voucher pickers for the checkout summary. Amounts shown here are a preview;
    CheckoutController / VoucherService work out the real discount when the order is placed.
--}}
@php
    $optionText = function (array $v) {
        $text = $v['code'] . ' — ' . $v['label'];
        if ($v['min_spend'] > 0) $text .= ' · min ₱' . number_format($v['min_spend'], 0);
        return $text;
    };
@endphp

<div class="mt-5 border-t pt-4" style="border-color:#EFEFEF;" id="voucherBox">
    <p class="mb-2 flex items-center gap-1.5 text-sm font-bold" style="color:#222;">
        <svg class="h-4 w-4" style="color:#fa4e1c;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
        Vouchers
    </p>

    {{-- Store vouchers: one per seller, only on that seller's items --}}
    @foreach ($vouchers['sellers'] as $sellerId => $store)
        <label class="mb-1 block text-xs font-semibold" style="color:#555;">{{ $store['name'] }} voucher</label>
        <select name="seller_vouchers[{{ $sellerId }}]" class="v-select mb-1 w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E6D9CF;"
                data-kind="seller">
            <option value="" data-discount="0">No store voucher</option>
            @foreach ($store['vouchers'] as $v)
                <option value="{{ $v['code'] }}" data-discount="{{ $v['discount'] }}" @disabled($v['reason'])
                        @selected(old("seller_vouchers.$sellerId") === $v['code'])>
                    {{ $optionText($v) }}{{ $v['reason'] ? ' — ' . $v['reason'] : '' }}
                </option>
            @endforeach
        </select>
        @error("seller_vouchers.$sellerId")<p class="mb-1 text-xs" style="color:#DC2626;">{{ $message }}</p>@enderror
    @endforeach

    {{-- ALVY vouchers: on the whole order --}}
    <label class="mb-1 mt-2 block text-xs font-semibold" style="color:#555;">ALVY voucher</label>
    <select name="platform_voucher" id="platformVoucher" class="v-select w-full rounded-lg border px-3 py-2 text-sm" style="border-color:#E6D9CF;"
            data-kind="platform">
        <option value="">{{ count($vouchers['platform']) ? 'No ALVY voucher' : 'No ALVY vouchers right now' }}</option>
        @foreach ($vouchers['platform'] as $v)
            <option value="{{ $v['code'] }}" data-type="{{ $v['type'] }}" data-value="{{ $v['value'] }}"
                    data-max="{{ $v['max'] }}" data-min="{{ $v['min_spend'] }}"
                    @disabled($v['reason'] && ! str_starts_with($v['reason'], 'needs a minimum'))
                    @selected(old('platform_voucher') === $v['code'])>
                {{ $optionText($v) }}{{ $v['reason'] && ! str_starts_with($v['reason'], 'needs a minimum') ? ' — ' . $v['reason'] : '' }}
            </option>
        @endforeach
    </select>
    @error('platform_voucher')<p class="mt-1 text-xs" style="color:#DC2626;">{{ $message }}</p>@enderror
    <p class="mt-1 hidden text-xs" style="color:#B45309;" id="platformNote"></p>

    {{-- Typing a code: picks it in the right list, or is sent as-is so the server can explain. --}}
    <div class="mt-2 flex gap-2">
        <input type="text" id="voucherCode" maxlength="30" placeholder="Have a code?"
               class="w-full rounded-lg border px-3 py-2 text-sm uppercase" style="border-color:#E6D9CF;">
        <button type="button" id="voucherApply" class="shrink-0 rounded-lg border px-3 py-2 text-sm font-semibold"
                style="border-color:#fa4e1c;color:#fa4e1c;">Apply</button>
    </div>
    <p class="mt-1 hidden text-xs" id="voucherCodeMsg"></p>
</div>
