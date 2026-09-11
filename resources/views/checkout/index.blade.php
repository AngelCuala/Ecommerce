<x-layout title="Checkout — ALVY">
<div class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">

    <h1 class="font-display text-3xl font-extrabold" style="color:#222222;">Checkout</h1>

    {{-- Progress --}}
    <div class="mt-5 flex items-center">
        @foreach (['Cart','Checkout','Confirmation'] as $i => $step)
            @if ($i > 0)<div class="h-px flex-1" style="background:#E0E0E0;"></div>@endif
            <div class="flex shrink-0 flex-col items-center gap-1">
                <span class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
                      style="{{ $i === 1 ? 'background:#fa4e1c;color:#FFFFFF;' : 'background:#EFEFEF;color:#999999;' }}">{{ $i+1 }}</span>
                <span class="text-[10px] font-semibold" style="{{ $i === 1 ? 'color:#fa4e1c;' : 'color:#999999;' }}">{{ $step }}</span>
            </div>
            @if ($i < 2)<div class="h-px flex-1" style="background:#E0E0E0;"></div>@endif
        @endforeach
    </div>

    @if ($errors->any())
        <div class="mt-5 rounded-lg border p-4 text-sm" style="background:rgba(208,2,27,.06);border-color:rgba(208,2,27,.2);color:#D0021B;">
            <ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form action="{{ route('checkout.store') }}" method="POST" class="mt-8 grid gap-8 lg:grid-cols-[1fr_380px]">
        @csrf
        <input type="hidden" name="payment_method" value="cod">

        <div class="space-y-6">

            {{-- ── Contact Information ── --}}
            <div class="card p-6">
                <h2 class="font-display text-lg font-extrabold mb-4" style="color:#222222;">Contact Information</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold" style="color:#999999;">Full Name *</label>
                        <input type="text" name="full_name"
                               value="{{ old('full_name', auth()->user()->name) }}"
                               class="input mt-1" required>
                    </div>
                    <div>
                        <label class="text-xs font-semibold" style="color:#999999;">Phone Number *</label>
                        <input type="text" name="phone"
                               value="{{ old('phone', auth()->user()->contact_no ?? auth()->user()->phone) }}"
                               class="input mt-1" required placeholder="+63 912 345 6789">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="text-xs font-semibold" style="color:#999999;">Email Address *</label>
                        <input type="email" name="email"
                               value="{{ old('email', auth()->user()->email) }}"
                               class="input mt-1" required>
                    </div>
                </div>
            </div>

            {{-- ── Delivery Address ── --}}
            <div class="card p-6">
                <h2 class="font-display text-lg font-extrabold mb-4" style="color:#222222;">Delivery Address</h2>

                <div class="grid gap-4 sm:grid-cols-2">

                    {{-- Region --}}
                    <div class="sm:col-span-2">
                        <label class="text-xs font-semibold" style="color:#999999;">Region *</label>
                        <select id="sel-region" name="region" class="input mt-1" required
                                onchange="loadProvinces(this.value, this.options[this.selectedIndex].text)">
                            <option value="">— Select Region —</option>
                        </select>
                        <p id="loading-region" class="mt-1 text-[11px] hidden" style="color:#fa4e1c;">Loading regions…</p>
                    </div>

                    {{-- Province --}}
                    <div>
                        <label class="text-xs font-semibold" style="color:#999999;">Province *</label>
                        <select id="sel-province" name="province" class="input mt-1" required disabled
                                onchange="loadMunicipalities(this.value, this.options[this.selectedIndex].text)">
                            <option value="">— Select Province —</option>
                        </select>
                    </div>

                    {{-- City / Municipality --}}
                    <div>
                        <label class="text-xs font-semibold" style="color:#999999;">City / Municipality *</label>
                        <select id="sel-city" name="city" class="input mt-1" required disabled
                                onchange="loadBarangays(this.value, this.options[this.selectedIndex].text)">
                            <option value="">— Select City / Municipality —</option>
                        </select>
                    </div>

                    {{-- Barangay --}}
                    <div>
                        <label class="text-xs font-semibold" style="color:#999999;">Barangay *</label>
                        <select id="sel-barangay" name="barangay" class="input mt-1" required disabled>
                            <option value="">— Select Barangay —</option>
                        </select>
                    </div>

                    {{-- ZIP Code --}}
                    <div>
                        <label class="text-xs font-semibold" style="color:#999999;">ZIP Code *</label>
                        <input type="text" name="zip_code"
                               value="{{ old('zip_code', auth()->user()->zip) }}"
                               class="input mt-1" required placeholder="e.g. 1000">
                    </div>

                    {{-- House Number --}}
                    <div>
                        <label class="text-xs font-semibold" style="color:#999999;">House / Unit No.</label>
                        <input type="text" name="house_number"
                               value="{{ old('house_number', auth()->user()->house_number) }}"
                               class="input mt-1" placeholder="e.g. 123, Unit 4B">
                    </div>

                    {{-- Street --}}
                    <div class="sm:col-span-2">
                        <label class="text-xs font-semibold" style="color:#999999;">Street / Subdivision</label>
                        <input type="text" name="street"
                               value="{{ old('street', auth()->user()->street) }}"
                               class="input mt-1" placeholder="e.g. Rizal Street, Sunshine Village">
                    </div>

                </div>
            </div>

            {{-- ── Payment Method ── --}}
            <div class="card p-6">
                <h2 class="font-display text-lg font-extrabold mb-4" style="color:#222222;">Payment Method</h2>
                <div class="flex items-center gap-4 rounded-xl border-2 p-5"
                     style="border-color:#fa4e1c;background:#FFF8F4;">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-xl"
                         style="background:#fa4e1c;color:#fff;">💵</div>
                    <div>
                        <p class="font-bold text-sm" style="color:#222222;">Cash on Delivery (COD)</p>
                        <p class="text-xs mt-0.5" style="color:#757575;">Pay in cash when your order arrives at your door</p>
                    </div>
                    <span class="ml-auto rounded-full px-3 py-1 text-[11px] font-bold"
                          style="background:#fa4e1c;color:#fff;">Selected</span>
                </div>
                <div class="mt-4 rounded-lg p-4 text-sm" style="background:#fff1ee;border:1px solid #fdb49e;">
                    💡 <span style="color:#555555;">Please prepare
                    <strong style="color:#fa4e1c;">₱{{ number_format($total, 2) }}</strong>
                    upon delivery. No advance payment needed.</span>
                </div>
            </div>
        </div>

        {{-- ── Order Summary ── --}}
        <div class="lg:sticky lg:top-24 lg:self-start">
            <div class="card overflow-hidden">
                <div class="px-6 py-4" style="background:#002b4d;">
                    <h2 class="font-display text-lg font-extrabold" style="color:#FFFFFF;">Order Summary</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-3 max-h-64 overflow-y-auto">
                        @foreach ($items as $item)
                            <div class="flex items-center gap-3">
                                <img src="{{ $item->book->image ? asset('storage/'.$item->book->image) : 'https://placehold.co/48x64/FF6300/FFFFFF?text=P' }}"
                                     class="h-12 w-9 rounded object-cover flex-shrink-0"
                                     alt="{{ $item->book->title }}">
                                <div class="flex flex-1 items-start justify-between gap-2">
                                    <div>
                                        <p class="text-sm font-semibold leading-snug" style="color:#222222;">{{ $item->book->title }}</p>
                                        <p class="text-xs" style="color:#999999;">Qty: {{ $item->quantity }}</p>
                                    </div>
                                    <span class="text-sm font-semibold flex-shrink-0" style="color:#fa4e1c;">
                                        ₱{{ number_format($item->quantity * $item->book->price, 2) }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-5 space-y-2 border-t pt-4 text-sm" style="border-color:#EFEFEF;">
                        <div class="flex justify-between" style="color:#555555;">
                            <span>Subtotal</span>
                            <span>₱{{ number_format($subtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between" style="color:#555555;">
                            <span>Delivery Fee</span>
                            <span>₱{{ number_format($shipping, 2) }}</span>
                        </div>
                    </div>
                    <div class="mt-3 flex justify-between border-t pt-3 font-display text-xl font-extrabold"
                         style="border-color:#EFEFEF;color:#222222;">
                        <span>Total</span>
                        <span style="color:#fa4e1c;">₱{{ number_format($total, 2) }}</span>
                    </div>
                    <div class="mt-3 rounded-lg p-2.5 text-xs text-center"
                         style="background:#fff1ee;color:#d93d0e;">
                        Includes ₱50.00 delivery fee
                    </div>
                    <button type="submit"
                            class="mt-5 w-full rounded-xl py-3.5 text-base font-bold text-white transition hover:opacity-90"
                            style="background:#002b4d;">
                        Place Order
                    </button>
                    <p class="mt-2 text-center text-[11px]" style="color:#999999;">🔒 Secure checkout</p>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
const BASE = '/api/psgc';

function setLoading(selectEl, msg) {
    selectEl.innerHTML = `<option value="">${msg}</option>`;
    selectEl.disabled = true;
}

function populate(selectEl, items, placeholder) {
    selectEl.innerHTML = `<option value="">${placeholder}</option>`;
    items.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item.name;
        opt.dataset.code = item.code;
        opt.textContent = item.name;
        selectEl.appendChild(opt);
    });
    selectEl.disabled = false;
}

function resetSelect(selectEl, placeholder) {
    selectEl.innerHTML = `<option value="">${placeholder}</option>`;
    selectEl.disabled = true;
}

// ── Load regions on page load ──────────────────────────────────────
window.addEventListener('DOMContentLoaded', function () {
    const sel = document.getElementById('sel-region');
    setLoading(sel, 'Loading regions…');
    fetch(BASE + '/regions')
        .then(r => r.json())
        .then(data => populate(sel, data, '— Select Region —'))
        .catch(() => {
            sel.innerHTML = '<option value="">Failed to load — try refreshing</option>';
        });
});

// ── Region → Province ──────────────────────────────────────────────
function loadProvinces(regionCode, regionName) {
    resetSelect(document.getElementById('sel-province'), '— Select Province —');
    resetSelect(document.getElementById('sel-city'),    '— Select City / Municipality —');
    resetSelect(document.getElementById('sel-barangay'), '— Select Barangay —');
    if (!regionCode) return;

    // Get the PSGC code from the selected option's data-code attribute
    const sel = document.getElementById('sel-region');
    const selected = sel.options[sel.selectedIndex];
    const code = selected.dataset.code;

    const provSel = document.getElementById('sel-province');
    setLoading(provSel, 'Loading provinces…');

    fetch(BASE + '/regions/' + code + '/provinces')
        .then(r => r.json())
        .then(data => {
            if (data.length === 0) {
                // NCR has no provinces — load municipalities directly
                loadMunicipalitiesForRegion(code);
            } else {
                populate(provSel, data, '— Select Province —');
            }
        })
        .catch(() => provSel.innerHTML = '<option value="">Failed to load</option>');
}

function loadMunicipalitiesForRegion(regionCode) {
    // For NCR: skip province, load cities directly from region
    const provSel = document.getElementById('sel-province');
    provSel.innerHTML = '<option value="NCR">Metro Manila (NCR)</option>';
    provSel.disabled = false;
    // trigger municipality load with NCR code
    loadMunicipalities(regionCode, 'Metro Manila');
}

// ── Province → City/Municipality ──────────────────────────────────
function loadMunicipalities(provinceCode, provinceName) {
    resetSelect(document.getElementById('sel-city'),     '— Select City / Municipality —');
    resetSelect(document.getElementById('sel-barangay'), '— Select Barangay —');
    if (!provinceCode) return;

    const sel = document.getElementById('sel-province');
    const selected = sel.options[sel.selectedIndex];
    const code = selected.dataset.code || provinceCode;

    const citySel = document.getElementById('sel-city');
    setLoading(citySel, 'Loading cities…');

    fetch(BASE + '/provinces/' + code + '/municipalities')
        .then(r => r.json())
        .then(data => populate(citySel, data, '— Select City / Municipality —'))
        .catch(() => citySel.innerHTML = '<option value="">Failed to load</option>');
}

// ── City/Municipality → Barangay ───────────────────────────────────
function loadBarangays(cityCode, cityName) {
    resetSelect(document.getElementById('sel-barangay'), '— Select Barangay —');
    if (!cityCode) return;

    const sel = document.getElementById('sel-city');
    const selected = sel.options[sel.selectedIndex];
    const code = selected.dataset.code || cityCode;

    const brgySel = document.getElementById('sel-barangay');
    setLoading(brgySel, 'Loading barangays…');

    fetch(BASE + '/municipalities/' + code + '/barangays')
        .then(r => r.json())
        .then(data => populate(brgySel, data, '— Select Barangay —'))
        .catch(() => brgySel.innerHTML = '<option value="">Failed to load</option>');
}
</script>
</x-layout>
