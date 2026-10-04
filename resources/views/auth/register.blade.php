<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Create Account — ALVY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family:'Inter',ui-sans-serif,system-ui; }
        .font-display { font-family:'Nunito',ui-sans-serif,system-ui; }
        .input { width:100%;border-radius:.5rem;border:1px solid #E6D9CF;background:#fff;padding:.6rem .9rem;font-size:.875rem;color:#222;outline:none;transition:border-color .15s,box-shadow .15s; }
        .input:focus { border-color:#fa4e1c;box-shadow:0 0 0 3px rgba(250,78,28,.12); }
        .btn-gold { display:inline-flex;align-items:center;justify-content:center;gap:.5rem;border-radius:.5rem;padding:.75rem 1.5rem;font-weight:700;font-size:.875rem;transition:background .18s; }
    </style>
</head>
<body style="background:#fbeee8;">

<div class="min-h-screen flex items-center justify-center p-4 sm:p-8">
    <div class="grid w-full max-w-5xl overflow-hidden rounded-2xl shadow-2xl md:grid-cols-2" style="background:#fff;">

        {{-- ══════════ LEFT: image (sticky) ══════════ --}}
        <div class="relative hidden md:block">
            <img src="{{ asset('images/photo1.png') }}" alt="ALVY"
                 class="sticky top-0 h-screen max-h-full w-full object-cover" style="min-height:100%;">
            <div class="absolute inset-0" style="background:linear-gradient(135deg,rgba(250,78,28,.15),rgba(0,43,77,.25));"></div>
        </div>

        {{-- ══════════ RIGHT: form ══════════ --}}
        <div class="flex flex-col justify-center px-8 py-10 sm:px-12" style="background:#FDF8F5;">
            <div class="mx-auto w-full max-w-md">

                <div class="text-center">
                    <a href="{{ route('home') }}" class="mx-auto mb-4 inline-flex h-12 w-12 items-center justify-center overflow-hidden rounded-xl">
                        <img src="{{ asset('images/logo.png') }}" alt="ALVY" class="h-full w-full object-cover" style="transform:scale(1.4);">
                    </a>
                    <h1 class="font-display text-3xl font-extrabold" style="color:#222;">Create Account</h1>
                    <p class="mt-1 text-sm" style="color:#8a7a70;">Join ALVY for faster checkout and order history.</p>
                </div>

        @if ($errors->any())
            <div class="mt-6 mb-1 rounded-xl border p-4 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('register') }}" method="POST" enctype="multipart/form-data" class="mt-6 space-y-6">
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
                        <div class="relative mt-1">
                            <input type="password" name="password" data-password class="input pr-11" required style="border-color:#FFDCC2;">
                            <button type="button" data-toggle-password aria-label="Show password"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-lg leading-none" style="color:#6b90aa;">👁️</button>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Confirm Password *</label>
                        <div class="relative mt-1">
                            <input type="password" name="password_confirmation" data-password class="input pr-11" required style="border-color:#FFDCC2;">
                            <button type="button" data-toggle-password aria-label="Show password"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-lg leading-none" style="color:#6b90aa;">👁️</button>
                        </div>
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
                        <select id="region_select" name="region" class="input mt-1" required style="border-color:#FFDCC2;">
                            <option value="">— Select Region —</option>
                        </select>
                        <p id="loading-region" class="mt-1 text-[11px] hidden" style="color:#fa4e1c;">Loading regions…</p>
                    </div>

                    {{-- Province --}}
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Province *</label>
                        <select id="province_select" name="province" class="input mt-1" required style="border-color:#FFDCC2;"
                                disabled>
                            <option value="">— Select Province —</option>
                        </select>
                        <input type="hidden" name="province_code" id="province_code">
                    </div>

                    {{-- Municipality / City --}}
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Municipality / City *</label>
                        <select id="municipality_select" name="municipality" class="input mt-1" required
                                style="border-color:#FFDCC2;" disabled>
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

            <button type="submit" class="btn-gold w-full" style="background:#fa4e1c;color:#FFFFFF;"
                    onmouseover="this.style.background='#E14F00';" onmouseout="this.style.background='#fa4e1c';">Create Account</button>
        </form>

        <p class="mt-6 text-center text-sm" style="color:#8a7a70;">
            Already have an account?
            <a href="{{ route('login') }}" class="font-bold" style="color:#222;"
               onmouseover="this.style.color='#fa4e1c';" onmouseout="this.style.color='#222';">Sign in</a>
        </p>

            </div>{{-- /form column inner --}}
        </div>{{-- /right column --}}
    </div>{{-- /split card --}}
</div>{{-- /page wrapper --}}

<script>
    window.REGISTER_OLD = {
        region:       @json(old('region')),
        province:     @json(old('province_code') ?: old('province')),
        municipality: @json(old('municipality_code') ?: old('municipality')),
        barangay:     @json(old('barangay')),
    };
</script>
<script src="{{ asset('js/psgc-address.js') }}"></script>
<script src="{{ asset('js/register.js') }}"></script>

@include('partials.password-toggle')
</body>
</html>