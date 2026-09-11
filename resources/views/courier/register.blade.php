<x-layout title="Sorting Center Registration — ALVY">
<div class="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">

    {{-- Page hero --}}
    <div class="mb-8 text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl text-3xl text-white"
             style="background:linear-gradient(135deg,#002b4d 0%,#003d6b 100%);">🏭</div>
        <h1 class="mt-4 font-display text-3xl font-bold" style="color:#222;">Sorting Center Registration</h1>
        <p class="mt-2 text-sm" style="color:#6B7280;">
            Join the ALVY Logistics network. Applications are reviewed within 1–3 business days.
        </p>
    </div>

    {{-- ── Already applied states ───────────────────────────────── --}}
    @if (isset($existing) && $existing?->isPending())
        <div class="rounded-2xl p-8 text-center" style="background:#fff;border:1px solid #cfdce8;">
            <span class="text-4xl">⏳</span>
            <p class="mt-4 font-display text-xl font-bold" style="color:#222;">Application Under Review</p>
            <p class="mt-2 text-sm" style="color:#6B7280;">
                Submitted {{ $existing->created_at->format('M d, Y') }}.
                We'll email you at <strong>{{ auth()->user()->email }}</strong> once it's reviewed.
            </p>
            <a href="{{ route('courier.status') }}"
               class="mt-6 inline-flex rounded-xl px-6 py-3 text-sm font-bold text-white"
               style="background:#002b4d;">View Status</a>
        </div>

    @elseif (isset($existing) && $existing?->isApproved())
        <div class="rounded-2xl p-8 text-center" style="background:#fff;border:1px solid #cfdce8;">
            <span class="text-4xl">✅</span>
            <p class="mt-4 font-display text-xl font-bold" style="color:#059669;">You're already registered!</p>
            <a href="{{ route('courier.dashboard') }}"
               class="mt-6 inline-flex rounded-xl px-6 py-3 text-sm font-bold text-white"
               style="background:#002b4d;">Go to Dashboard</a>
        </div>

    @else

        {{-- Rejected banner --}}
        @if (isset($existing) && $existing?->isRejected())
            <div class="mb-6 rounded-2xl border p-5" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);">
                <p class="font-semibold" style="color:#DC2626;">❌ Previous application rejected.</p>
                @if ($existing->rejection_reason)
                    <p class="mt-1 text-sm" style="color:#6B7280;">Reason: {{ $existing->rejection_reason }}</p>
                @endif
                <p class="mt-2 text-sm" style="color:#6B7280;">You may re-apply below.</p>
            </div>
        @endif

        {{-- Validation errors --}}
        @if ($errors->any())
            <div class="mb-6 rounded-xl border p-4 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-2xl p-8" style="background:#fff;border:1px solid #cfdce8;">
            <form action="{{ route('courier.register.store') }}" method="POST"
                  enctype="multipart/form-data" class="space-y-8">
                @csrf

                {{-- ── 1. Personal Information ──────────────────────── --}}
                <div>
                    <h2 class="mb-4 font-display text-base font-bold" style="color:#222;">
                        1 · Personal Information
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2">

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Last Name *</label>
                            <input type="text" name="last_name"
                                   value="{{ old('last_name', auth()->user()->last_name ?? '') }}"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                                   style="border-color:#cfdce8;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                                   required>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">First Name *</label>
                            <input type="text" name="first_name"
                                   value="{{ old('first_name', auth()->user()->first_name ?? '') }}"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                                   style="border-color:#cfdce8;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                                   required>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Middle Initial</label>
                            <input type="text" name="middle_initial" maxlength="5"
                                   value="{{ old('middle_initial', auth()->user()->middle_initial ?? '') }}"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                                   style="border-color:#cfdce8;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                                   placeholder="e.g. A">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Sex *</label>
                            <select name="sex"
                                    class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                                    style="border-color:#cfdce8;"
                                    onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                                    required>
                                <option value="">— Select —</option>
                                @foreach(['Male','Female'] as $s)
                                    <option value="{{ $s }}" @selected(old('sex') === $s)>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Email *</label>
                            <input type="email" value="{{ auth()->user()->email }}"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm"
                                   style="border-color:#cfdce8;background:#F9FAFB;color:#6b90aa;" readonly>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Contact No. *</label>
                            <input type="text" name="contact_no"
                                   value="{{ old('contact_no', auth()->user()->contact_no ?? auth()->user()->phone ?? '') }}"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                                   style="border-color:#cfdce8;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                                   placeholder="+63 912 345 6789" required>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Birthday *</label>
                            <input type="date" id="sc_birthday" name="birthday"
                                   value="{{ old('birthday') }}"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                                   style="border-color:#cfdce8;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                                   required>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Age</label>
                            <input type="number" id="sc_age" name="age_display"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm"
                                   style="border-color:#cfdce8;background:#F9FAFB;color:#6b90aa;"
                                   placeholder="Auto-calculated" readonly>
                        </div>

                    </div>
                </div>

                {{-- ── 2. Address ────────────────────────────────────── --}}
                <div>
                    <h2 class="mb-4 font-display text-base font-bold" style="color:#222;">
                        2 · Address
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2">

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Province *</label>
                            <select id="sc_province" name="province"
                                    class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                                    style="border-color:#cfdce8;"
                                    onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                                    onchange="scLoadMunicipalities(this.options[this.selectedIndex].dataset.code)"
                                    required disabled>
                                <option value="">Loading provinces…</option>
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Municipality / City *</label>
                            <select id="sc_municipality" name="municipality"
                                    class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                                    style="border-color:#cfdce8;"
                                    onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                                    onchange="scLoadBarangays(this.options[this.selectedIndex].dataset.code)"
                                    required disabled>
                                <option value="">— Select Province first —</option>
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Barangay *</label>
                            <select id="sc_barangay" name="barangay"
                                    class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                                    style="border-color:#cfdce8;"
                                    onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                                    required disabled>
                                <option value="">— Select Municipality first —</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Street</label>
                            <input type="text" name="street" value="{{ old('street') }}"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                                   style="border-color:#cfdce8;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                                   placeholder="Street name">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">House / Unit No.</label>
                            <input type="text" name="house_number" value="{{ old('house_number') }}"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                                   style="border-color:#cfdce8;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                                   placeholder="House / unit number">
                        </div>

                    </div>
                </div>

                {{-- ── 3. Business Information ───────────────────────── --}}
                <div>
                    <h2 class="mb-4 font-display text-base font-bold" style="color:#222;">
                        3 · Business Information
                    </h2>
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Business Name <span class="font-normal">(optional)</span></label>
                        <input type="text" name="business_name" value="{{ old('business_name') }}"
                               class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none"
                               style="border-color:#cfdce8;"
                               onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                               placeholder="Registered business / sorting center name">
                    </div>
                </div>

                {{-- ── 4. Documents ──────────────────────────────────── --}}
                <div>
                    <h2 class="mb-4 font-display text-base font-bold" style="color:#222;">
                        4 · Documents
                    </h2>
                    <div class="grid gap-4 sm:grid-cols-2">

                        <div class="rounded-xl border p-4" style="border-color:#cfdce8;">
                            <label class="block text-xs font-semibold mb-2" style="color:#6B7280;">
                                Upload Valid ID *
                            </label>
                            <input type="file" name="id_upload" accept="image/*,.pdf"
                                   class="w-full text-sm" required
                                   onchange="previewFile(this, 'preview_id')">
                            <p class="mt-1 text-[11px]" style="color:#6b90aa;">JPG, PNG or PDF · max 5 MB</p>
                            <img id="preview_id" class="mt-3 hidden h-24 w-full rounded-lg object-cover"
                                 alt="ID preview">
                        </div>

                        <div class="rounded-xl border p-4" style="border-color:#cfdce8;">
                            <label class="block text-xs font-semibold mb-2" style="color:#6B7280;">
                                Upload Business / DTI Permit
                                <span class="font-normal">(optional)</span>
                            </label>
                            <input type="file" name="dti_permit" accept="image/*,.pdf"
                                   class="w-full text-sm"
                                   onchange="previewFile(this, 'preview_dti')">
                            <p class="mt-1 text-[11px]" style="color:#6b90aa;">JPG, PNG or PDF · max 5 MB</p>
                            <img id="preview_dti" class="mt-3 hidden h-24 w-full rounded-lg object-cover"
                                 alt="DTI permit preview">
                        </div>

                    </div>
                </div>

                {{-- Notice --}}
                <div class="rounded-xl border p-4 text-sm" style="background:#FFF8F3;border-color:#cfdce8;">
                    <p style="color:#1a4d6e;">
                        📧 After submitting your registration, please wait for the administrator's approval,
                        which will be sent to your email at
                        <strong>{{ auth()->user()->email }}</strong>.
                    </p>
                </div>

                {{-- Actions --}}
                <div class="flex gap-3 pt-2">
                    <button type="submit"
                            class="flex-1 rounded-xl py-3 text-sm font-bold text-white transition hover:opacity-90"
                            style="background:#002b4d;">
                        Submit Registration
                    </button>
                    <a href="{{ route('profile.show') }}"
                       class="rounded-xl border px-6 py-3 text-sm font-semibold transition"
                       style="border-color:#cfdce8;color:#6B7280;"
                       onmouseover="this.style.background='#F9FAFB';" onmouseout="this.style.background='';">
                        Cancel
                    </a>
                </div>

            </form>
        </div>
    @endif

</div>

<script>
// ── Age auto-calculate ─────────────────────────────────────
document.getElementById('sc_birthday').addEventListener('change', function () {
    const dob = new Date(this.value);
    if (isNaN(dob)) return;
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const m = today.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
    document.getElementById('sc_age').value = age >= 0 ? age : '';
});

// ── File image preview ─────────────────────────────────────
function previewFile(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = e => {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        } else {
            preview.classList.add('hidden');
        }
    }
}

// ── PSGC Dropdowns ─────────────────────────────────────────
const PSGC = '/api/psgc';

function scPopulate(id, items, placeholder) {
    const el = document.getElementById(id);
    el.innerHTML = `<option value="">${placeholder}</option>`;
    items.forEach(item => {
        const o = document.createElement('option');
        o.value = item.name;
        o.dataset.code = item.code;
        o.textContent = item.name;
        el.appendChild(o);
    });
    el.disabled = false;
}

// Load provinces on page load
(async () => {
    try {
        const r = await fetch(`${PSGC}/provinces`);
        scPopulate('sc_province', await r.json(), '— Select Province —');

        const oldProvince = '{{ old("province") }}';
        if (oldProvince) {
            [...document.getElementById('sc_province').options].forEach(o => {
                if (o.value === oldProvince) {
                    o.selected = true;
                    scLoadMunicipalities(o.dataset.code);
                }
            });
        }
    } catch(e) {
        document.getElementById('sc_province').innerHTML =
            '<option value="">⚠ Could not load provinces — refresh to retry</option>';
        document.getElementById('sc_province').disabled = false;
    }
})();

async function scLoadMunicipalities(code) {
    const mEl = document.getElementById('sc_municipality');
    const bEl = document.getElementById('sc_barangay');
    mEl.innerHTML = '<option>Loading…</option>'; mEl.disabled = true;
    bEl.innerHTML = '<option>— Select Municipality first —</option>'; bEl.disabled = true;
    if (!code) return;
    try {
        const r = await fetch(`${PSGC}/provinces/${code}/municipalities`);
        scPopulate('sc_municipality', await r.json(), '— Select Municipality —');

        const oldMun = '{{ old("municipality") }}';
        if (oldMun) {
            [...document.getElementById('sc_municipality').options].forEach(o => {
                if (o.value === oldMun) {
                    o.selected = true;
                    scLoadBarangays(o.dataset.code);
                }
            });
        }
    } catch(e) {}
}

async function scLoadBarangays(code) {
    const bEl = document.getElementById('sc_barangay');
    bEl.innerHTML = '<option>Loading…</option>'; bEl.disabled = true;
    if (!code) return;
    try {
        const r = await fetch(`${PSGC}/municipalities/${code}/barangays`);
        scPopulate('sc_barangay', await r.json(), '— Select Barangay —');

        const oldBrgy = '{{ old("barangay") }}';
        if (oldBrgy) {
            [...document.getElementById('sc_barangay').options].forEach(o => {
                if (o.value === oldBrgy) o.selected = true;
            });
        }
    } catch(e) {}
}
</script>
</x-layout>
