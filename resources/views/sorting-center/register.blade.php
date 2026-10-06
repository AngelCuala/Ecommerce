<x-layout title="Logistics / Sorting Center Registration — ALVY">
<div class="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">

    <div class="mb-8 text-center">
        <span class="flex h-16 w-16 mx-auto items-center justify-center rounded-2xl text-3xl text-white"
              style="background:linear-gradient(135deg,#002b4d 0%,#003d6b 100%);" aria-hidden="true">📦</span>
        <h1 class="mt-4 font-display text-3xl font-bold" style="color:#222222;">Logistics / Sorting Center Registration</h1>
        <p class="mt-2 text-sm" style="color:#7A7A7A;">
            Register your sorting center for LogiSort. Your registered municipality becomes your delivery coverage.
        </p>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-xl border p-4 text-sm" role="alert" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="card p-8">
        <form action="{{ route('sc.register.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            {{-- Personal information --}}
            <div>
                <h2 class="text-sm font-bold mb-3" style="color:#222222;">Personal Information</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="last_name" class="text-xs font-semibold" style="color:#6b90aa;">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" class="input mt-1" required maxlength="100">
                    </div>
                    <div>
                        <label for="first_name" class="text-xs font-semibold" style="color:#6b90aa;">First Name *</label>
                        <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" class="input mt-1" required maxlength="100">
                    </div>
                    <div>
                        <label for="middle_initial" class="text-xs font-semibold" style="color:#6b90aa;">Middle Initial</label>
                        <input type="text" id="middle_initial" name="middle_initial" maxlength="5" value="{{ old('middle_initial') }}" class="input mt-1">
                    </div>
                    <div>
                        <label for="sex" class="text-xs font-semibold" style="color:#6b90aa;">Sex *</label>
                        <select id="sex" name="sex" class="input mt-1" required>
                            <option value="">Select</option>
                            @foreach (['Male', 'Female'] as $s)
                                <option value="{{ $s }}" @selected(old('sex') === $s)>{{ $s }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="email" class="text-xs font-semibold" style="color:#6b90aa;">E-mail *</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}" class="input mt-1" required autocomplete="email">
                    </div>
                    <div>
                        <label for="contact_no" class="text-xs font-semibold" style="color:#6b90aa;">Contact No. *</label>
                        <input type="tel" id="contact_no" name="contact_no" value="{{ old('contact_no') }}" class="input mt-1" required placeholder="09xx xxx xxxx">
                    </div>
                    <div>
                        <label for="birthday" class="text-xs font-semibold" style="color:#6b90aa;">Birthday *</label>
                        <input type="date" id="birthday" name="birthday" value="{{ old('birthday') }}" class="input mt-1" required max="{{ now()->subDay()->toDateString() }}">
                    </div>
                    <div>
                        <label for="age" class="text-xs font-semibold" style="color:#6b90aa;">Age *</label>
                        <input type="text" id="age" class="input mt-1" readonly placeholder="Auto" aria-describedby="age_hint" style="background:#F9FAFB;">
                        <p id="age_hint" class="text-[11px] mt-1" style="color:#B0B0B0;">Calculated from your birthday.</p>
                    </div>
                </div>
            </div>

            {{-- Business --}}
            <div>
                <h2 class="text-sm font-bold mb-3" style="color:#222222;">Business Information</h2>
                <label for="business_name" class="text-xs font-semibold" style="color:#6b90aa;">Business Name *</label>
                <input type="text" id="business_name" name="business_name" value="{{ old('business_name') }}" class="input mt-1" required maxlength="150"
                       placeholder="e.g. Luisiana Sorting Center">
            </div>

            {{-- Address (local PSA PSGC data) --}}
            <div>
                <h2 class="text-sm font-bold mb-1" style="color:#222222;">Address</h2>
                <p class="text-xs mt-0.5 mb-3" style="color:#6b90aa;">
                    Select your region, province, city/municipality, then barangay. The city/municipality is the area your center will serve.
                </p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="scr_region" class="text-xs font-semibold" style="color:#6b90aa;">Region *</label>
                        <select id="scr_region" name="region" class="input mt-1" required>
                            <option value="">— Select Region —</option>
                        </select>
                    </div>
                    <div>
                        <label for="scr_province" class="text-xs font-semibold" style="color:#6b90aa;">Province *</label>
                        <select id="scr_province" name="province" class="input mt-1" required disabled>
                            <option value="">— Select Province —</option>
                        </select>
                    </div>
                    <div>
                        <label for="scr_municipality" class="text-xs font-semibold" style="color:#6b90aa;">Municipality / City *</label>
                        <select id="scr_municipality" name="municipality" class="input mt-1" required disabled>
                            <option value="">— Select Province first —</option>
                        </select>
                    </div>
                    <div>
                        <label for="scr_barangay" class="text-xs font-semibold" style="color:#6b90aa;">Barangay *</label>
                        <select id="scr_barangay" name="barangay" class="input mt-1" required disabled>
                            <option value="">— Select Municipality first —</option>
                        </select>
                    </div>
                    <div>
                        <label for="zip_code" class="text-xs font-semibold" style="color:#6b90aa;">ZIP Code</label>
                        <input type="text" id="zip_code" name="zip_code" value="{{ old('zip_code') }}" class="input mt-1" maxlength="20">
                    </div>
                    <div>
                        <label for="house_number" class="text-xs font-semibold" style="color:#6b90aa;">House / Unit Number *</label>
                        <input type="text" id="house_number" name="house_number" value="{{ old('house_number') }}" class="input mt-1" required maxlength="50">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="street" class="text-xs font-semibold" style="color:#6b90aa;">Street / Subdivision *</label>
                        <input type="text" id="street" name="street" value="{{ old('street') }}" class="input mt-1" required maxlength="255">
                    </div>
                </div>
            </div>

            {{-- Documents --}}
            <div>
                <h2 class="text-sm font-bold mb-3" style="color:#222222;">Documents</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="government_id" class="text-xs font-semibold" style="color:#6b90aa;">Valid ID *</label>
                        <input type="file" id="government_id" name="government_id" accept="image/jpeg,image/png,.pdf" class="input mt-1 py-2" required>
                        <p class="text-[11px] mt-1" style="color:#B0B0B0;">JPG, PNG or PDF · max 5 MB</p>
                    </div>
                    <div>
                        <label for="business_permit" class="text-xs font-semibold" style="color:#6b90aa;">Business / DTI Permit *</label>
                        <input type="file" id="business_permit" name="business_permit" accept="image/jpeg,image/png,.pdf" class="input mt-1 py-2" required>
                        <p class="text-[11px] mt-1" style="color:#B0B0B0;">JPG, PNG or PDF · max 5 MB</p>
                    </div>
                </div>
            </div>

            {{-- Account --}}
            <div>
                <h2 class="text-sm font-bold mb-3" style="color:#222222;">Account</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="password" class="text-xs font-semibold" style="color:#6b90aa;">Password *</label>
                        <input type="password" id="password" name="password" class="input mt-1" required minlength="8" autocomplete="new-password">
                    </div>
                    <div>
                        <label for="password_confirmation" class="text-xs font-semibold" style="color:#6b90aa;">Confirm Password *</label>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="input mt-1" required minlength="8" autocomplete="new-password">
                    </div>
                </div>
            </div>

            <label class="flex items-start gap-2 text-sm" style="color:#555555;">
                <input type="checkbox" name="terms" value="1" class="mt-1" required @checked(old('terms'))>
                <span>I confirm the information and documents above are true and correct.</span>
            </label>

            <div class="rounded-xl border p-4 text-sm" style="background:#e8f0f6;border-color:#cfdce8;">
                <p style="color:#7A7A7A;">
                    <em>After submitting your registration, please wait for the administrator's approval, which will be sent to your email.</em>
                </p>
            </div>

            <div class="flex gap-3">
                <button type="submit" class="flex-1 rounded-xl py-3 text-sm font-bold text-white transition hover:opacity-90" style="background:#002b4d;">Submit Registration</button>
                <a href="{{ route('sc.login') }}" class="rounded-xl border px-6 py-3 text-sm font-semibold" style="border-color:#cfdce8;color:#7A7A7A;">Back to Login</a>
            </div>
        </form>
    </div>
</div>

<script src="{{ asset('js/psgc-address.js') }}"></script>
<script>
// Age (display only; the server recalculates it from the birthday)
(function () {
    const bday = document.getElementById('birthday');
    const age  = document.getElementById('age');
    function calc() {
        const dob = new Date(bday.value);
        if (isNaN(dob)) { age.value = ''; return; }
        const t = new Date();
        let a = t.getFullYear() - dob.getFullYear();
        const m = t.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && t.getDate() < dob.getDate())) a--;
        age.value = a >= 0 ? a : '';
    }
    bday.addEventListener('change', calc);
    calc();
})();

PsgcAddress.attach({
    region:   '#scr_region',
    province: '#scr_province',
    city:     '#scr_municipality',
    barangay: '#scr_barangay',
    old: {
        region:       @json(old('region')),
        province:     @json(old('province_code') ?: old('province')),
        municipality: @json(old('municipality_code') ?: old('municipality')),
        barangay:     @json(old('barangay')),
    },
});
</script>
</x-layout>
