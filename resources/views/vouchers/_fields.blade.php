{{--
    Voucher form fields, shared by the admin (ALVY vouchers) and seller (store vouchers) forms.
    Expects $voucher (a new or existing App\Models\Voucher).
--}}
@php
    $used  = $voucher->exists && $voucher->used_count > 0;
    $type  = old('discount_type', $voucher->discount_type ?? 'fixed');
    $label = 'block mb-1 text-xs font-semibold';
    $input = 'w-full rounded-lg border px-3 py-2 text-sm';
    $err   = fn ($f) => $errors->first($f);
@endphp
<style>
    .vf-input { border-color: #E6D9CF; background: #fff; }
    .vf-input:focus { outline: none; border-color: #fa4e1c; box-shadow: 0 0 0 3px rgba(250,78,28,.12); }
    .vf-input:disabled { background: #f7f1ed; color: #8a7a70; }
    .vf-err { margin-top: .25rem; font-size: .75rem; color: #DC2626; }
    .vf-hint { margin-top: .25rem; font-size: .72rem; color: #8a7a70; }
</style>

@if ($used)
    <div class="mb-4 rounded-lg border p-3 text-sm" style="background:#FFFBEB;border-color:#FDE68A;color:#92400E;">
        This voucher has been used {{ $voucher->used_count }} {{ \Illuminate\Support\Str::plural('time', $voucher->used_count) }}, so its code and discount can't change.
        You can still change the name, dates, limits and minimum spend, or turn it off.
    </div>
@endif

<div class="grid gap-4 sm:grid-cols-2">
    <div>
        <label class="{{ $label }}" style="color:#3a2f29;" for="code">Voucher code *</label>
        <input id="code" name="code" maxlength="30" value="{{ old('code', $voucher->code) }}" placeholder="e.g. SALE1111"
               class="{{ $input }} vf-input uppercase" @readonly($used) required>
        @if ($err('code'))<p class="vf-err">{{ $err('code') }}</p>@else<p class="vf-hint">Letters, numbers, - and _. Buyers type or pick this at checkout.</p>@endif
    </div>
    <div>
        <label class="{{ $label }}" style="color:#3a2f29;" for="name">Name *</label>
        <input id="name" name="name" maxlength="120" value="{{ old('name', $voucher->name) }}" placeholder="e.g. 11.11 Mega Sale"
               class="{{ $input }} vf-input" required>
        @if ($err('name'))<p class="vf-err">{{ $err('name') }}</p>@endif
    </div>

    <div>
        <label class="{{ $label }}" style="color:#3a2f29;" for="discount_type">Discount type *</label>
        <select id="discount_type" name="discount_type" class="{{ $input }} vf-input" @disabled($used)
                onchange="document.getElementById('maxDiscountBox').style.display = this.value === 'percent' ? '' : 'none'">
            <option value="fixed" @selected($type === 'fixed')>Fixed amount (₱ off)</option>
            <option value="percent" @selected($type === 'percent')>Percent (% off)</option>
        </select>
        @if ($used)<input type="hidden" name="discount_type" value="{{ $voucher->discount_type }}">@endif
    </div>
    <div>
        <label class="{{ $label }}" style="color:#3a2f29;" for="discount_value">Discount *</label>
        <input id="discount_value" name="discount_value" type="number" step="0.01" min="1" max="{{ $type === 'percent' ? 99 : 1000000 }}"
               value="{{ old('discount_value', $voucher->discount_value) }}" placeholder="e.g. 150 or 10"
               class="{{ $input }} vf-input" @readonly($used) required>
        @if ($err('discount_value'))<p class="vf-err">{{ $err('discount_value') }}</p>@else<p class="vf-hint">Pesos for a fixed amount, or the percent for a % discount.</p>@endif
    </div>

    <div id="maxDiscountBox" style="{{ $type === 'percent' ? '' : 'display:none' }}">
        <label class="{{ $label }}" style="color:#3a2f29;" for="max_discount">Maximum discount (₱)</label>
        <input id="max_discount" name="max_discount" type="number" step="0.01" min="1"
               value="{{ old('max_discount', $voucher->max_discount) }}" placeholder="e.g. 200"
               class="{{ $input }} vf-input">
        @if ($err('max_discount'))<p class="vf-err">{{ $err('max_discount') }}</p>@else<p class="vf-hint">Optional cap, e.g. "10% off, up to ₱200".</p>@endif
    </div>
    <div>
        <label class="{{ $label }}" style="color:#3a2f29;" for="min_spend">Minimum spend (₱)</label>
        <input id="min_spend" name="min_spend" type="number" step="0.01" min="0"
               value="{{ old('min_spend', $voucher->min_spend ?: '') }}" placeholder="e.g. 2000"
               class="{{ $input }} vf-input">
        @if ($err('min_spend'))<p class="vf-err">{{ $err('min_spend') }}</p>@else<p class="vf-hint">Leave empty for no minimum.</p>@endif
    </div>

    <div>
        <label class="{{ $label }}" style="color:#3a2f29;" for="starts_at">Starts</label>
        <input id="starts_at" name="starts_at" type="datetime-local"
               value="{{ old('starts_at', $voucher->starts_at?->format('Y-m-d\TH:i')) }}" class="{{ $input }} vf-input">
        @if ($err('starts_at'))<p class="vf-err">{{ $err('starts_at') }}</p>@else<p class="vf-hint">Empty = starts right away.</p>@endif
    </div>
    <div>
        <label class="{{ $label }}" style="color:#3a2f29;" for="ends_at">Ends</label>
        <input id="ends_at" name="ends_at" type="datetime-local"
               value="{{ old('ends_at', $voucher->ends_at?->format('Y-m-d\TH:i')) }}" class="{{ $input }} vf-input">
        @if ($err('ends_at'))<p class="vf-err">{{ $err('ends_at') }}</p>@else<p class="vf-hint">Empty = no end date. For a flash sale, set both.</p>@endif
    </div>

    <div>
        <label class="{{ $label }}" style="color:#3a2f29;" for="usage_limit">Total uses allowed</label>
        <input id="usage_limit" name="usage_limit" type="number" min="1"
               value="{{ old('usage_limit', $voucher->usage_limit) }}" placeholder="e.g. 100"
               class="{{ $input }} vf-input">
        @if ($err('usage_limit'))<p class="vf-err">{{ $err('usage_limit') }}</p>@else<p class="vf-hint">Empty = unlimited.{{ $voucher->exists ? ' Used so far: ' . $voucher->used_count . '.' : '' }}</p>@endif
    </div>
    <div>
        <label class="{{ $label }}" style="color:#3a2f29;" for="per_user_limit">Uses per buyer *</label>
        <input id="per_user_limit" name="per_user_limit" type="number" min="1" max="100"
               value="{{ old('per_user_limit', $voucher->per_user_limit ?? 1) }}" class="{{ $input }} vf-input" required>
        @if ($err('per_user_limit'))<p class="vf-err">{{ $err('per_user_limit') }}</p>@endif
    </div>
</div>

<label class="mt-4 inline-flex cursor-pointer items-center gap-2 text-sm" style="color:#3a2f29;">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" class="h-4 w-4" style="accent-color:#fa4e1c;"
           @checked(old('is_active', $voucher->is_active ?? true))>
    Active (buyers can use it while it's within its dates)
</label>

<label class="mt-2 flex cursor-pointer items-start gap-2 text-sm" style="color:#3a2f29;">
    <input type="hidden" name="is_claimable" value="0">
    <input type="checkbox" name="is_claimable" value="1" class="mt-0.5 h-4 w-4" style="accent-color:#fa4e1c;"
           @checked(old('is_claimable', $voucher->is_claimable ?? true))>
    <span>Show it to buyers (store page, product pages, checkout list)
        <span class="block text-xs" style="color:#8a7f78;">Untick for a code-only voucher: it is hidden and works only when the buyer types the code.</span></span>
</label>

@isset($sellerProducts)
    @php $chosenProducts = array_map('intval', (array) old('product_ids', $voucher->product_ids ?? [])); @endphp
    <div class="mt-4">
        <span class="{{ $label }}" style="color:#3a2f29;">Products</span>
        <p class="vf-hint" style="margin-top:0;">Leave all unticked for every product in your store, or tick the products it works on.</p>
        <div class="mt-2 grid max-h-56 gap-1 overflow-y-auto rounded-lg border p-2 sm:grid-cols-2" style="border-color:#E6D9CF;">
            @forelse ($sellerProducts as $sp)
                <label class="flex items-center gap-2 text-sm" style="color:#3a2f29;">
                    <input type="checkbox" name="product_ids[]" value="{{ $sp->id }}" @checked(in_array($sp->id, $chosenProducts, true)) style="accent-color:#fa4e1c;">
                    <span class="truncate">{{ $sp->title }}</span>
                </label>
            @empty
                <p class="text-xs" style="color:#8a7f78;">No products yet.</p>
            @endforelse
        </div>
        @if ($err('product_ids'))<p class="vf-err">{{ $err('product_ids') }}</p>@endif
        @if ($err('product_ids.0'))<p class="vf-err">{{ $err('product_ids.0') }}</p>@endif
    </div>
@endisset

@if (! empty($allowShipping))
    @php $kind = old('scope', $voucher->scope ?? 'platform'); @endphp
    <div class="mt-4">
        <label class="{{ $label }}" style="color:#3a2f29;" for="scope">What it discounts *</label>
        <select id="scope" name="scope" class="{{ $input }} vf-input" @disabled($used)>
            <option value="platform" @selected($kind === 'platform')>The order (ALVY voucher on any store's items)</option>
            <option value="shipping" @selected($kind === 'shipping')>Shipping fees (free-shipping voucher — set a fixed amount, e.g. ₱50)</option>
        </select>
        @if ($used)<input type="hidden" name="scope" value="{{ $voucher->scope }}">@endif
    </div>
@endif
