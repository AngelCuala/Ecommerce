<x-seller-layout title="Store Settings" active="store">
@php
    $a = $application;
    $oldAddress = [
        'region'       => old('region', $a->region),
        'province'     => old('province', $a->province),
        'municipality' => old('municipality', $a->municipality),
        'barangay'     => old('barangay', $a->barangay),
    ];
    $shopName = old('shop_name', $a->shop_name);
    $logoUrl = $a->logo_path ? asset('storage/' . $a->logo_path) : null;
    $bannerUrl = $a->banner_path ? asset('storage/' . $a->banner_path) : null;
    $svg = fn (string $d, string $cls = 'h-5 w-5') => '<svg class="' . $cls . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">' . $d . '</svg>';
@endphp
<style>
    .ss-card { background:#fff; border:1px solid #ECE7E3; border-radius:1rem; box-shadow:0 1px 2px rgba(34,34,34,.04); }
    .ss-head { display:flex; align-items:flex-start; gap:.75rem; padding:1.15rem 1.5rem; border-bottom:1px solid #F2EEEA; }
    .ss-head h2 { font-size:1rem; font-weight:700; color:#222; }
    .ss-head p { margin-top:.15rem; font-size:.8125rem; color:#8a7f78; }
    .ss-ic { display:flex; flex:none; height:2.4rem; width:2.4rem; align-items:center; justify-content:center; border-radius:.75rem; }
    .ss-body { padding:1.25rem 1.5rem; }
    .ss-label { display:block; margin-bottom:.3rem; font-size:.8125rem; font-weight:500; color:#44403C; }
    .ss-note { margin-top:.3rem; font-size:.75rem; color:#8a7f78; }
    .ss-note-row { display:flex; justify-content:space-between; gap:.75rem; }
    .ss-error { margin-top:.3rem; font-size:.75rem; font-weight:500; color:#B91C1C; }
    .input.is-invalid { border-color:#DC2626; box-shadow:0 0 0 3px rgba(220,38,38,.12); }

    /* Store page preview */
    .ss-preview { overflow:hidden; border-radius:.9rem; border:1px solid #ECE7E3; background:#fff; }
    .ss-banner { position:relative; height:7.5rem; background:linear-gradient(120deg,#fa4e1c 0%,#ff8a5b 45%,#002b4d 130%); background-size:cover; background-position:center; }
    .ss-banner img { position:absolute; inset:0; height:100%; width:100%; object-fit:cover; }
    .ss-id { display:flex; align-items:flex-start; gap:.9rem; padding:0 1.1rem .6rem; margin-top:-2.25rem; position:relative; }
    .ss-id > div { padding-top:2.55rem; }   /* the logo overlaps the banner; the name sits below it */
    .ss-logo { display:flex; flex:none; height:4.5rem; width:4.5rem; align-items:center; justify-content:center; overflow:hidden; border-radius:9999px; border:3px solid #fff; background:#FFF1EB; box-shadow:0 4px 12px -4px rgba(0,0,0,.25); font-size:1.6rem; font-weight:800; color:#d93d0e; }
    .ss-logo img { height:100%; width:100%; object-fit:cover; }
    .ss-tag { display:inline-flex; align-items:center; gap:.3rem; padding:.15rem .55rem; border-radius:9999px; font-size:.7rem; font-weight:700; }
    .ss-tag.hidden { display:none; }

    /* Image pickers */
    .ss-pick { display:flex; align-items:center; gap:.8rem; padding:.75rem .9rem; border:1.5px dashed #D6D3D1; border-radius:.75rem; cursor:pointer; transition:border-color .15s, background .15s; }
    .ss-pick:hover, .ss-pick:focus-within { border-color:#fa4e1c; background:#FFF7F4; }
    .ss-pick-thumb { display:flex; flex:none; height:2.75rem; width:2.75rem; align-items:center; justify-content:center; overflow:hidden; border-radius:.6rem; background:#FFF1EB; color:#fa4e1c; }
    .ss-pick-thumb.wide { width:4.5rem; }
    .ss-pick-thumb img { height:100%; width:100%; object-fit:cover; }
    .ss-pick input[type=file] { position:absolute; width:1px; height:1px; opacity:0; pointer-events:none; }

    /* Vacation switch */
    .ss-switch { position:relative; display:inline-flex; flex:none; height:1.6rem; width:2.85rem; border-radius:9999px; background:#D6D3D1; transition:background .2s; }
    .ss-switch::after { content:''; position:absolute; top:.2rem; left:.2rem; height:1.2rem; width:1.2rem; border-radius:9999px; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.25); transition:transform .2s; }
    .ss-vac input:checked + .ss-switch { background:#F59E0B; }
    .ss-vac input:checked + .ss-switch::after { transform:translateX(1.25rem); }
    .ss-vac input:focus-visible + .ss-switch { outline:2px solid #fa4e1c; outline-offset:2px; }

    /* Save bar: sticks to the bottom once something changes */
    .ss-save { display:flex; flex-wrap:wrap; align-items:center; justify-content:flex-end; gap:.75rem; border-radius:1rem; }
    .ss-save.is-dirty { position:sticky; bottom:1rem; z-index:20; justify-content:space-between; border:1px solid #ECE7E3; background:#fff; padding:.75rem 1rem; box-shadow:0 8px 28px rgba(34,34,34,.16); }
    .ss-save-state { display:none; font-size:.8125rem; font-weight:500; color:#92400E; }
    .ss-save.is-dirty .ss-save-state { display:inline-block; }
    .ss-save > span:last-child { margin-left:auto; }
</style>

<div class="mx-auto max-w-3xl">
    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <span class="section-eyebrow">Store</span>
            <h1 class="text-2xl">Store Settings</h1>
            <p class="text-sm" style="color:#8a7f78;">What buyers see on your store page, and where couriers pick up your parcels.</p>
        </div>
        <a href="{{ route('stores.show', auth()->id()) }}" target="_blank" class="btn-outline inline-flex items-center gap-1.5" style="padding:.5rem 1rem;">
            {!! $svg('<path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"/>', 'h-4 w-4') !!} View store page
        </a>
    </div>

    @if ($errors->any())<div class="mb-4 rounded-2xl border px-5 py-3 text-sm font-medium" style="background:#FEF2F2;border-color:#FECACA;color:#991B1B;" role="alert">Please fix the highlighted fields. Nothing was saved.</div>@endif

    <form method="POST" action="{{ route('seller.store-settings.update') }}" enctype="multipart/form-data" class="space-y-5" novalidate data-store-form data-start-dirty="{{ $errors->any() ? '1' : '0' }}">
        @csrf @method('PATCH')

        <section class="ss-card">
            <div class="ss-head">
                <span class="ss-ic" style="background:#FFF1EB;color:#fa4e1c;">{!! $svg('<path d="M3 9l1.5-5h15L21 9M3 9h18M3 9a3 3 0 006 0 3 3 0 006 0 3 3 0 006 0M5 12v8h14v-8"/>') !!}</span>
                <div><h2>Store profile</h2><p>Shown on your public store page.</p></div>
            </div>
            <div class="ss-body space-y-5">
                {{-- What buyers will see (updates as you type and pick pictures) --}}
                <div>
                    <p class="ss-label">Preview</p>
                    <div class="ss-preview" data-preview>
                        <div class="ss-banner" data-preview-banner>@if ($bannerUrl)<img src="{{ $bannerUrl }}" alt="">@endif</div>
                        <div class="ss-id">
                            <span class="ss-logo" data-preview-logo>@if ($logoUrl)<img src="{{ $logoUrl }}" alt="">@else{{ mb_strtoupper(mb_substr($shopName ?: 'S', 0, 1)) }}@endif</span>
                            <div class="min-w-0 pb-1">
                                <p class="truncate font-display text-lg font-bold" style="color:#222;" data-preview-name>{{ $shopName ?: 'Your store name' }}</p>
                                <span class="ss-tag {{ old('is_on_vacation', $a->is_on_vacation) ? '' : 'hidden' }}" style="background:#FEF3C7;color:#92400E;" data-preview-vacation>On vacation</span>
                            </div>
                        </div>
                        <p class="px-4 pb-4 text-sm" style="color:#57534E;white-space:pre-line;" data-preview-desc>{{ old('description', $a->description) ?: 'Your description shows here.' }}</p>
                    </div>
                </div>

                <div>
                    <label class="ss-label" for="shop_name">Store name *</label>
                    <input id="shop_name" name="shop_name" maxlength="30" value="{{ $shopName }}" class="input @error('shop_name') is-invalid @enderror">
                    @error('shop_name')<p class="ss-error">{{ $message }}</p>@else
                        <p class="ss-note ss-note-row"><span>3 to 30 characters. {{ $a->shopNameChangesRemaining() }} of 3 name changes left this month.</span><span class="flex-none"><span data-count="shop_name">{{ mb_strlen($shopName) }}</span> / 30</span></p>
                    @enderror
                </div>
                <div>
                    <label class="ss-label" for="description">Description</label>
                    <textarea id="description" name="description" rows="4" maxlength="500" class="input @error('description') is-invalid @enderror" placeholder="What you sell, what makes your store special, how fast you ship…">{{ old('description', $a->description) }}</textarea>
                    @error('description')<p class="ss-error">{{ $message }}</p>@else
                        <p class="ss-note ss-note-row"><span>Plain text, up to 500 characters.</span><span class="flex-none"><span data-count="description">{{ mb_strlen((string) old('description', $a->description)) }}</span> / 500</span></p>
                    @enderror
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach (['logo' => ['Logo', 'Square works best', $logoUrl], 'banner' => ['Banner', 'Wide works best', $bannerUrl]] as $field => [$label, $tip, $url])
                        <div>
                            <p class="ss-label">{{ $label }}</p>
                            <label class="ss-pick" for="{{ $field }}">
                                <span class="ss-pick-thumb {{ $field === 'banner' ? 'wide' : '' }}" data-pick-thumb="{{ $field }}">
                                    @if ($url)<img src="{{ $url }}" alt="Current {{ strtolower($label) }}">@else{!! $svg('<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>') !!}@endif
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold" style="color:#222;" data-pick-title="{{ $field }}">{{ $url ? 'Change ' . strtolower($label) : 'Upload ' . strtolower($label) }}</span>
                                    <span class="block text-xs" style="color:#8a7f78;">JPG or PNG, up to 5 MB. {{ $tip }}.</span>
                                </span>
                                <input type="file" id="{{ $field }}" name="{{ $field }}" accept=".jpg,.jpeg,.png,image/jpeg,image/png" data-pick="{{ $field }}">
                            </label>
                            @error($field)<p class="ss-error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="ss-card">
            <div class="ss-head">
                <span class="ss-ic" style="background:#EEF3F7;color:#002b4d;">{!! $svg('<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/>') !!}</span>
                <div class="min-w-0"><h2>Contact and pickup address</h2><p>Printed on your waybills. Couriers pick up parcels here.</p>
                    @if ($a->address)<p class="mt-2 inline-flex rounded-lg px-2.5 py-1 text-xs" style="background:#F5F2EF;color:#57534E;" data-saved-address>Saved: {{ $a->address }}</p>@endif
                </div>
            </div>
            <div class="ss-body grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="ss-label" for="phone">Contact number *</label>
                    <input id="phone" name="phone" value="{{ old('phone', $a->phone) }}" placeholder="09171234567" class="input @error('phone') is-invalid @enderror" maxlength="13" inputmode="tel" autocomplete="tel">
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
                    <input id="zip_code" name="zip_code" maxlength="4" inputmode="numeric" value="{{ old('zip_code', $a->zip_code) }}" class="input @error('zip_code') is-invalid @enderror" placeholder="e.g. 4027">
                    @error('zip_code')<p class="ss-error">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="ss-label" for="street">Street address *</label>
                    <input id="street" name="street" value="{{ old('street', $a->street) }}" class="input @error('street') is-invalid @enderror" placeholder="House no. and street, e.g. 12 Rizal Street">
                    @error('street')<p class="ss-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <section class="ss-card">
            <div class="ss-head">
                <span class="ss-ic" style="background:#FEF3C7;color:#B45309;">{!! $svg('<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>') !!}</span>
                <div><h2>Vacation mode</h2><p>Your products stay visible with an "On vacation" label, but buyers can't add them to the cart, buy now or check out.</p></div>
            </div>
            <div class="ss-body">
                <label class="ss-vac flex cursor-pointer items-center justify-between gap-4 text-sm font-medium" style="color:#222;">
                    <span>
                        My store is on vacation
                        <span class="block text-xs font-normal" style="color:#8a7f78;" data-vacation-note>{{ old('is_on_vacation', $a->is_on_vacation) ? 'Buyers see your store as on vacation.' : 'Your store is open for orders.' }}</span>
                    </span>
                    <input type="hidden" name="is_on_vacation" value="0">
                    <input type="checkbox" name="is_on_vacation" value="1" class="sr-only" role="switch" @checked(old('is_on_vacation', $a->is_on_vacation))>
                    <span class="ss-switch" aria-hidden="true"></span>
                </label>
            </div>
        </section>

        <div class="ss-save" data-save-bar>
            <span class="ss-save-state" role="status">You have unsaved changes</span>
            <span class="flex items-center gap-3">
                <button type="button" class="hidden text-sm font-semibold hover:underline" style="color:#57534E;" data-discard>Discard</button>
                <button class="btn-primary" id="saveStore">Save</button>
            </span>
        </div>
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

    var form = document.querySelector('[data-store-form]'),
        bar = form.querySelector('[data-save-bar]'), discard = form.querySelector('[data-discard]'),
        dirty = form.dataset.startDirty === '1', leaving = false;
    function q(sel) { return document.querySelector(sel); }

    // Live preview and character counts.
    var name = form.elements.shop_name, desc = form.elements.description, vac = form.querySelector('input[type=checkbox][name=is_on_vacation]');
    function preview() {
        q('[data-preview-name]').textContent = name.value.trim() || 'Your store name';
        q('[data-preview-desc]').textContent = desc.value.trim() || 'Your description shows here.';
        var logo = q('[data-preview-logo]');
        if (!logo.querySelector('img')) logo.textContent = (name.value.trim() || 'S').charAt(0).toUpperCase();
        q('[data-preview-vacation]').classList.toggle('hidden', !vac.checked);
        q('[data-vacation-note]').textContent = vac.checked ? 'Buyers see your store as on vacation.' : 'Your store is open for orders.';
        form.querySelectorAll('[data-count]').forEach(function (c) { c.textContent = form.elements[c.dataset.count].value.length; });
    }
    // A picked logo or banner shows straight away (it uploads when you save).
    form.querySelectorAll('[data-pick]').forEach(function (input) {
        input.addEventListener('change', function () {
            var f = input.files && input.files[0], key = input.dataset.pick;
            if (!f) return;
            var url = URL.createObjectURL(f), img = '<img src="' + url + '" alt="">';
            q('[data-pick-thumb="' + key + '"]').innerHTML = img;
            q('[data-pick-title="' + key + '"]').textContent = f.name;
            (key === 'logo' ? q('[data-preview-logo]') : q('[data-preview-banner]')).innerHTML = img;
        });
    });

    // The save bar appears once you change something (the address lists filling in by themselves don't count).
    function sync() {
        bar.classList.toggle('is-dirty', dirty);
        discard.classList.toggle('hidden', !dirty);
    }
    function changed(e) { if (e.isTrusted) { dirty = true; sync(); } preview(); }
    form.addEventListener('input', changed);
    form.addEventListener('change', changed);
    discard.addEventListener('click', function () { leaving = true; window.location.reload(); });
    form.addEventListener('submit', function () { leaving = true; var b = q('#saveStore'); b.textContent = 'Saving…'; setTimeout(function () { b.disabled = true; }, 0); });
    window.addEventListener('beforeunload', function (e) { if (dirty && !leaving && form.dataset.startDirty !== '1') { e.preventDefault(); e.returnValue = ''; } });
    sync();

    var first = form.querySelector('.is-invalid');
    if (first) { first.scrollIntoView({ block: 'center' }); first.focus({ preventScroll: true }); }
});
</script>
</x-seller-layout>
