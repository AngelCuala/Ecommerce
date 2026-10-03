<x-layout title="Courier Registration — ALVY">
<div class="mx-auto max-w-2xl px-4 py-12 sm:px-6 lg:px-8">

    {{-- Page hero --}}
    <div class="mb-8 text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl text-white"
             style="background:linear-gradient(135deg,#002b4d 0%,#003d6b 100%);">
            <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13h11V6H3v7zm11 0h4l3 3v-3M5.5 17.5a1.5 1.5 0 100 .01M17.5 17.5a1.5 1.5 0 100 .01"/></svg>
        </div>
        <h1 class="mt-4 font-display text-3xl font-bold" style="color:#222;">Courier / Rider Registration</h1>
        <p class="mt-2 text-sm" style="color:#6B7280;">
            Join the ALVY delivery fleet. Applications are reviewed by the Logistics team within 1–3 business days.
        </p>
    </div>

    {{-- ── Already applied states ───────────────────────────────── --}}
    @if (isset($existing) && $existing?->isPending())
        <div class="rounded-2xl p-8 text-center" style="background:#fff;border:1px solid #cfdce8;">
            <span style="display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:999px;background:#fffbeb;color:#b45309;">
                @include('courier.partials.icon', ['name' => 'clock', 'size' => 28])
            </span>
            <p class="mt-4 font-display text-xl font-bold" style="color:#222;">Application Under Review</p>
            <p class="mt-2 text-sm" style="color:#6B7280;">
                Submitted {{ $existing->created_at->format('M d, Y') }}.
                We'll email you at <strong>{{ auth()->user()->email }}</strong> once it's reviewed.
            </p>
            <a href="{{ route('courier.status') }}"
               class="mt-6 inline-flex rounded-xl px-6 py-3 text-sm font-bold text-white"
               style="background:#002b4d;">View Status</a>
        </div>
        <div class="rounded-2xl p-8 text-center" style="background:#fff;border:1px solid #cfdce8;">
            <span style="display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:999px;background:#ecfdf5;color:#059669;">
                @include('courier.partials.icon', ['name' => 'check', 'size' => 28])
            </span>
            <p class="mt-4 font-display text-xl font-bold" style="color:#059669;">You're already an approved courier!</p>
            <a href="{{ route('courier.dashboard') }}"
               class="mt-6 inline-flex rounded-xl px-6 py-3 text-sm font-bold text-white"
               style="background:#002b4d;">Go to Dashboard</a>
        </div>

    @else

        {{-- Rejected banner --}}
        @if (isset($existing) && $existing?->isRejected())
            <div class="mb-6 rounded-2xl border p-5" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);">
                <p class="font-semibold" style="color:#DC2626;">Previous application rejected.</p>
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
                  enctype="multipart/form-data" class="space-y-8" id="courier-reg-form">
                @csrf

                {{-- ── 1. Personal Information ──────────────────────── --}}
                <div>
                    <h2 class="mb-4 font-display text-base font-bold" style="color:#222;">1 · Personal Information</h2>
                    <div class="grid gap-4 sm:grid-cols-2">

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Last Name *</label>
                            <input type="text" name="last_name" value="{{ old('last_name', auth()->user()->last_name ?? '') }}"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none" style="border-color:#cfdce8;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';" required>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">First Name *</label>
                            <input type="text" name="first_name" value="{{ old('first_name', auth()->user()->first_name ?? '') }}"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none" style="border-color:#cfdce8;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';" required>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Middle Initial</label>
                            <input type="text" name="middle_initial" maxlength="5" value="{{ old('middle_initial', auth()->user()->middle_initial ?? '') }}"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none" style="border-color:#cfdce8;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';" placeholder="e.g. A">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Sex *</label>
                            <select name="sex" class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none" style="border-color:#cfdce8;"
                                    onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';" required>
                                <option value="">— Select —</option>
                                @foreach(['Male','Female'] as $s)
                                    <option value="{{ $s }}" @selected(old('sex') === $s)>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Email *</label>
                            <input type="email" value="{{ auth()->user()->email }}"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm" style="border-color:#cfdce8;background:#F9FAFB;color:#6b90aa;" readonly>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Contact No. *</label>
                            <input type="text" name="contact_no" value="{{ old('contact_no', auth()->user()->contact_no ?? auth()->user()->phone ?? '') }}"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none" style="border-color:#cfdce8;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                                   placeholder="+63 912 345 6789" required>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Birthday *</label>
                            <input type="date" id="sc_birthday" name="birthday" value="{{ old('birthday') }}" max="{{ now()->toDateString() }}"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none" style="border-color:#cfdce8;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';" required>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Age *</label>
                            <input type="number" id="sc_age" name="age_display"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm" style="border-color:#cfdce8;background:#F9FAFB;color:#6b90aa;"
                                   placeholder="Auto-calculated" readonly>
                        </div>

                    </div>
                </div>

                {{-- ── 2. Address ────────────────────────────────────── --}}
                <div>
                    <h2 class="mb-4 font-display text-base font-bold" style="color:#222;">2 · Address</h2>
                    <div class="grid gap-4 sm:grid-cols-2">

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Province *</label>
                            <select id="sc_province" name="province"
                                    class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none" style="border-color:#cfdce8;"
                                    onchange="scLoadMunicipalities(this.options[this.selectedIndex].dataset.code)" required disabled>
                                <option value="">Loading provinces…</option>
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Municipality / City *</label>
                            <select id="sc_municipality" name="municipality"
                                    class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none" style="border-color:#cfdce8;"
                                    onchange="scLoadBarangays(this.options[this.selectedIndex].dataset.code)" required disabled>
                                <option value="">— Select Province first —</option>
                            </select>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Barangay *</label>
                            <select id="sc_barangay" name="barangay"
                                    class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none" style="border-color:#cfdce8;" required disabled>
                                <option value="">— Select Municipality first —</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Street</label>
                            <input type="text" name="street" value="{{ old('street') }}"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none" style="border-color:#cfdce8;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';" placeholder="Street name">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">House / Unit No.</label>
                            <input type="text" name="house_number" value="{{ old('house_number') }}"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none" style="border-color:#cfdce8;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';" placeholder="House / unit number">
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Building / Unit / Other Address Details</label>
                            <input type="text" name="address_details" value="{{ old('address_details') }}"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none" style="border-color:#cfdce8;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                                   placeholder="Building name, landmark, etc.">
                        </div>

                    </div>
                </div>

                {{-- ── 3. Vehicle Information ────────────────────────── --}}
                <div>
                    <h2 class="mb-4 font-display text-base font-bold" style="color:#222;">3 · Vehicle Information</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Vehicle Type *</label>
                            <select name="vehicle_type" class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none" style="border-color:#cfdce8;"
                                    onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';" required>
                                <option value="">— Select vehicle —</option>
                                @foreach(['Motorcycle','Bicycle','Tricycle','Car','Van','Other'] as $v)
                                    <option value="{{ $v }}" @selected(old('vehicle_type') === $v)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:#6B7280;">Plate Number *</label>
                            <input type="text" name="plate_number" value="{{ old('plate_number') }}"
                                   class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none" style="border-color:#cfdce8;"
                                   onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                                   placeholder="e.g. ABC 1234" required>
                        </div>
                    </div>
                </div>

                {{-- ── 4. Required Documents ─────────────────────────── --}}
                <div>
                    <h2 class="mb-4 font-display text-base font-bold" style="color:#222;">4 · Required Documents</h2>
                    <p class="mb-3 text-[11px]" style="color:#6b90aa;">Allowed: JPG, JPEG, PNG or PDF · max 5 MB each.</p>
                    <div class="grid gap-4 sm:grid-cols-2">

                        <div class="rounded-xl border p-4" style="border-color:#cfdce8;" data-upload>
                            <label class="block text-xs font-semibold mb-2" style="color:#6B7280;">OR / CR *</label>
                            <input type="file" name="or_cr" accept=".jpg,.jpeg,.png,.pdf" class="hidden" required
                                   data-file-input onchange="courierFileChosen(this)">
                            <button type="button" onclick="this.closest('[data-upload]').querySelector('[data-file-input]').click()"
                                    class="w-full rounded-lg border-2 border-dashed py-3 text-xs font-semibold transition"
                                    style="border-color:#cfdce8;color:#6b90aa;"
                                    onmouseover="this.style.borderColor='#fa4e1c';this.style.color='#fa4e1c';"
                                    onmouseout="this.style.borderColor='#cfdce8';this.style.color='#6b90aa';">
                                Choose file
                            </button>
                            <div data-file-meta class="mt-2 hidden items-center justify-between gap-2 rounded-lg px-3 py-2 text-xs" style="background:#F9FAFB;">
                                <span data-file-name class="truncate" style="color:#1a4d6e;"></span>
                                <button type="button" onclick="courierFileRemove(this)" class="shrink-0 font-bold" style="color:#DC2626;">Remove</button>
                            </div>
                            <img data-file-preview class="mt-3 hidden h-24 w-full rounded-lg object-cover" alt="OR/CR preview">
                        </div>

                        <div class="rounded-xl border p-4" style="border-color:#cfdce8;" data-upload>
                            <label class="block text-xs font-semibold mb-2" style="color:#6B7280;">Valid ID / Driver's License *</label>
                            <input type="file" name="id_upload" accept=".jpg,.jpeg,.png,.pdf" class="hidden" required
                                   data-file-input onchange="courierFileChosen(this)">
                            <button type="button" onclick="this.closest('[data-upload]').querySelector('[data-file-input]').click()"
                                    class="w-full rounded-lg border-2 border-dashed py-3 text-xs font-semibold transition"
                                    style="border-color:#cfdce8;color:#6b90aa;"
                                    onmouseover="this.style.borderColor='#fa4e1c';this.style.color='#fa4e1c';"
                                    onmouseout="this.style.borderColor='#cfdce8';this.style.color='#6b90aa';">
                                Choose file
                            </button>
                            <div data-file-meta class="mt-2 hidden items-center justify-between gap-2 rounded-lg px-3 py-2 text-xs" style="background:#F9FAFB;">
                                <span data-file-name class="truncate" style="color:#1a4d6e;"></span>
                                <button type="button" onclick="courierFileRemove(this)" class="shrink-0 font-bold" style="color:#DC2626;">Remove</button>
                            </div>
                            <img data-file-preview class="mt-3 hidden h-24 w-full rounded-lg object-cover" alt="ID preview">
                        </div>

                    </div>
                </div>

                {{-- Notice --}}
                <div class="rounded-xl border p-4 text-sm" style="background:#FFF8F3;border-color:#cfdce8;">
                    <p style="color:#1a4d6e;">
                        After submitting, your account will be marked <strong>Pending Approval</strong>. You won't have access to
                        pickup/delivery functions until the Logistics team reviews your application. You'll be notified by email at
                        <strong>{{ auth()->user()->email }}</strong>.
                    </p>
                </div>

                {{-- Actions --}}
                <div class="flex gap-3 pt-2">
                    <button type="submit" class="flex-1 rounded-xl py-3 text-sm font-bold text-white transition hover:opacity-90" style="background:#002b4d;">
                        Submit Registration
                    </button>
                    <a href="{{ route('profile.show') }}"
                       class="rounded-xl border px-6 py-3 text-sm font-semibold transition" style="border-color:#cfdce8;color:#6B7280;"
                       onmouseover="this.style.background='#F9FAFB';" onmouseout="this.style.background='';">
                        Cancel
                    </a>
                </div>

            </form>
        </div>
    @endif

</div>

<script>
    window.COURIER_OLD = {
        province:     @json(old('province')),
        municipality: @json(old('municipality')),
        barangay:     @json(old('barangay')),
    };
</script>
<script src="{{ asset('js/courier-register.js') }}"></script>
</x-layout>
