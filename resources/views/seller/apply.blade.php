<x-layout title="Become a Seller — ALVY">
@include('profile._styles')
@php $u = auth()->user(); @endphp
<style>
    .pf-input.is-invalid { border-color:#DC2626; }
    .fld-error { margin-top:.3rem; font-size:.75rem; color:#DC2626; }
    .upload-row { display:flex; align-items:center; gap:.875rem; border:1.5px dashed #E6D9CF; border-radius:.75rem; padding:.875rem 1rem; background:#FDF8F5; cursor:pointer; transition:all .2s; }
    .upload-row:hover { border-color:#fa4e1c; background:#fff5f3; }
    .upload-row.has-file { border-style:solid; border-color:#fa4e1c; background:#fff5f3; }
    .upload-row.is-invalid { border-color:#DC2626; }
</style>

<div class="pf-page">
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 lg:py-10">

    {{-- Header --}}
    <div class="mb-6 text-center">
        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl text-white"
              style="background:linear-gradient(135deg,#fa4e1c,#ff8a5b);box-shadow:0 10px 24px -12px rgba(250,78,28,.8);">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M3 9l1-5h16l1 5M4 9h16v10a1 1 0 01-1 1H5a1 1 0 01-1-1V9zM9 13h6"/></svg>
        </span>
        <h1 class="mt-4 font-display text-3xl font-extrabold" style="color:#222;">Become a Seller</h1>
        <p class="mt-1 text-sm pf-muted">Join the ALVY Seller Center. Applications are reviewed within 1–3 business days.</p>
    </div>

    {{-- Status states --}}
    @if (isset($existing) && $existing?->isPending())
        <div class="pf-card p-8 text-center">
            <span class="pf-icon mx-auto h-14 w-14">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
            </span>
            <p class="mt-4 font-display text-xl font-extrabold" style="color:#222;">Application Under Review</p>
            <p class="mt-1 text-sm pf-muted">Submitted {{ $existing->created_at->format('M d, Y') }}. We'll email you once reviewed.</p>
            <a href="{{ route('profile.show') }}" class="pf-btn mt-6">Back to Profile</a>
        </div>

    @elseif (isset($existing) && $existing?->isApproved())
        <div class="pf-card p-8 text-center">
            <span class="pf-icon mx-auto h-14 w-14" style="background:#ECFDF5;color:#059669;">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
            </span>
            <p class="mt-4 font-display text-xl font-extrabold" style="color:#059669;">You're already a Seller!</p>
            <a href="{{ route('seller.dashboard') }}" class="pf-btn mt-6">Go to Seller Dashboard</a>
        </div>

    @else
        @if (isset($existing) && $existing?->isRejected())
            <div class="pf-alert pf-alert-error mb-5">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <strong>Previous application rejected.</strong>
                    @if ($existing->rejection_reason)
                        <p class="mt-0.5">Reason: {{ $existing->rejection_reason }}</p>
                    @endif
                </div>
            </div>
        @endif

        @if (session('error'))
            <div class="pf-alert pf-alert-error mb-5">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="pf-alert pf-alert-error mb-5">
                <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <strong>Please fix the {{ $errors->count() === 1 ? 'field' : $errors->count() . ' fields' }} marked below.</strong>
                </div>
            </div>
        @endif

        <form action="{{ route('seller.apply.store') }}" method="POST" enctype="multipart/form-data" class="space-y-5" id="sellerApplyForm" novalidate>
            @csrf

            {{-- ═══ 1. Personal information ═══ --}}
            <section class="pf-card p-6 sm:p-7">
                <div class="mb-5 flex items-center gap-3">
                    <span class="pf-icon"><span class="text-sm font-extrabold">1</span></span>
                    <div>
                        <h2 class="pf-card-title">Personal Information</h2>
                        <p class="text-xs pf-muted">The account owner applying for the shop.</p>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="pf-label" for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" value="{{ old('first_name', $u->first_name) }}"
                               class="pf-input @error('first_name') is-invalid @enderror" required>
                        @error('first_name')<p class="fld-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="pf-label" for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" value="{{ old('last_name', $u->last_name) }}"
                               class="pf-input @error('last_name') is-invalid @enderror" required>
                        @error('last_name')<p class="fld-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="pf-label" for="email">Email</label>
                        <input type="email" id="email" value="{{ $u->email }}" class="pf-input" readonly>
                        <p class="mt-1 text-[11px] pf-muted">Your account email — we'll send updates here.</p>
                    </div>
                    <div>
                        <label class="pf-label" for="phone">Mobile Number *</label>
                        <input type="tel" id="phone" name="phone" inputmode="numeric" placeholder="09171234567"
                               value="{{ old('phone', $u->contact_no ?: $u->phone) }}"
                               class="pf-input @error('phone') is-invalid @enderror" required>
                        @error('phone')<p class="fld-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            {{-- ═══ 2. Shop information ═══ --}}
            <section class="pf-card p-6 sm:p-7">
                <div class="mb-5 flex items-center gap-3">
                    <span class="pf-icon"><span class="text-sm font-extrabold">2</span></span>
                    <div>
                        <h2 class="pf-card-title">Shop Information</h2>
                        <p class="text-xs pf-muted">How your shop appears to buyers, and how it's registered.</p>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="pf-label" for="shop_name">Shop Name *</label>
                        <input type="text" id="shop_name" name="shop_name" value="{{ old('shop_name') }}" maxlength="100"
                               class="pf-input @error('shop_name') is-invalid @enderror" placeholder="e.g. Juan's Handicrafts" required>
                        @error('shop_name')<p class="fld-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="pf-label" for="line_of_business">Shop Category *</label>
                        <select id="line_of_business" name="line_of_business" class="pf-input @error('line_of_business') is-invalid @enderror" required>
                            <option value="">Select category</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat }}" @selected(old('line_of_business') === $cat)>{{ $cat }}</option>
                            @endforeach
                        </select>
                        @error('line_of_business')<p class="fld-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="pf-label" for="seller_type">Seller Type *</label>
                        <select id="seller_type" name="seller_type" class="pf-input @error('seller_type') is-invalid @enderror" required>
                            <option value="">Select seller type</option>
                            @foreach ($sellerTypes as $type)
                                <option value="{{ $type }}" @selected(old('seller_type') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                        @error('seller_type')<p class="fld-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="pf-label" for="tin">TIN (Taxpayer Identification Number) *</label>
                        <input type="text" id="tin" name="tin" value="{{ old('tin') }}" inputmode="numeric" maxlength="15"
                               class="pf-input @error('tin') is-invalid @enderror" placeholder="123-456-789-000" required>
                        @error('tin')
                            <p class="fld-error">{{ $message }}</p>
                        @else
                            <p class="mt-1 text-[11px] pf-muted">9 digits, or 12 with your branch code.</p>
                        @enderror
                    </div>
                </div>
            </section>

            {{-- ═══ 3. Shop address ═══ --}}
            <section class="pf-card p-6 sm:p-7">
                <div class="mb-5 flex items-center gap-3">
                    <span class="pf-icon"><span class="text-sm font-extrabold">3</span></span>
                    <div>
                        <h2 class="pf-card-title">Shop Address</h2>
                        <p class="text-xs pf-muted">Where couriers pick up your orders.</p>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="pf-label" for="sa_region">Region *</label>
                        <select id="sa_region" name="region" class="pf-input @error('region') is-invalid @enderror" required>
                            <option value="">— Select Region —</option>
                        </select>
                        @error('region')<p class="fld-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="pf-label" for="sa_province">Province *</label>
                        <select id="sa_province" name="province" class="pf-input @error('province') is-invalid @enderror" required disabled>
                            <option value="">— Select Region first —</option>
                        </select>
                        @error('province')<p class="fld-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="pf-label" for="sa_municipality">City / Municipality *</label>
                        <select id="sa_municipality" name="municipality" class="pf-input @error('municipality') is-invalid @enderror" required disabled>
                            <option value="">— Select Province first —</option>
                        </select>
                        @error('municipality')<p class="fld-error">{{ $message }}</p>@enderror
                        {{-- PSGC codes of the selected province/municipality (official pickup origin for routing) --}}
                        <input type="hidden" name="province_code" id="sa_province_code">
                        <input type="hidden" name="municipality_code" id="sa_municipality_code">
                    </div>
                    <div>
                        <label class="pf-label" for="sa_barangay">Barangay *</label>
                        <select id="sa_barangay" name="barangay" class="pf-input @error('barangay') is-invalid @enderror" required disabled>
                            <option value="">— Select City first —</option>
                        </select>
                        @error('barangay')<p class="fld-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="pf-label" for="zip_code">Postal Code *</label>
                        <input type="text" id="zip_code" name="zip_code" value="{{ old('zip_code') }}" inputmode="numeric" maxlength="4"
                               class="pf-input @error('zip_code') is-invalid @enderror" placeholder="e.g. 1121" required>
                        @error('zip_code')<p class="fld-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="pf-label" for="street">Street Address *</label>
                        <input type="text" id="street" name="street" value="{{ old('street') }}"
                               class="pf-input @error('street') is-invalid @enderror" placeholder="e.g. Blk 12 Lot 8, Sampaguita St." required>
                        @error('street')<p class="fld-error">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            {{-- ═══ 4. Documents ═══ --}}
            <section class="pf-card p-6 sm:p-7">
                <div class="mb-5 flex items-center gap-3">
                    <span class="pf-icon"><span class="text-sm font-extrabold">4</span></span>
                    <div>
                        <h2 class="pf-card-title">Documents</h2>
                        <p class="text-xs pf-muted">JPG, PNG or PDF · max 5 MB each. Only the admin team can see these.</p>
                    </div>
                </div>
                <div class="space-y-3">
                    @foreach ([
                        ['government_id',   'Government-issued ID *', 'Passport, driver\'s license, UMID, PhilID, etc.'],
                        ['dti_certificate', 'DTI Certificate *',      'Business name registration certificate.'],
                        ['bir_2303',        'BIR Form 2303 *',        'Certificate of Registration from the BIR.'],
                    ] as [$field, $label, $hint])
                        <div>
                            <label class="pf-label">{{ $label }}</label>
                            <label for="{{ $field }}" class="upload-row @error($field) is-invalid @enderror" data-upload-row>
                                <span class="pf-icon">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold" style="color:#3a2f29;" data-upload-name>Click to upload</span>
                                    <span class="block truncate text-xs pf-muted" data-upload-hint>{{ $hint }}</span>
                                </span>
                            </label>
                            <input type="file" id="{{ $field }}" name="{{ $field }}" accept="image/jpeg,image/png,.pdf" class="hidden" required>
                            @error($field)<p class="fld-error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- ═══ 5. Confirm & submit ═══ --}}
            <section class="pf-card p-6 sm:p-7">
                <div class="mb-5 flex items-center gap-3">
                    <span class="pf-icon"><span class="text-sm font-extrabold">5</span></span>
                    <div>
                        <h2 class="pf-card-title">Confirm It's You</h2>
                        <p class="text-xs pf-muted">Enter your ALVY account password to submit this application.</p>
                    </div>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="pf-label" for="password">Password *</label>
                        <div class="relative">
                            <input type="password" id="password" name="password" data-password autocomplete="current-password"
                                   class="pf-input pr-11 @error('password') is-invalid @enderror" required>
                            <button type="button" data-toggle-password aria-label="Show password"
                                    class="absolute right-3 top-1/2 -translate-y-1/2" style="color:#8a7a70;"></button>
                        </div>
                        @error('password')<p class="fld-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="pf-label" for="password_confirmation">Confirm Password *</label>
                        <div class="relative">
                            <input type="password" id="password_confirmation" name="password_confirmation" data-password autocomplete="current-password"
                                   class="pf-input pr-11" required>
                            <button type="button" data-toggle-password aria-label="Show password"
                                    class="absolute right-3 top-1/2 -translate-y-1/2" style="color:#8a7a70;"></button>
                        </div>
                    </div>
                </div>

                <label class="mt-5 flex cursor-pointer items-start gap-3 rounded-xl p-4" style="background:#FDF8F5;border:1px solid #F5EBE4;">
                    <input type="checkbox" id="terms" name="terms" value="1" class="mt-0.5 h-4 w-4 shrink-0" style="accent-color:#fa4e1c;" @checked(old('terms')) required>
                    <span class="text-sm pf-text">
                        I agree to ALVY's
                        <a href="{{ route('policies.show', 'terms') }}" target="_blank" class="font-semibold underline" style="color:#fa4e1c;">Terms and Conditions</a>
                        and
                        <a href="{{ route('policies.show', 'privacy') }}" target="_blank" class="font-semibold underline" style="color:#fa4e1c;">Privacy Policy</a>,
                        and confirm the information above is true.
                    </span>
                </label>
                @error('terms')<p class="fld-error">{{ $message }}</p>@enderror

                <div class="mt-5 flex flex-col-reverse gap-3 border-t pt-5 sm:flex-row sm:items-center sm:justify-between" style="border-color:#F0E4DC;">
                    <p class="text-xs pf-muted">We'll notify you at <strong class="pf-text">{{ $u->email }}</strong> once reviewed.</p>
                    <div class="flex gap-3">
                        <a href="{{ route('profile.show') }}" class="pf-btn-ghost">Cancel</a>
                        <button type="submit" id="applySubmit" class="pf-btn px-8">Submit Application</button>
                    </div>
                </div>
            </section>
        </form>
    @endif

</div>
</div>

@include('partials.password-toggle')
<script src="{{ asset('js/register-address-fallback.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Region → Province → City → Barangay dropdowns
    if (window.RegisterAddressFallback && document.getElementById('sa_region')) {
        RegisterAddressFallback.attach({
            region:       '#sa_region',
            province:     '#sa_province',
            city:         '#sa_municipality',
            barangay:     '#sa_barangay',
            provinceCode: '#sa_province_code',
            cityCode:     '#sa_municipality_code',
            old: {
                region:       @json(old('region')),
                province:     @json(old('province')),
                municipality: @json(old('municipality')),
                barangay:     @json(old('barangay')),
            },
        });
    }

    // Show the chosen file name in each upload row
    document.querySelectorAll('[data-upload-row]').forEach(function (row) {
        var input = document.getElementById(row.getAttribute('for'));
        if (!input) return;
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            row.classList.toggle('has-file', !!file);
            row.classList.remove('is-invalid');
            row.querySelector('[data-upload-name]').textContent = file ? file.name : 'Click to upload';
            row.querySelector('[data-upload-hint]').textContent = file
                ? (file.size / 1048576).toFixed(1) + ' MB'
                : row.querySelector('[data-upload-hint]').dataset.hint || '';
        });
        row.querySelector('[data-upload-hint]').dataset.hint = row.querySelector('[data-upload-hint]').textContent;
    });

    // Prevent double submits
    var form = document.getElementById('sellerApplyForm');
    if (form) form.addEventListener('submit', function () {
        var btn = document.getElementById('applySubmit');
        btn.disabled = true;
        btn.textContent = 'Submitting…';
    });
});
</script>
</x-layout>
