<x-layout title="Become a Seller — ALVY">
<div class="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">

    <div class="mb-8 text-center">
        <span class="flex h-16 w-16 mx-auto items-center justify-center rounded-2xl text-3xl text-white"
              style="background:linear-gradient(135deg,#002b4d 0%,#003d6b 100%);">🏪</span>
        <h1 class="mt-4 font-display text-3xl font-bold" style="color:#222222;">Become a Seller</h1>
        <p class="mt-2 text-sm" style="color:#7A7A7A;">Join the ALVY Seller Center. Applications are reviewed within 1–3 business days.</p>
    </div>

    {{-- Status states --}}
    @if (isset($existing) && $existing?->isPending())
        <div class="card p-8 text-center">
            <span class="text-4xl">⏳</span>
            <p class="mt-4 font-display text-xl font-semibold" style="color:#222222;">Application Under Review</p>
            <p class="mt-2 text-sm" style="color:#7A7A7A;">Submitted {{ $existing->created_at->format('M d, Y') }}. We'll email you once reviewed.</p>
            <a href="{{ route('profile.show') }}" class="mt-6 inline-flex rounded-xl px-6 py-3 text-sm font-bold text-white" style="background:#002b4d;">Back to Profile</a>
        </div>

    @elseif (isset($existing) && $existing?->isApproved())
        <div class="card p-8 text-center">
            <span class="text-4xl">✅</span>
            <p class="mt-4 font-display text-xl font-semibold" style="color:#15803D;">You're already a Seller!</p>
            <a href="{{ route('seller.dashboard') }}" class="mt-6 inline-flex rounded-xl px-6 py-3 text-sm font-bold text-white" style="background:#002b4d;">Go to Seller Dashboard</a>
        </div>

    @else
        @if (isset($existing) && $existing?->isRejected())
            <div class="mb-6 rounded-2xl border p-5" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);">
                <p class="font-semibold" style="color:#DC2626;">Previous application rejected.</p>
                @if ($existing->rejection_reason)
                    <p class="mt-1 text-sm" style="color:#7A7A7A;">Reason: {{ $existing->rejection_reason }}</p>
                @endif
            </div>
        @endif

        @if ($errors->any())
            <div class="mb-6 rounded-xl border p-4 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="card p-8">
            <form action="{{ route('seller.apply.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
                @csrf

                {{-- Personal info --}}
                <div>
                    <h2 class="text-sm font-bold mb-3" style="color:#222222;">Personal Information</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Last Name *</label>
                            <input type="text" name="last_name" value="{{ old('last_name', auth()->user()->last_name ?? '') }}" class="input mt-1" required>
                        </div>
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">First Name *</label>
                            <input type="text" name="first_name" value="{{ old('first_name', auth()->user()->first_name ?? '') }}" class="input mt-1" required>
                        </div>
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Middle Initial</label>
                            <input type="text" name="middle_initial" maxlength="5" value="{{ old('middle_initial', auth()->user()->middle_initial ?? '') }}" class="input mt-1">
                        </div>
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Sex *</label>
                            <select name="sex" class="input mt-1" required>
                                <option value="">Select</option>
                                @foreach (['Male','Female','Other'] as $s)
                                    <option value="{{ $s }}" @selected(old('sex') === $s)>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Email *</label>
                            <input type="email" value="{{ auth()->user()->email }}" class="input mt-1" readonly style="background:#F9FAFB;">
                        </div>
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Contact No. *</label>
                            <input type="text" name="phone" value="{{ old('phone', auth()->user()->phone ?? '') }}" class="input mt-1" required>
                        </div>
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Birthday *</label>
                            <input type="date" id="birthday_apply" name="birthday" value="{{ old('birthday') }}" class="input mt-1" required>
                        </div>
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Age</label>
                            <input type="text" id="age_apply" class="input mt-1" readonly placeholder="Auto" style="background:#F9FAFB;">
                        </div>
                    </div>
                </div>

                {{-- Business info --}}
                <div>
                    <h2 class="text-sm font-bold mb-3" style="color:#222222;">Business Information</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Shop / Store Name *</label>
                            <input type="text" name="shop_name" value="{{ old('shop_name') }}" class="input mt-1" required placeholder="e.g. Maria's Fashion">
                        </div>
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Business Name</label>
                            <input type="text" name="business_name" value="{{ old('business_name') }}" class="input mt-1" placeholder="Registered business name">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Line of Business / Category *</label>
                            <select name="line_of_business" class="input mt-1" required>
                                <option value="">Select category</option>
                                @foreach (['Pet Supplies','Kids & Baby','Electronics & Gadgets',"Women's Apparel",'Sports & Outdoors','Home & Garden',"Men's Apparel",'Health & Beauty','Books & Media','Food & Gourmet','Furniture & Office','Jewelry & Watches','Others'] as $biz)
                                    <option value="{{ $biz }}" @selected(old('line_of_business') === $biz)>{{ $biz }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold" style="color:#6b90aa;">About your store *</label>
                            <textarea name="description" rows="3" class="input mt-1" required maxlength="1000"
                                      placeholder="What products do you sell? What makes your store unique?">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Address with PSGC --}}
                <div>
                    <h2 class="text-sm font-bold mb-1" style="color:#222222;">Address</h2>
                    <p class="text-xs mt-0.5 mb-3" style="color:#6b90aa;">Select your region, province, city/municipality, then barangay.</p>
                    <div class="grid gap-4 sm:grid-cols-2">

                        {{-- Region --}}
                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Region *</label>
                            <select id="sa_region" name="region" class="input mt-1" required
                                    onchange="saLoadProvincesByRegion(this.value)">
                                <option value="">— Select Region —</option>
                            </select>
                        </div>

                        {{-- Province --}}
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Province *</label>
                            <select id="sa_province" name="province" class="input mt-1" required disabled
                                    onchange="saLoadMunicipalities(this.options[this.selectedIndex].dataset.code, this.value)">
                                <option value="">— Select Province —</option>
                            </select>
                        </div>

                        {{-- Municipality / City --}}
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Municipality / City *</label>
                            <select id="sa_municipality" name="municipality" class="input mt-1" required disabled
                                    onchange="saLoadBarangays(this.options[this.selectedIndex].dataset.code)">
                                <option value="">— Select Province first —</option>
                            </select>
                        </div>

                        {{-- Barangay --}}
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Barangay *</label>
                            <select id="sa_barangay" name="barangay" class="input mt-1" required disabled>
                                <option value="">— Select Municipality first —</option>
                            </select>
                        </div>

                        {{-- ZIP Code --}}
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">ZIP Code</label>
                            <input type="text" name="zip_code" value="{{ old('zip_code') }}" class="input mt-1"
                                   placeholder="e.g. 1000">
                        </div>

                        {{-- House Number --}}
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">House / Unit Number *</label>
                            <input type="text" name="house_number" value="{{ old('house_number') }}" class="input mt-1" required
                                   placeholder="e.g. 12B">
                        </div>

                        {{-- Street --}}
                        <div class="sm:col-span-2">
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Street / Subdivision *</label>
                            <input type="text" name="street" value="{{ old('street', auth()->user()->address ?? '') }}" class="input mt-1" required
                                   placeholder="e.g. Rizal Street, Sunshine Village">
                        </div>

                    </div>
                </div>

                {{-- Documents --}}
                <div>
                    <h2 class="text-sm font-bold mb-3" style="color:#222222;">Documents</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Valid Government ID *</label>
                            <input type="file" name="government_id" accept="image/*,.pdf" class="input mt-1 py-2" required>
                            <p class="text-[11px] mt-1" style="color:#B0B0B0;">JPG, PNG or PDF · max 5 MB</p>
                        </div>
                        <div>
                            <label class="text-xs font-semibold" style="color:#6b90aa;">Business Permit (optional)</label>
                            <input type="file" name="business_permit" accept="image/*,.pdf" class="input mt-1 py-2">
                            <p class="text-[11px] mt-1" style="color:#B0B0B0;">JPG, PNG or PDF · max 5 MB</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border p-4 text-sm" style="background:#e8f0f6;border-color:#cfdce8;">
                    <p style="color:#7A7A7A;">
                        📧 After submitting, please wait for administrator approval. You will be notified at
                        <strong>{{ auth()->user()->email }}</strong>.
                    </p>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="flex-1 rounded-xl py-3 text-sm font-bold text-white transition hover:opacity-90" style="background:#002b4d;">Submit Application</button>
                    <a href="{{ route('profile.show') }}" class="rounded-xl border px-6 py-3 text-sm font-semibold" style="border-color:#cfdce8;color:#7A7A7A;">Cancel</a>
                </div>
            </form>
        </div>
    @endif

</div>

<script>
// Age auto-calc
document.getElementById('birthday_apply')?.addEventListener('change', function () {
    const dob = new Date(this.value);
    if (isNaN(dob)) return;
    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const m = today.getMonth() - dob.getMonth();
    if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
    document.getElementById('age_apply').value = age >= 0 ? age : '';
});

// ── PSGC cascading address dropdowns (same as register page) ─────────
const PSGC = '/api/psgc';

function saSetLoading(id, msg) {
    const el = document.getElementById(id);
    el.innerHTML = `<option value="">${msg}</option>`;
    el.disabled = true;
}

function saPopulate(id, items, placeholder) {
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

function saReset(id, placeholder) {
    const el = document.getElementById(id);
    el.innerHTML = `<option value="">${placeholder}</option>`;
    el.disabled = true;
}

// Load regions on page load
window.addEventListener('DOMContentLoaded', async function () {
    saSetLoading('sa_region', 'Loading regions…');
    try {
        const res  = await fetch(`${PSGC}/regions`);
        const data = await res.json();
        saPopulate('sa_region', data, '— Select Region —');
    } catch (e) {
        document.getElementById('sa_region').innerHTML =
            '<option value="">⚠ Could not load regions. Refresh to retry.</option>';
    }
});

// Region → Province
async function saLoadProvincesByRegion(regionName) {
    saReset('sa_province',     '— Select Province —');
    saReset('sa_municipality', '— Select Province first —');
    saReset('sa_barangay',     '— Select Municipality first —');
    if (!regionName) return;

    const sel  = document.getElementById('sa_region');
    const code = sel.options[sel.selectedIndex].dataset.code;

    saSetLoading('sa_province', 'Loading provinces…');
    try {
        const res  = await fetch(`${PSGC}/regions/${code}/provinces`);
        const data = await res.json();
        if (data.length === 0) {
            // NCR — no provinces, load cities directly
            saPopulate('sa_province', [{ code: code, name: 'Metro Manila (NCR)' }], '— Select Province —');
            await saLoadMunicipalities(code, 'Metro Manila (NCR)');
        } else {
            saPopulate('sa_province', data, '— Select Province —');
        }
    } catch (e) {
        document.getElementById('sa_province').innerHTML =
            '<option value="">⚠ Could not load provinces</option>';
    }
}

// Province → Municipality
async function saLoadMunicipalities(code, label) {
    saReset('sa_municipality', '— Select Municipality / City —');
    saReset('sa_barangay',     '— Select Municipality first —');
    if (!code) return;

    saSetLoading('sa_municipality', 'Loading cities…');
    try {
        const res  = await fetch(`${PSGC}/provinces/${code}/municipalities`);
        const data = await res.json();
        saPopulate('sa_municipality', data, '— Select Municipality / City —');
    } catch (e) {
        document.getElementById('sa_municipality').innerHTML =
            '<option value="">⚠ Could not load municipalities</option>';
    }
}

// Municipality → Barangay
async function saLoadBarangays(code) {
    saReset('sa_barangay', '— Select Barangay —');
    if (!code) return;

    saSetLoading('sa_barangay', 'Loading barangays…');
    try {
        const res  = await fetch(`${PSGC}/municipalities/${code}/barangays`);
        const data = await res.json();
        saPopulate('sa_barangay', data, '— Select Barangay —');
    } catch (e) {
        document.getElementById('sa_barangay').innerHTML =
            '<option value="">⚠ Could not load barangays</option>';
    }
}
</script>
</x-layout>
