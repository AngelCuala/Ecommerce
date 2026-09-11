<x-layout title="Create Account — ALVY">

<div class="mx-auto flex min-h-[70vh] max-w-2xl flex-col justify-center px-4 py-16 sm:px-6">
    <div class="text-center">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl font-display text-xl font-bold"
              style="background:#fa4e1c;color:#FFFFFF;">B</span>
        <h1 class="mt-4 font-display text-2xl font-bold" style="color:#222222;">Create your account</h1>
        <p class="mt-1 text-sm" style="color:#6b90aa;">Join ALVY for faster checkout and order history.</p>
    </div>

    <div class="card mt-8 p-8">
        @if ($errors->any())
            <div class="mb-5 rounded-xl border p-4 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('register') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div>
                <h2 class="text-sm font-bold" style="color:#222222;">Personal Information</h2>
                <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Last Name *</label>
                        <input type="text" name="last_name" value="{{ old('last_name') }}" class="input mt-1" required style="border-color:#FFDCC2;">
                    </div>
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">First Name *</label>
                        <input type="text" name="first_name" value="{{ old('first_name') }}" class="input mt-1" required style="border-color:#FFDCC2;">
                    </div>
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Middle Initial</label>
                        <input type="text" name="middle_initial" maxlength="5" value="{{ old('middle_initial') }}" class="input mt-1" style="border-color:#FFDCC2;">
                    </div>
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Username *</label>
                        <input type="text" name="username" value="{{ old('username') }}" class="input mt-1" required style="border-color:#FFDCC2;">
                    </div>
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Password *</label>
                        <input type="password" name="password" class="input mt-1" required style="border-color:#FFDCC2;">
                    </div>
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Confirm Password *</label>
                        <input type="password" name="password_confirmation" class="input mt-1" required style="border-color:#FFDCC2;">
                    </div>
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Sex *</label>
                        <select name="sex" class="input mt-1" required style="border-color:#FFDCC2;">
                            <option value="">Select</option>
                            <option value="Male" @selected(old('sex') === 'Male')>Male</option>
                            <option value="Female" @selected(old('sex') === 'Female')>Female</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Email *</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="input mt-1" required style="border-color:#FFDCC2;">
                    </div>
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Contact No. *</label>
                        <input type="text" name="contact_no" value="{{ old('contact_no') }}" class="input mt-1" required style="border-color:#FFDCC2;">
                    </div>
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Birthday *</label>
                        <input type="date" id="birthday" name="birthday" value="{{ old('birthday') }}" class="input mt-1" required style="border-color:#FFDCC2;">
                    </div>
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Age</label>
                        <input type="text" id="age" name="age_display" class="input mt-1" readonly placeholder="Automatically generated" style="border-color:#FFDCC2;background:#F9FAFB;">
                    </div>
                </div>
            </div>

            <div>
                <h2 class="text-sm font-bold" style="color:#222222;">Address</h2>
                <p class="text-xs mt-0.5" style="color:#6b90aa;">Select your region, province, city/municipality, then barangay.</p>

                <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">

                    {{-- Region --}}
                    <div class="sm:col-span-2">
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Region *</label>
                        <select id="region_select" name="region" class="input mt-1" required style="border-color:#FFDCC2;"
                                onchange="loadProvincesByRegion(this.value, this.options[this.selectedIndex].text)">
                            <option value="">— Select Region —</option>
                        </select>
                        <p id="loading-region" class="mt-1 text-[11px] hidden" style="color:#fa4e1c;">Loading regions…</p>
                    </div>

                    {{-- Province --}}
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Province *</label>
                        <select id="province_select" name="province" class="input mt-1" required style="border-color:#FFDCC2;"
                                disabled onchange="loadMunicipalities(this.value, this.options[this.selectedIndex].text)">
                            <option value="">— Select Province —</option>
                        </select>
                        <input type="hidden" name="province_code" id="province_code">
                    </div>

                    {{-- Municipality / City --}}
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Municipality / City *</label>
                        <select id="municipality_select" name="municipality" class="input mt-1" required
                                style="border-color:#FFDCC2;" disabled
                                onchange="loadBarangays(this.value, this.options[this.selectedIndex].text)">
                            <option value="">— Select Province first —</option>
                        </select>
                        <input type="hidden" name="municipality_code" id="municipality_code">
                    </div>

                    {{-- Barangay --}}
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Barangay *</label>
                        <select id="barangay_select" name="barangay" class="input mt-1" required
                                style="border-color:#FFDCC2;" disabled>
                            <option value="">— Select Municipality first —</option>
                        </select>
                    </div>

                    {{-- ZIP Code --}}
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">ZIP Code</label>
                        <input type="text" name="zip_code" value="{{ old('zip_code') }}" class="input mt-1"
                               style="border-color:#FFDCC2;" placeholder="e.g. 1000">
                    </div>

                    {{-- House Number --}}
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">House / Unit Number *</label>
                        <input type="text" name="house_number" value="{{ old('house_number') }}" class="input mt-1" required
                               style="border-color:#FFDCC2;" placeholder="e.g. 12B">
                    </div>

                    {{-- Street --}}
                    <div class="sm:col-span-2">
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Street / Subdivision *</label>
                        <input type="text" name="street" value="{{ old('street') }}" class="input mt-1" required
                               style="border-color:#FFDCC2;" placeholder="e.g. Rizal Street, Sunshine Village">
                    </div>

                </div>
            </div>

            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Upload Valid ID *</label>
                <input type="file" name="valid_id" accept=".jpg,.jpeg,.png,.pdf" class="input mt-1" required style="border-color:#FFDCC2;">
                <p class="mt-1 text-xs" style="color:#6b90aa;">JPG, JPEG, PNG, or PDF. Max 5MB.</p>
            </div>

            <div class="flex items-start gap-2">
                <input type="checkbox" name="terms" id="terms" class="mt-1" required>
                <label for="terms" class="text-sm" style="color:#222222;">I agree to the terms and conditions</label>
            </div>

            <button type="submit" class="btn-gold w-full" style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;">Create Account</button>
        </form>

        <p class="mt-6 text-center text-sm" style="color:#6b90aa;">
            Already have an account?
            <a href="{{ route('login') }}" class="font-semibold underline" style="color:#fa4e1c;">Sign in</a>
        </p>
    </div>
</div>

<script>
    // ── Age auto-calculation ─────────────────────────────────
    document.getElementById('birthday').addEventListener('change', function () {
        const dob = new Date(this.value);
        const ageField = document.getElementById('age');
        if (isNaN(dob.getTime())) { ageField.value = ''; return; }
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        ageField.value = age >= 0 ? age : '';
    });

    // ── PSGC cascading address dropdowns ─────────────────────
    const PSGC = '/api/psgc';

    function setLoading(id, msg) {
        const el = document.getElementById(id);
        el.innerHTML = `<option value="">${msg}</option>`;
        el.disabled = true;
    }

    function populate(id, items, placeholder) {
        const el = document.getElementById(id);
        el.innerHTML = `<option value="">${placeholder}</option>`;
        items.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.name;
            opt.dataset.code = item.code;
            opt.textContent = item.name;
            el.appendChild(opt);
        });
        el.disabled = false;
    }

    function reset(id, placeholder) {
        const el = document.getElementById(id);
        el.innerHTML = `<option value="">${placeholder}</option>`;
        el.disabled = true;
    }

    // Load regions on page load
    window.addEventListener('DOMContentLoaded', async function () {
        setLoading('region_select', 'Loading regions…');
        try {
            const res  = await fetch(`${PSGC}/regions`);
            const data = await res.json();
            populate('region_select', data, '— Select Region —');
        } catch (e) {
            document.getElementById('region_select').innerHTML =
                '<option value="">⚠ Could not load regions. Refresh to retry.</option>';
        }
    });

    // Region → Province
    async function loadProvincesByRegion(regionName, label) {
        reset('province_select', '— Select Province —');
        reset('municipality_select', '— Select Province first —');
        reset('barangay_select', '— Select Municipality first —');
        if (!regionName) return;

        const sel  = document.getElementById('region_select');
        const code = sel.options[sel.selectedIndex].dataset.code;

        setLoading('province_select', 'Loading provinces…');
        try {
            const res  = await fetch(`${PSGC}/regions/${code}/provinces`);
            const data = await res.json();
            if (data.length === 0) {
                // NCR — no provinces, load cities directly
                populate('province_select', [{ code: code, name: 'Metro Manila (NCR)' }], '— Select Province —');
                await loadMunicipalities('Metro Manila (NCR)', null, code);
            } else {
                populate('province_select', data, '— Select Province —');
            }
        } catch (e) {
            document.getElementById('province_select').innerHTML =
                '<option value="">⚠ Could not load provinces</option>';
        }
    }

    // Province → Municipality
    async function loadMunicipalities(provinceName, label, overrideCode) {
        reset('municipality_select', '— Select Municipality / City —');
        reset('barangay_select', '— Select Municipality first —');

        let code = overrideCode;
        if (!code) {
            const sel = document.getElementById('province_select');
            code = sel.options[sel.selectedIndex]?.dataset.code;
        }
        document.getElementById('province_code').value = code || '';
        if (!code) return;

        setLoading('municipality_select', 'Loading cities…');
        try {
            const res  = await fetch(`${PSGC}/provinces/${code}/municipalities`);
            const data = await res.json();
            populate('municipality_select', data, '— Select Municipality / City —');
        } catch (e) {
            document.getElementById('municipality_select').innerHTML =
                '<option value="">⚠ Could not load municipalities</option>';
        }
    }

    // Municipality → Barangay
    async function loadBarangays(municipalityName, label) {
        reset('barangay_select', '— Select Barangay —');

        const sel  = document.getElementById('municipality_select');
        const code = sel.options[sel.selectedIndex]?.dataset.code;
        document.getElementById('municipality_code').value = code || '';
        if (!code) return;

        setLoading('barangay_select', 'Loading barangays…');
        try {
            const res  = await fetch(`${PSGC}/municipalities/${code}/barangays`);
            const data = await res.json();
            populate('barangay_select', data, '— Select Barangay —');
        } catch (e) {
            document.getElementById('barangay_select').innerHTML =
                '<option value="">⚠ Could not load barangays</option>';
        }
    }
</script>

</x-layout>