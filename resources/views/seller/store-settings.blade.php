<x-seller-layout title="Store Settings" active="store">
@php
    $a = $application;
    $oldAddress = [
        'region'       => old('region', $a->region),
        'province'     => old('province', $a->province),
        'municipality' => old('municipality', $a->municipality),
        'barangay'     => old('barangay', $a->barangay),
    ];
@endphp
<style>
    .ss-card { background:#fff; border:1px solid #ECE7E3; border-radius:.75rem; }
    .ss-head { padding:1rem 1.5rem; border-bottom:1px solid #F2EEEA; }
    .ss-head h2 { font-size:1rem; font-weight:700; }
    .ss-head p { margin-top:.15rem; font-size:.8125rem; color:#8a7f78; }
    .ss-body { padding:1.25rem 1.5rem; }
    .ss-label { display:block; margin-bottom:.3rem; font-size:.8125rem; font-weight:500; color:#44403C; }
    .ss-note { margin-top:.3rem; font-size:.75rem; color:#8a7f78; }
    .ss-error { margin-top:.3rem; font-size:.75rem; color:#B91C1C; }
    .input.is-invalid { border-color:#DC2626; }
</style>

<div class="mx-auto max-w-3xl">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <span class="section-eyebrow">Store</span>
            <h1 class="text-2xl">Store Settings</h1>
            <p class="text-sm" style="color:#8a7f78;">What buyers see on your store page, and where couriers pick up your parcels.</p>
        </div>
        <a href="{{ route('stores.show', auth()->id()) }}" target="_blank" class="btn-outline" style="padding:.5rem 1rem;">View store page</a>
    </div>

    @if ($errors->any())<div class="mb-4 rounded-lg px-4 py-2.5 text-sm" style="background:#FEF2F2;color:#B91C1C;">Please fix the highlighted fields. Nothing was saved.</div>@endif

    <form method="POST" action="{{ route('seller.store-settings.update') }}" enctype="multipart/form-data" class="space-y-5" novalidate>
        @csrf @method('PATCH')

        <section class="ss-card">
            <div class="ss-head"><h2>Store profile</h2><p>Shown on your public store page.</p></div>
            <div class="ss-body space-y-4">
                <div>
                    <label class="ss-label" for="shop_name">Store name *</label>
                    <input id="shop_name" name="shop_name" maxlength="30" value="{{ old('shop_name', $a->shop_name) }}" class="input @error('shop_name') is-invalid @enderror">
                    @error('shop_name')<p class="ss-error">{{ $message }}</p>@else<p class="ss-note">3 to 30 characters. {{ $a->shopNameChangesRemaining() }} of 3 name changes left this month.</p>@enderror
                </div>
                <div>
                    <label class="ss-label" for="description">Description</label>
                    <textarea id="description" name="description" rows="4" maxlength="500" class="input @error('description') is-invalid @enderror">{{ old('description', $a->description) }}</textarea>
                    @error('description')<p class="ss-error">{{ $message }}</p>@else<p class="ss-note">Plain text, up to 500 characters.</p>@enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach (['logo' => 'Logo', 'banner' => 'Banner'] as $field => $label)
                        <div>
                            <label class="ss-label" for="{{ $field }}">{{ $label }}</label>
                            @if ($a->{$field . '_path'})
                                <img src="{{ asset('storage/' . $a->{$field . '_path'}) }}" alt="Current {{ strtolower($label) }}" class="mb-2 {{ $field === 'logo' ? 'h-16 w-16 rounded-full' : 'h-16 w-full rounded-lg' }} object-cover" data-current-{{ $field }}>
                            @endif
                            <input type="file" id="{{ $field }}" name="{{ $field }}" accept=".jpg,.jpeg,.png,image/jpeg,image/png" class="block w-full text-sm">
                            @error($field)<p class="ss-error">{{ $message }}</p>@else<p class="ss-note">JPG or PNG, up to 5 MB.</p>@enderror
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="ss-card">
            <div class="ss-head"><h2>Contact and pickup address</h2><p>Printed on your waybills. Couriers pick up parcels here.</p>
                @if ($a->address)<p class="mt-1 text-xs" style="color:#57534E;" data-saved-address>Saved: {{ $a->address }}</p>@endif
            </div>
            <div class="ss-body grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="ss-label" for="phone">Contact number *</label>
                    <input id="phone" name="phone" inputmode="tel" value="{{ old('phone', $a->phone) }}" placeholder="09171234567" class="input @error('phone') is-invalid @enderror">
                    @error('phone')<p class="ss-error">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="ss-label" for="ss_region">Region *</label>
                    <select id="ss_region" name="region" class="input @error('region') is-invalid @enderror"><option value="">— Select Region —</option></select>
                    @error('region')<p class="ss-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="ss-label" for="ss_province">Province *</label>
                    <select id="ss_province" name="province" class="input @error('province') is-invalid @enderror" disabled><option value="">— Select Region first —</option></select>
                    @error('province')<p class="ss-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="ss-label" for="ss_municipality">City / Municipality *</label>
                    <select id="ss_municipality" name="municipality" class="input @error('municipality') is-invalid @enderror" disabled><option value="">— Select Province first —</option></select>
                    @error('municipality')<p class="ss-error">{{ $message }}</p>@enderror
                    <input type="hidden" name="province_code" id="ss_province_code" value="{{ old('province_code', $a->province_code) }}">
                    <input type="hidden" name="municipality_code" id="ss_municipality_code" value="{{ old('municipality_code', $a->municipality_code) }}">
                </div>
                <div>
                    <label class="ss-label" for="ss_barangay">Barangay *</label>
                    <select id="ss_barangay" name="barangay" class="input @error('barangay') is-invalid @enderror" disabled><option value="">— Select City first —</option></select>
                    @error('barangay')<p class="ss-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="ss-label" for="zip_code">Postal code *</label>
                    <input id="zip_code" name="zip_code" maxlength="4" inputmode="numeric" value="{{ old('zip_code', $a->zip_code) }}" class="input @error('zip_code') is-invalid @enderror">
                    @error('zip_code')<p class="ss-error">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="ss-label" for="street">Street address *</label>
                    <input id="street" name="street" value="{{ old('street', $a->street) }}" class="input @error('street') is-invalid @enderror">
                    @error('street')<p class="ss-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <section class="ss-card">
            <div class="ss-head"><h2>Vacation mode</h2><p>Your products stay visible with an "On vacation" label, but buyers can't add them to the cart, buy now or check out.</p></div>
            <div class="ss-body">
                <label class="flex items-center gap-3 text-sm font-medium">
                    <input type="hidden" name="is_on_vacation" value="0">
                    <input type="checkbox" name="is_on_vacation" value="1" class="h-4 w-4" @checked(old('is_on_vacation', $a->is_on_vacation))>
                    My store is on vacation
                </label>
            </div>
        </section>

        <div class="flex justify-end"><button class="btn-primary" id="saveStore">Save</button></div>
    </form>
</div>

<script src="{{ asset('js/register-address-fallback.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    RegisterAddressFallback.attach({
        region: '#ss_region', province: '#ss_province', city: '#ss_municipality', barangay: '#ss_barangay',
        provinceCode: '#ss_province_code', cityCode: '#ss_municipality_code',
        old: @json($oldAddress),
    });
});
</script>
</x-seller-layout>
