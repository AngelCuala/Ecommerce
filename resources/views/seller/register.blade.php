<x-auth-shell title="Seller Sign Up — ALVY" heading="Start selling on ALVY" subtitle="Create your seller account — we'll review your shop in 1–3 business days." tab="signup" width="wide" :progress="true">
<x-slot:head>
    <style>
        body { font-family:'Inter',ui-sans-serif,system-ui; }
        .font-display { font-family:'Nunito',ui-sans-serif,system-ui; }
        .form-input { width:100%;border-radius:.625rem;border:1px solid #E6D9CF;background:#fff;padding:.7rem 1rem;font-size:.9rem;color:#222;outline:none;transition:border-color .15s,box-shadow .15s; }
        .form-input::placeholder { color:#b5a79d; }
        .form-input:focus { border-color:#fa4e1c;box-shadow:0 0 0 3px rgba(250,78,28,.12); }
        .form-input:disabled, .form-input[readonly] { background:#f7f1ed;cursor:not-allowed; }
        .form-input.is-invalid { border-color:#DC2626; }
        .fld-label { display:block;margin-bottom:.375rem;font-size:.75rem;font-weight:600;color:#3a2f29; }
        .field-error { margin-top:.3rem;font-size:.75rem;color:#DC2626; }
        .form-card { background:#fff;border:1px solid #F0E4DC;border-radius:1rem;padding:1.25rem; }
        .card-title { display:flex;align-items:center;gap:.625rem;margin-bottom:1rem;font-family:'Nunito',ui-sans-serif,system-ui;font-weight:800;font-size:1rem;color:#222; }
        .card-num { display:inline-flex;align-items:center;justify-content:center;width:1.625rem;height:1.625rem;border-radius:999px;background:#fff1ee;color:#fa4e1c;font-size:.75rem;font-weight:800; }
        .upload-row { display:flex;align-items:center;gap:.875rem;border:1.5px dashed #E6D9CF;border-radius:.75rem;padding:.75rem 1rem;background:#FDF8F5;cursor:pointer;transition:all .2s; }
        .upload-row:hover { border-color:#fa4e1c;background:#fff5f3; }
        .upload-row.has-file { border-style:solid;border-color:#fa4e1c;background:#fff5f3; }
        .upload-row.is-invalid { border-color:#DC2626; }
        .upload-thumb { flex-shrink:0;display:inline-flex;align-items:center;justify-content:center;width:2.75rem;height:2.75rem;border-radius:.625rem;background:#fff1ee;color:#fa4e1c;overflow:hidden;font-size:.65rem;font-weight:800; }
        .upload-thumb img { width:100%;height:100%;object-fit:cover; }
        .form-scroll { scrollbar-width:thin;scrollbar-color:#E6D9CF transparent; }
        .form-card { background:#FDFAF8;border-color:#F3E8E0; }
        .sticky-submit { position:sticky;bottom:0;z-index:20;background:rgba(255,255,255,.96);backdrop-filter:blur(8px);border-top:1px solid #F0E4DC;border-radius:0 0 1.75rem 1.75rem;box-shadow:0 -12px 24px -18px rgba(58,47,41,.35); }
        .flow-step { display:flex;align-items:center;gap:.5rem;font-size:.75rem;font-weight:600;color:#8a7a70; }
        .flow-dot { display:inline-flex;align-items:center;justify-content:center;width:1.5rem;height:1.5rem;border-radius:999px;font-size:.7rem;font-weight:800;background:#F7F1ED;color:#8a7a70; }
        .flow-step.is-current { color:#222; } .flow-step.is-current .flow-dot { background:#fa4e1c;color:#fff; }
    </style>
</x-slot:head>

        {{-- Sign-up → verify → approval --}}
        <div class="mx-6 mt-6 flex items-center justify-center gap-2 sm:mx-10">
            <span class="flow-step is-current"><span class="flow-dot">1</span> Sign up</span>
            <span class="h-px w-8" style="background:#E6D9CF;"></span>
            <span class="flow-step"><span class="flow-dot">2</span> Verify email</span>
            <span class="h-px w-8" style="background:#E6D9CF;"></span>
            <span class="flow-step"><span class="flow-dot">3</span> Admin approval</span>
        </div>

        <form id="sellerRegisterForm" method="POST" action="{{ route('seller.register.store') }}" enctype="multipart/form-data" novalidate>
            @csrf

            <div class="space-y-4 px-6 py-6 sm:px-10">

                @if ($errors->any())
                    <div class="flex items-start gap-2 rounded-xl border p-3 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
                        <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p><strong>Please fix the {{ $errors->count() === 1 ? 'field' : $errors->count() . ' fields' }} marked below.</strong>
                           For your security, passwords and uploaded files need to be entered again.</p>
                    </div>
                @endif

                {{-- 1. Personal --}}
                <section class="form-card">
                    <h2 class="card-title"><span class="card-num">1</span> Personal Information</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="fld-label" for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" placeholder="Maria Clara"
                                   class="form-input @error('first_name') is-invalid @enderror">
                            @error('first_name')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="fld-label" for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" placeholder="Santos"
                                   class="form-input @error('last_name') is-invalid @enderror">
                            @error('last_name')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="fld-label" for="email">Email *</label>
                            <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="you@example.com" autocomplete="email"
                                   class="form-input @error('email') is-invalid @enderror">
                            @error('email')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="fld-label" for="phone">Mobile Number *</label>
                            <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" placeholder="09171234567" inputmode="tel"
                                   class="form-input @error('phone') is-invalid @enderror">
                            @error('phone')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                {{-- 2. Shop --}}
                <section class="form-card">
                    <h2 class="card-title"><span class="card-num">2</span> Shop Information</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="fld-label" for="shop_name">Shop Name * <span class="font-normal" style="color:#8a7a70;">(3–30 characters)</span></label>
                            <input type="text" id="shop_name" name="shop_name" value="{{ old('shop_name') }}" placeholder="e.g. Maria Clara Handicrafts"
                                   class="form-input @error('shop_name') is-invalid @enderror">
                            @error('shop_name')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="fld-label" for="line_of_business">Shop Category *</label>
                            <select id="line_of_business" name="line_of_business" class="form-input @error('line_of_business') is-invalid @enderror">
                                <option value="">Select category</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat }}" @selected(old('line_of_business') === $cat)>{{ $cat }}</option>
                                @endforeach
                            </select>
                            @error('line_of_business')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="fld-label" for="seller_type">Seller Type *</label>
                            <select id="seller_type" name="seller_type" class="form-input @error('seller_type') is-invalid @enderror">
                                <option value="">Select seller type</option>
                                @foreach ($sellerTypes as $type)
                                    <option value="{{ $type }}" @selected(old('seller_type') === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                            @error('seller_type')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="fld-label" for="tin">TIN *</label>
                            <input type="text" id="tin" name="tin" value="{{ old('tin') }}" placeholder="000-000-000-000" inputmode="numeric" maxlength="15"
                                   class="form-input @error('tin') is-invalid @enderror">
                            @error('tin')
                                <p class="field-error">{{ $message }}</p>
                            @else
                                <p class="mt-1 text-[11px]" style="color:#8a7a70;">12 digits, including your 3-digit branch code (000 for the main office).</p>
                            @enderror
                        </div>
                    </div>
                </section>

                {{-- 3. Address --}}
                <section class="form-card">
                    <h2 class="card-title"><span class="card-num">3</span> Shop Address</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="fld-label" for="sa_region">Region *</label>
                            <select id="sa_region" name="region" class="form-input @error('region') is-invalid @enderror">
                                <option value="">— Select Region —</option>
                            </select>
                            @error('region')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="fld-label" for="sa_province">Province *</label>
                            <select id="sa_province" name="province" class="form-input @error('province') is-invalid @enderror" disabled>
                                <option value="">— Select Region first —</option>
                            </select>
                            @error('province')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="fld-label" for="sa_municipality">City / Municipality *</label>
                            <select id="sa_municipality" name="municipality" class="form-input @error('municipality') is-invalid @enderror" disabled>
                                <option value="">— Select Province first —</option>
                            </select>
                            @error('municipality')<p class="field-error">{{ $message }}</p>@enderror
                            <input type="hidden" name="province_code" id="sa_province_code">
                            <input type="hidden" name="municipality_code" id="sa_municipality_code">
                        </div>
                        <div>
                            <label class="fld-label" for="sa_barangay">Barangay *</label>
                            <select id="sa_barangay" name="barangay" class="form-input @error('barangay') is-invalid @enderror" disabled>
                                <option value="">— Select City first —</option>
                            </select>
                            @error('barangay')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="fld-label" for="zip_code">Postal Code *</label>
                            <input type="text" id="zip_code" name="zip_code" value="{{ old('zip_code') }}" placeholder="e.g. 1121" inputmode="numeric" maxlength="4"
                                   class="form-input @error('zip_code') is-invalid @enderror">
                            @error('zip_code')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="fld-label" for="street">Street Address *</label>
                            <input type="text" id="street" name="street" value="{{ old('street') }}" placeholder="e.g. Blk 12 Lot 8, Sampaguita St."
                                   class="form-input @error('street') is-invalid @enderror">
                            @error('street')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                </section>

                {{-- 4. Documents --}}
                <section class="form-card">
                    <h2 class="card-title"><span class="card-num">4</span> Documents</h2>
                    <p class="-mt-2 mb-3 text-xs" style="color:#8a7a70;">JPG, PNG or PDF · max 5 MB each. Only the admin team can see these.</p>
                    <div class="space-y-3">
                        @foreach ([
                            ['government_id',   'Government-issued ID *', 'Passport, driver\'s license, UMID, PhilID, etc.', ''],
                            ['dti_certificate', 'DTI Certificate *',      'Business name registration certificate.',        'dti'],
                            ['sec_certificate', 'SEC Certificate *',      'Required for corporations.',                     'sec'],
                            ['bir_2303',        'BIR Form 2303 *',        'Certificate of Registration from the BIR.',      ''],
                        ] as [$field, $label, $hint, $group])
                            <div @if ($group) data-doc-group="{{ $group }}" @endif>
                                <label class="fld-label">{{ $label }}</label>
                                <label for="{{ $field }}" class="upload-row @error($field) is-invalid @enderror" data-upload-row>
                                    <span class="upload-thumb" data-upload-thumb>
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold" style="color:#3a2f29;" data-upload-name>Click to upload</span>
                                        <span class="block truncate text-xs" style="color:#8a7a70;" data-upload-hint data-hint="{{ $hint }}">{{ $hint }}</span>
                                    </span>
                                </label>
                                <input type="file" id="{{ $field }}" name="{{ $field }}" accept="image/jpeg,image/png,.pdf" class="hidden">
                                @error($field)<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- 5. Security --}}
                <section class="form-card">
                    <h2 class="card-title"><span class="card-num">5</span> Account Password</h2>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="fld-label" for="password">Password *</label>
                            <div class="relative">
                                <input type="password" id="password" name="password" data-password autocomplete="new-password"
                                       class="form-input pr-11 @error('password') is-invalid @enderror">
                                <button type="button" data-toggle-password aria-label="Show password"
                                        class="absolute right-3 top-1/2 -translate-y-1/2" style="color:#8a7a70;"></button>
                            </div>
                        </div>
                        <div>
                            <label class="fld-label" for="password_confirmation">Confirm Password *</label>
                            <div class="relative">
                                <input type="password" id="password_confirmation" name="password_confirmation" data-password autocomplete="new-password"
                                       class="form-input pr-11">
                                <button type="button" data-toggle-password aria-label="Show password"
                                        class="absolute right-3 top-1/2 -translate-y-1/2" style="color:#8a7a70;"></button>
                            </div>
                        </div>
                    </div>
                    @error('password')
                        <p class="field-error">{{ $message }}</p>
                    @else
                        <p class="mt-1.5 text-[11px]" style="color:#8a7a70;">At least 8 characters with an uppercase letter, a lowercase letter, a number and a symbol.</p>
                    @enderror
                </section>
            </div>

            {{-- Footer (sticky) --}}
            <div class="sticky-submit px-6 py-4 sm:px-10">
                <label class="flex cursor-pointer items-start gap-3">
                    <input type="checkbox" id="terms" name="terms" value="1" class="mt-0.5 h-4 w-4 shrink-0" style="accent-color:#fa4e1c;" @checked(old('terms'))>
                    <span class="text-sm" style="color:#5a4d45;">
                        I agree to ALVY's
                        <a href="{{ route('policies.show', 'terms') }}" target="_blank" class="font-semibold underline" style="color:#fa4e1c;">Terms and Conditions</a>
                        and
                        <a href="{{ route('policies.show', 'privacy') }}" target="_blank" class="font-semibold underline" style="color:#fa4e1c;">Privacy Policy</a>.
                    </span>
                </label>
                @error('terms')<p class="field-error">{{ $message }}</p>@enderror

                <div class="mt-4 flex flex-col-reverse items-center gap-3 sm:flex-row sm:justify-between">
                    <p class="text-sm" style="color:#8a7a70;">
                        Already have an account?
                        <a href="{{ route('login') }}" class="font-bold" style="color:#222;">Log In</a>
                    </p>
                    <button type="submit" id="submitBtn"
                            class="flex w-full items-center justify-center gap-2 rounded-xl px-8 py-3 text-sm font-bold text-white transition disabled:opacity-60 sm:w-auto"
                            style="background:linear-gradient(135deg,#fa4e1c,#ff7a45);box-shadow:0 12px 24px -12px rgba(250,78,28,.8);">
                        Sign Up as Seller
                    </button>
                </div>
            </div>
        </form>

<x-slot:scripts>
@include('partials.password-toggle')
<script src="{{ asset('js/register-address-fallback.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Region → Province → City → Barangay (changing the region clears everything below it)
    RegisterAddressFallback.attach({
        region: '#sa_region', province: '#sa_province', city: '#sa_municipality', barangay: '#sa_barangay',
        provinceCode: '#sa_province_code', cityCode: '#sa_municipality_code',
        old: {
            region:       @json(old('region')),
            province:     @json(old('province')),
            municipality: @json(old('municipality')),
            barangay:     @json(old('barangay')),
        },
    });

    // Corporations upload an SEC certificate; everyone else a DTI certificate.
    var sellerType = document.getElementById('seller_type');
    function syncDocs() {
        var corp = sellerType.value === 'Corporation';
        document.querySelector('[data-doc-group="dti"]').style.display = corp ? 'none' : '';
        document.querySelector('[data-doc-group="sec"]').style.display = corp ? '' : 'none';
    }
    sellerType.addEventListener('change', syncDocs);
    syncDocs();

    // Show the chosen file's name, size and (for images) a preview
    document.querySelectorAll('[data-upload-row]').forEach(function (row) {
        var input = document.getElementById(row.getAttribute('for'));
        var thumb = row.querySelector('[data-upload-thumb]');
        var thumbHtml = thumb.innerHTML;
        var hint = row.querySelector('[data-upload-hint]');
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            row.classList.toggle('has-file', !!file);
            row.classList.remove('is-invalid');
            row.querySelector('[data-upload-name]').textContent = file ? file.name : 'Click to upload';
            hint.textContent = file ? (file.size / 1048576).toFixed(1) + ' MB' : hint.dataset.hint;
            thumb.innerHTML = thumbHtml;
            if (!file) return;
            if (/^image\//.test(file.type)) {
                var img = document.createElement('img');
                img.alt = '';
                img.src = URL.createObjectURL(file);
                thumb.innerHTML = '';
                thumb.appendChild(img);
            } else {
                thumb.textContent = (file.name.split('.').pop() || 'FILE').toUpperCase().slice(0, 4);
            }
        });
    });

    // One submit only — a double click won't send the form twice.
    var form = document.getElementById('sellerRegisterForm');
    var submitting = false;
    form.addEventListener('submit', function (e) {
        if (submitting) { e.preventDefault(); return; }
        submitting = true;
        var btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.textContent = 'Creating your account…';
    });
});
</script>
</x-slot:scripts>
</x-auth-shell>
