<x-auth-shell title="Create Account — ALVY" heading="Create your account" subtitle="Join ALVY in a few minutes — it's free." tab="signup" width="wide" :progress="true">
<x-slot:head>
    <style>
        .font-display { font-family: 'Nunito', ui-sans-serif, system-ui; }

        /* ── Selectable chips (role / sex / vehicle) ── */
        .role-chip, .gender-chip, .wheel-chip {
            cursor: pointer;
            background: #fff;
            border: 1.5px solid #EDE2DA;
            border-radius: .75rem;
            color: #5a4d45;
            transition: all .18s ease;
        }
        .role-chip:not(.active):hover,
        .gender-chip:not(.active):hover,
        .wheel-chip:not(.active):hover {
            border-color: #fa4e1c;
            background: #fff5f3;
            transform: translateY(-1px);
        }
        .role-chip.active, .gender-chip.active, .wheel-chip.active {
            background: #fa4e1c;
            border-color: #fa4e1c;
            color: #fff;
            box-shadow: 0 6px 16px -6px rgba(250, 78, 28, .55);
        }

        /* ── Inputs ── */
        .form-input {
            width: 100%;
            border-radius: .625rem;
            border: 1px solid #E6D9CF;
            background: #fff;
            padding: .7rem 1rem;
            font-size: .9rem;
            color: #222;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .form-input::placeholder { color: #b5a79d; }
        .form-input:focus {
            border-color: #fa4e1c;
            box-shadow: 0 0 0 3px rgba(250, 78, 28, .12);
        }
        .form-input.is-invalid { border-color: #DC2626; }
        .form-input.is-invalid:focus { box-shadow: 0 0 0 3px rgba(220, 38, 38, .12); }
        .field-error { margin-top: .3rem; font-size: .75rem; color: #DC2626; }
        .field-error:empty { display: none; }
        .form-input:disabled, .form-input[readonly] {
            background: #f7f1ed;
            cursor: not-allowed;
        }
        .fld-label {
            display: block;
            margin-bottom: .375rem;
            font-size: .75rem;
            font-weight: 600;
            color: #3a2f29;
        }

        /* ── Section cards ── */
        .form-card {
            background: #fff;
            border: 1px solid #F0E4DC;
            border-radius: 1rem;
            padding: 1.25rem;
        }
        .card-title {
            display: flex;
            align-items: center;
            gap: .625rem;
            margin-bottom: 1rem;
            font-family: 'Nunito', ui-sans-serif, system-ui;
            font-weight: 800;
            font-size: 1rem;
            color: #222;
        }
        .card-num {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.625rem;
            height: 1.625rem;
            border-radius: 999px;
            background: #fff1ee;
            color: #fa4e1c;
            font-size: .75rem;
            font-weight: 800;
        }
        .section-divider {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin: 1.25rem 0 .75rem;
        }
        .section-divider:first-child { margin-top: 0; }
        .section-bar {
            width: 4px;
            height: 16px;
            background: #fa4e1c;
            border-radius: 2px;
        }
        .hidden-section { display: none; }

        /* ── Uploads ── */
        .file-upload-box {
            display: flex;
            align-items: center;
            gap: .875rem;
            border: 1.5px dashed #E6D9CF;
            border-radius: .75rem;
            padding: .875rem 1rem;
            cursor: pointer;
            transition: all .2s;
            background: #FDF8F5;
        }
        .file-upload-box:hover {
            border-color: #fa4e1c;
            background: #fff5f3;
        }
        .upload-icon {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: .625rem;
            background: #fff1ee;
            color: #fa4e1c;
        }
        .file-preview {
            max-width: 100%;
            max-height: 160px;
            border-radius: .5rem;
            margin-top: .75rem;
        }

        /* ── Courier provider list ── */
        .provider-card {
            padding: .875rem 1rem;
            border-radius: .75rem;
            border: 1.5px solid #EDE2DA;
            background: #fff;
            cursor: pointer;
            transition: all .18s;
            margin-bottom: .5rem;
        }
        .provider-card:hover { background: #fff5f3; border-color: #fa4e1c; }
        .provider-card.active { background: #fff1ee; border-color: #fa4e1c; }

        /* Sections sit on a soft panel inside the white card */
        .form-card { background: #FDFAF8; border-color: #F3E8E0; }

        /* Terms + submit stay reachable at the bottom while scrolling */
        .sticky-submit { position: sticky; bottom: 0; z-index: 20; background: rgba(255,255,255,.96); backdrop-filter: blur(8px);
                         border-top: 1px solid #F0E4DC; border-radius: 0 0 1.75rem 1.75rem; box-shadow: 0 -12px 24px -18px rgba(58,47,41,.35); }
        .btn-primary-grad { background: linear-gradient(135deg,#fa4e1c,#ff7a45); box-shadow: 0 12px 24px -12px rgba(250,78,28,.8); }
    </style>
</x-slot:head>

        <form id="registrationForm" method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
            @csrf

            <div class="space-y-4 px-6 py-6 sm:px-10">

                {{-- Info (e.g. arrived from "Continue with Google" without an account) --}}
                @if (session('info'))
                    <div class="flex items-start gap-2 rounded-xl border p-3 text-sm" style="background:#FFF7F3;border-color:#FAD7C8;color:#9A3412;">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 11v5m0-8h.01"/></svg>
                        <p class="flex-1">{{ session('info') }}</p>
                    </div>
                @endif

                {{-- Error Display --}}
                @if ($errors->any())
                    <div class="flex items-start gap-2 rounded-xl border p-3" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);">
                        <svg class="mt-0.5 h-5 w-5 flex-shrink-0" style="color:#DC2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div class="flex-1">
                            @foreach ($errors->all() as $error)
                                <p class="text-sm" style="color:#DC2626;">{{ $error }}</p>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- 1. Account type --}}
                <section class="form-card">
                    <h2 class="card-title"><span class="card-num">1</span> I am a…</h2>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="role-chip active p-3 text-center" data-role="buyer">
                            <svg class="mx-auto mb-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <span class="text-xs font-semibold">Buyer</span>
                        </div>
                        <div class="role-chip p-3 text-center" data-role="seller">
                            <svg class="mx-auto mb-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                            <span class="text-xs font-semibold">Seller</span>
                        </div>
                        <div class="role-chip p-3 text-center" data-role="courier">
                            <svg class="mx-auto mb-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <span class="text-xs font-semibold">Courier</span>
                        </div>
                        <div class="role-chip p-3 text-center" data-role="logistics">
                            <svg class="mx-auto mb-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <span class="text-xs font-semibold">Logistics</span>
                        </div>
                    </div>
                    <input type="hidden" name="role" id="roleInput" value="buyer">
                </section>

                {{-- 2. Personal information --}}
                <section class="form-card">
                    <h2 class="card-title"><span class="card-num">2</span> Personal Information</h2>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-5">
                        <div class="sm:col-span-2">
                            <label class="fld-label" for="lastName">Last Name*</label>
                            <input type="text" name="last_name" id="lastName" class="form-input" placeholder="Dela Cruz" value="{{ old('last_name') }}" required>
                            <p class="field-error" data-error-for="lastName">@error('last_name'){{ $message }}@enderror</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="fld-label" for="firstName">First Name*</label>
                            <input type="text" name="first_name" id="firstName" class="form-input" placeholder="Juan" value="{{ old('first_name') }}" required>
                            <p class="field-error" data-error-for="firstName">@error('first_name'){{ $message }}@enderror</p>
                        </div>
                        <div>
                            <label class="fld-label" for="middleName">M.I.</label>
                            <input type="text" name="middle_initial" id="middleName" class="form-input" placeholder="Opt." value="{{ old('middle_initial') }}">
                            <p class="field-error" data-error-for="middleName">@error('middle_initial'){{ $message }}@enderror</p>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="fld-label" for="email">Email Address*</label>
                        <input type="email" name="email" id="email" class="form-input" placeholder="example@gmail.com" value="{{ old('email') }}" required>
                        <p class="field-error" data-error-for="email">@error('email'){{ $message }}@enderror</p>
                    </div>

                    <div class="mt-3">
                        <label class="fld-label">Sex*</label>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="gender-chip active px-3 py-2.5 text-center text-sm font-semibold" data-gender="male">Male</div>
                            <div class="gender-chip px-3 py-2.5 text-center text-sm font-semibold" data-gender="female">Female</div>
                            <div class="gender-chip px-3 py-2.5 text-center text-sm font-semibold" data-gender="other">Other</div>
                        </div>
                        <input type="hidden" name="sex" id="genderInput" value="male">
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-5">
                        <div class="sm:col-span-2">
                            <label class="fld-label" for="contactNo">Contact No.*</label>
                            <input type="tel" name="contact_no" id="contactNo" class="form-input" placeholder="09171234567"
                                   inputmode="numeric" value="{{ old('contact_no') }}" required>
                            <p class="field-error" data-error-for="contactNo">@error('contact_no'){{ $message }}@enderror</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="fld-label" for="birthday">Birthday*</label>
                            <input type="date" name="birthday" id="birthday" class="form-input"
                                   value="{{ old('birthday') }}" max="{{ now()->subDay()->toDateString() }}" required>
                        </div>
                        <div>
                            <label class="fld-label" for="age_display">Age</label>
                            <input type="text" id="age_display" class="form-input" readonly placeholder="Auto">
                        </div>
                    </div>
                </section>

                {{-- 3. Address (PSGC) --}}
                <section class="form-card">
                    <h2 class="card-title"><span class="card-num">3</span> Address</h2>
                    <div class="space-y-3">
                        <div>
                            <label class="fld-label" for="reg_region">Region*</label>
                            <select id="reg_region" name="region" class="form-input" required>
                                <option value="">— Select Region —</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="fld-label" for="reg_province">Province*</label>
                                <select id="reg_province" name="province" class="form-input" required disabled>
                                    <option value="">— Select Province —</option>
                                </select>
                                <input type="hidden" name="province_code" id="reg_province_code">
                            </div>
                            <div>
                                <label class="fld-label" for="reg_municipality">Municipality / City*</label>
                                <select id="reg_municipality" name="municipality" class="form-input" required disabled>
                                    <option value="">— Select Province first —</option>
                                </select>
                                <input type="hidden" name="municipality_code" id="reg_municipality_code">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div class="sm:col-span-2">
                                <label class="fld-label" for="reg_barangay">Barangay*</label>
                                <select id="reg_barangay" name="barangay" class="form-input" required disabled>
                                    <option value="">— Select Municipality first —</option>
                                </select>
                            </div>
                            <div>
                                <label class="fld-label">ZIP Code</label>
                                <input type="text" name="zip_code" class="form-input" placeholder="e.g. 4001" value="{{ old('zip_code') }}" maxlength="10">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <label class="fld-label">House / Unit No.*</label>
                                <input type="text" name="house_number" class="form-input" placeholder="e.g. 12B" value="{{ old('house_number') }}" required>
                            </div>
                            <div class="sm:col-span-2">
                                <label class="fld-label">Street / Subdivision*</label>
                                <input type="text" name="street" class="form-input" placeholder="e.g. Rizal Street" value="{{ old('street') }}" required>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- 4. Security & verification --}}
                <section class="form-card">
                    <h2 class="card-title"><span class="card-num">4</span> Security &amp; Verification</h2>

                    <div>
                        <label class="fld-label" for="password">Password*</label>
                        <div class="relative">
                            <input type="password" name="password" id="password" class="form-input pr-11" placeholder="At least 8 characters" required>
                            <button type="button" id="togglePassword" aria-label="Show password"
                                    class="absolute right-3 top-1/2 -translate-y-1/2" style="color:#8a7a70;">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                        </div>
                        <p class="field-error" data-error-for="password">@error('password'){{ $message }}@enderror</p>
                        <input type="hidden" name="password_confirmation" id="passwordConfirmation">
                    </div>

                    {{-- Upload ID (buyer) --}}
                    <div class="mt-4" id="buyerIdSection">
                        <label class="fld-label">Valid ID*</label>
                        <div class="file-upload-box" onclick="document.getElementById('buyerIdUpload').click()">
                            <span class="upload-icon">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold" style="color:#3a2f29;" id="buyerIdLabel">Click to upload a valid ID</p>
                                <p class="text-xs" style="color:#9a8b81;">JPG, PNG or PDF · max 5 MB</p>
                            </div>
                        </div>
                        <input type="file" id="buyerIdUpload" name="valid_id" accept="image/jpeg,image/png,.pdf" class="hidden">
                        <img id="buyerIdPreview" class="file-preview hidden">
                    </div>
                </section>

                {{-- SELLER FIELDS --}}
                <section id="sellerSection" class="hidden-section form-card">
                    <h2 class="card-title"><span class="card-num">5</span> Seller Documents</h2>

                    <div class="mb-3">
                        <label class="fld-label">Valid Government ID</label>
                        <div class="file-upload-box" onclick="document.getElementById('sellerIdUpload').click()">
                            <span class="upload-icon">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            </span>
                            <p class="text-sm font-semibold" style="color:#3a2f29;">Click to upload Valid ID</p>
                        </div>
                        <input type="file" id="sellerIdUpload" name="id_image" accept="image/*,.pdf" class="hidden">
                        <img id="sellerIdPreview" class="file-preview hidden">
                    </div>

                    <div class="mb-3">
                        <label class="fld-label">Business / DTI Permit</label>
                        <div class="file-upload-box" onclick="document.getElementById('sellerPermitUpload').click()">
                            <span class="upload-icon">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            </span>
                            <p class="text-sm font-semibold" style="color:#3a2f29;">Click to upload Business Permit</p>
                        </div>
                        <input type="file" id="sellerPermitUpload" name="business_permit" accept="image/*,.pdf" class="hidden">
                        <img id="sellerPermitPreview" class="file-preview hidden">
                    </div>

                    <div class="flex gap-2 rounded-lg border p-3 text-sm" style="background:#fff5f3;border-color:#fdd5c8;">
                        <svg class="mt-0.5 h-4 w-4 flex-shrink-0" style="color:#fa4e1c;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p style="color:#b4380f;">Both your valid ID and business permit are required for verification.</p>
                    </div>
                </section>

                {{-- COURIER FIELDS --}}
                <section id="courierSection" class="hidden-section form-card">
                    <h2 class="card-title"><span class="card-num">5</span> Courier Details</h2>

                    <div class="section-divider">
                        <div class="section-bar"></div>
                        <h3 class="text-sm font-bold" style="color:#3a2f29;">Vehicle Information</h3>
                    </div>

                    <div class="mb-3">
                        <label class="fld-label">Vehicle Type</label>
                        <div class="grid grid-cols-3 gap-2">
                            <div class="wheel-chip active p-3 text-center" data-wheel="motorcycle">
                                <svg class="mx-auto mb-1 h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <circle cx="5.5" cy="16.5" r="3.5"/><circle cx="18.5" cy="16.5" r="3.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M5.5 16.5 9 9h5l4.5 7.5M14 9l-1-3h3"/>
                                </svg>
                                <p class="text-xs font-semibold">2 Wheels<br/>(Motorcycle)</p>
                            </div>
                            <div class="wheel-chip p-3 text-center" data-wheel="tricycle">
                                <svg class="mx-auto mb-1 h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <circle cx="5" cy="17" r="3"/><circle cx="19" cy="17" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 17h4l3-8h7v8M12 9V6h4"/>
                                </svg>
                                <p class="text-xs font-semibold">3 Wheels<br/>(Tricycle)</p>
                            </div>
                            <div class="wheel-chip p-3 text-center" data-wheel="car">
                                <svg class="mx-auto mb-1 h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 17H3v-5l2-5h14l2 5v5h-2M5 12h14"/><circle cx="7.5" cy="17" r="2"/><circle cx="16.5" cy="17" r="2"/>
                                </svg>
                                <p class="text-xs font-semibold">4 Wheels<br/>(Car/Van)</p>
                            </div>
                        </div>
                        <input type="hidden" name="vehicle_type" id="vehicleTypeInput" value="motorcycle">
                    </div>

                    <div class="mb-3">
                        <label class="fld-label" for="plateNumber">Plate Number</label>
                        <input type="text" name="plate_number" id="plateNumber" class="form-input" placeholder="ABC 1234" style="text-transform:uppercase;">
                    </div>

                    <div class="mb-3">
                        <label class="fld-label">Driver's License</label>
                        <div class="file-upload-box" onclick="document.getElementById('driverLicenseUpload').click()">
                            <span class="upload-icon">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            </span>
                            <p class="text-sm font-semibold" style="color:#3a2f29;">Click to upload Driver's License</p>
                        </div>
                        <input type="file" id="driverLicenseUpload" name="or_cr_image" accept="image/*,.pdf" class="hidden">
                        <img id="driverLicensePreview" class="file-preview hidden">
                    </div>

                    <div class="mb-3">
                        <label class="fld-label">Vehicle Registration (OR/CR)</label>
                        <div class="file-upload-box" onclick="document.getElementById('vehicleRegUpload').click()">
                            <span class="upload-icon">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            </span>
                            <p class="text-sm font-semibold" style="color:#3a2f29;">Click to upload Vehicle Registration</p>
                        </div>
                        <input type="file" id="vehicleRegUpload" name="vehicle_reg_image" accept="image/*,.pdf" class="hidden">
                        <img id="vehicleRegPreview" class="file-preview hidden">
                    </div>

                    <div class="section-divider">
                        <div class="section-bar"></div>
                        <h3 class="text-sm font-bold" style="color:#3a2f29;">Logistics Provider</h3>
                    </div>

                    <label class="fld-label">Select your Sorting Center / Logistics Hub</label>
                    <div id="logisticsProvidersList">
                        <div class="py-4 text-center">
                            <div class="mx-auto h-8 w-8 animate-spin rounded-full border-b-2" style="border-color:#fa4e1c;"></div>
                            <p class="mt-2 text-sm" style="color:#8a7a70;">Loading providers...</p>
                        </div>
                    </div>
                    <input type="hidden" name="logistics_id" id="logisticsIdInput">
                </section>

                {{-- LOGISTICS FIELDS --}}
                <section id="logisticsSection" class="hidden-section form-card">
                    <h2 class="card-title"><span class="card-num">5</span> Sorting Center Information</h2>

                    <div class="mb-3">
                        <label class="fld-label" for="logisticsBusinessName">Business / Center Name</label>
                        <input type="text" name="business_name" id="logisticsBusinessName" class="form-input" placeholder="e.g. ALVY Hub Lipa">
                    </div>

                    <div class="section-divider">
                        <div class="section-bar"></div>
                        <h3 class="text-sm font-bold" style="color:#3a2f29;">Hub Address</h3>
                    </div>

                    <div class="mb-3 space-y-3">
                        <div>
                            <label class="fld-label" for="logisticsProvince">Province</label>
                            <select name="province" id="logisticsProvince" class="form-input">
                                <option value="">Select Province</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="fld-label" for="logisticsMunicipality">Municipality / City</label>
                                <select name="municipality" id="logisticsMunicipality" class="form-input" disabled>
                                    <option value="">Select province first</option>
                                </select>
                            </div>
                            <div>
                                <label class="fld-label" for="logisticsBarangay">Barangay</label>
                                <select name="barangay" id="logisticsBarangay" class="form-input" disabled>
                                    <option value="">Select municipality first</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <label class="fld-label" for="logisticsStreet">Street</label>
                                <input type="text" name="street" id="logisticsStreet" class="form-input" placeholder="Street">
                            </div>
                            <div>
                                <label class="fld-label" for="logisticsHouseNumber">Bldg / Unit No.</label>
                                <input type="text" name="house_number" id="logisticsHouseNumber" class="form-input" placeholder="Bldg / Unit No.">
                            </div>
                        </div>
                    </div>

                    <div class="section-divider">
                        <div class="section-bar"></div>
                        <h3 class="text-sm font-bold" style="color:#3a2f29;">Logistics Documents</h3>
                    </div>

                    <div class="mb-3">
                        <label class="fld-label">Business / DTI Permit</label>
                        <div class="file-upload-box" onclick="document.getElementById('logisticsPermitUpload').click()">
                            <span class="upload-icon">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            </span>
                            <p class="text-sm font-semibold" style="color:#3a2f29;">Click to upload DTI Permit</p>
                        </div>
                        <input type="file" id="logisticsPermitUpload" name="dti_permit" accept="image/*,.pdf" class="hidden">
                        <img id="logisticsPermitPreview" class="file-preview hidden">
                    </div>

                    <div class="flex gap-2 rounded-lg border p-3 text-sm" style="background:#fff5f3;border-color:#fdd5c8;">
                        <svg class="mt-0.5 h-4 w-4 flex-shrink-0" style="color:#fa4e1c;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p style="color:#b4380f;">Your permit and hub address will be reviewed by the admin before your account is activated.</p>
                    </div>
                </section>
            </div>

            {{-- Footer (sticky): terms + submit --}}
            <div class="sticky-submit px-6 py-4 sm:px-10">
                <label class="flex cursor-pointer items-start gap-3">
                    <input type="checkbox" id="agreeTerms" name="terms" value="1" class="mt-0.5 h-4 w-4 flex-shrink-0 rounded" style="accent-color:#fa4e1c;" @checked(old('terms')) required>
                    <span class="text-sm" style="color:#5a4d45;">
                        I agree to ALVY's
                        <a href="{{ route('policies.show', 'terms') }}" target="_blank" class="font-semibold underline" style="color:#fa4e1c;" onclick="event.stopPropagation()">Terms and Conditions</a>
                        and
                        <a href="{{ route('policies.show', 'privacy') }}" target="_blank" class="font-semibold underline" style="color:#fa4e1c;" onclick="event.stopPropagation()">Privacy Policy</a>.
                    </span>
                </label>
                @error('terms')
                    <p class="mt-2 text-xs" style="color:#DC2626;">{{ $message }}</p>
                @enderror

                <div class="mt-4 flex flex-col-reverse items-center gap-3 sm:flex-row sm:justify-between">
                    <p class="text-sm" style="color:#8a7a70;">
                        Already have an account?
                        <a href="{{ route('login') }}" class="font-bold" style="color:#222;"
                           onmouseover="this.style.color='#fa4e1c';" onmouseout="this.style.color='#222';">Log In</a>
                    </p>
                    <button type="submit" id="submitBtn"
                            class="btn-primary-grad flex w-full items-center justify-center gap-2 rounded-xl px-8 py-3 text-sm font-bold text-white transition sm:w-auto"
                            style="opacity:0.5;cursor:not-allowed;"
                            disabled>
                        Create Account
                    </button>
                </div>
            </div>
        </form>

<x-slot:scripts>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const roleChips = document.querySelectorAll('.role-chip');
    const genderChips = document.querySelectorAll('.gender-chip');
    const wheelChips = document.querySelectorAll('.wheel-chip');
    const roleInput = document.getElementById('roleInput');
    const genderInput = document.getElementById('genderInput');
    const vehicleTypeInput = document.getElementById('vehicleTypeInput');
    
    const sellerSection = document.getElementById('sellerSection');
    const courierSection = document.getElementById('courierSection');
    const logisticsSection = document.getElementById('logisticsSection');
    
    // Disable inputs in hidden role sections so they aren't submitted
    // (logistics fields share names like province/street with the main address)
    function syncSectionInputs() {
        [sellerSection, courierSection, logisticsSection].forEach(sec => {
            const hidden = sec.classList.contains('hidden-section');
            sec.querySelectorAll('input, select').forEach(el => {
                if (hidden) {
                    el.disabled = true;
                } else if (!['logisticsMunicipality', 'logisticsBarangay'].includes(el.id) || el.options.length > 1) {
                    el.disabled = false;
                }
            });
        });
    }
    syncSectionInputs();

    // Role Selection
    roleChips.forEach(chip => {
        chip.addEventListener('click', function() {
            // Sellers sign up on their own page (shop details, TIN, business documents).
            if (this.dataset.role === 'seller') {
                window.location.href = @json(route('seller.register'));
                return;
            }
            roleChips.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            const role = this.dataset.role;
            roleInput.value = role;
            
            // Hide all sections
            sellerSection.classList.add('hidden-section');
            courierSection.classList.add('hidden-section');
            logisticsSection.classList.add('hidden-section');
            
            // Show relevant section
            if (role === 'seller') {
                sellerSection.classList.remove('hidden-section');
            } else if (role === 'courier') {
                courierSection.classList.remove('hidden-section');
                loadLogisticsProviders();
            } else if (role === 'logistics') {
                logisticsSection.classList.remove('hidden-section');
                loadProvinces();
            }
            syncSectionInputs();
        });
    });
    
    // Gender Selection
    genderChips.forEach(chip => {
        chip.addEventListener('click', function() {
            genderChips.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            genderInput.value = this.dataset.gender;
        });
    });
    
    // Vehicle Type Selection
    wheelChips.forEach(chip => {
        chip.addEventListener('click', function() {
            wheelChips.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            vehicleTypeInput.value = this.dataset.wheel;
        });
    });
    
    // Password Toggle
    document.getElementById('togglePassword').addEventListener('click', function() {
        const passwordInput = document.getElementById('password');
        const type = passwordInput.type === 'password' ? 'text' : 'password';
        passwordInput.type = type;
    });
    
    // File Upload Previews
    setupFilePreview('sellerIdUpload', 'sellerIdPreview');
    setupFilePreview('sellerPermitUpload', 'sellerPermitPreview');
    setupFilePreview('driverLicenseUpload', 'driverLicensePreview');
    setupFilePreview('vehicleRegUpload', 'vehicleRegPreview');
    setupFilePreview('logisticsPermitUpload', 'logisticsPermitPreview');
    setupFilePreview('buyerIdUpload', 'buyerIdPreview', 'buyerIdLabel');

    // Age auto-calculation from birthday
    (function() {
        const bday = document.getElementById('birthday');
        const ageEl = document.getElementById('age_display');
        if (!bday || !ageEl) return;
        function calcAge() {
            const d = new Date(bday.value);
            if (isNaN(d)) { ageEl.value = ''; return; }
            const t = new Date();
            let a = t.getFullYear() - d.getFullYear();
            const m = t.getMonth() - d.getMonth();
            if (m < 0 || (m === 0 && t.getDate() < d.getDate())) a--;
            ageEl.value = a >= 0 ? a : '';
        }
        bday.addEventListener('change', calcAge);
        calcAge();
    })();

    // Show/hide buyer ID upload when role changes
    function syncBuyerIdVisibility(role) {
        const sec = document.getElementById('buyerIdSection');
        if (!sec) return;
        sec.style.display = (role === 'buyer') ? '' : 'none';
    }
    syncBuyerIdVisibility(roleInput.value);
    roleChips.forEach(chip => {
        chip.addEventListener('click', function() {
            syncBuyerIdVisibility(this.dataset.role);
        });
    });
    
    // Terms checkbox toggles the submit button
    const agreeCheckbox = document.getElementById('agreeTerms');
    const submitBtn = document.getElementById('submitBtn');

    function syncSubmitBtn() {
        if (agreeCheckbox.checked) {
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            submitBtn.style.cursor = 'pointer';
        } else {
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.5';
            submitBtn.style.cursor = 'not-allowed';
        }
    }

    if (agreeCheckbox) {
        agreeCheckbox.addEventListener('change', syncSubmitBtn);
        syncSubmitBtn(); // run on load (handles old() re-check after validation error)
    }

    // Form submission — show spinner, re-enable on slow/no response
    document.getElementById('registrationForm').addEventListener('submit', function(e) {
        if (!agreeCheckbox.checked) { e.preventDefault(); return; }
        document.getElementById('passwordConfirmation').value = document.getElementById('password').value;
        submitBtn.innerHTML = '<div class="animate-spin rounded-full h-5 w-5 border-b-2 border-white mx-auto"></div>';
        submitBtn.disabled = true;
        submitBtn.style.opacity = '0.7';
        // Re-enable after 8 s so the user can retry if something goes wrong
        setTimeout(function() {
            submitBtn.innerHTML = 'Create Account';
            submitBtn.disabled = false;
            submitBtn.style.opacity = '1';
            submitBtn.style.cursor = 'pointer';
        }, 8000);
    });
});

function setupFilePreview(inputId, previewId, labelId) {
    const input   = document.getElementById(inputId);
    const preview = document.getElementById(previewId);
    const label   = labelId ? document.getElementById(labelId) : null;
    if (!input) return;
    input.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        if (label) label.textContent = file.name;
        if (preview && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    });
}

// Load Logistics Providers for Courier
function loadLogisticsProviders() {
    fetch('/api/logistics-providers')
        .then(res => res.json())
        .then(data => {
            const container = document.getElementById('logisticsProvidersList');
            if (data.length === 0) {
                container.innerHTML = '<p class="text-center text-sm text-gray-500 py-4">No logistics providers available yet.</p>';
                return;
            }
            
            container.innerHTML = data.map(provider => `
                <div class="provider-card" onclick="selectProvider(${provider.id}, this)">
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-gray-100 rounded-lg">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <p class="font-semibold text-sm">${provider.name || ''}</p>
                            ${provider.hub_address ? `<p class="text-xs text-gray-500">${provider.hub_address}</p>` : ''}
                        </div>
                    </div>
                </div>
            `).join('');
        })
        .catch(err => {
            document.getElementById('logisticsProvidersList').innerHTML = '<p class="text-center text-sm text-red-500 py-4">Failed to load providers.</p>';
        });
}

function selectProvider(id, element) {
    document.querySelectorAll('.provider-card').forEach(card => card.classList.remove('active'));
    element.classList.add('active');
    document.getElementById('logisticsIdInput').value = id;
}

// Load Provinces for Logistics
function loadProvinces() {
    fetch('https://psgc.gitlab.io/api/provinces/')
        .then(res => res.json())
        .then(data => {
            const select = document.getElementById('logisticsProvince');
            select.innerHTML = '<option value="">Select Province</option>' +
                data.sort((a, b) => a.name.localeCompare(b.name))
                    .map(p => `<option value="${p.name}" data-code="${p.code}">${p.name}</option>`)
                    .join('');
        });
}

// Load Municipalities when province changes
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('logisticsProvince')?.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const code = selectedOption.dataset.code;
        
        if (!code) return;
        
        const munSelect = document.getElementById('logisticsMunicipality');
        munSelect.disabled = true;
        munSelect.innerHTML = '<option value="">Loading...</option>';
        
        Promise.all([
            fetch(`https://psgc.gitlab.io/api/provinces/${code}/municipalities/`).then(r => r.json()),
            fetch(`https://psgc.gitlab.io/api/provinces/${code}/cities/`).then(r => r.json())
        ]).then(([muns, cities]) => {
            const combined = [...muns, ...cities].sort((a, b) => a.name.localeCompare(b.name));
            munSelect.innerHTML = '<option value="">Select Municipality / City</option>' +
                combined.map(m => `<option value="${m.name}" data-code="${m.code}">${m.name}</option>`).join('');
            munSelect.disabled = false;
        });
    });
    
    document.getElementById('logisticsMunicipality')?.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const code = selectedOption.dataset.code;
        
        if (!code) return;
        
        const brgySelect = document.getElementById('logisticsBarangay');
        brgySelect.disabled = true;
        brgySelect.innerHTML = '<option value="">Loading...</option>';
        
        fetch(`https://psgc.gitlab.io/api/municipalities/${code}/barangays/`)
            .then(res => res.json())
            .then(data => {
                brgySelect.innerHTML = '<option value="">Select Barangay</option>' +
                    data.sort((a, b) => a.name.localeCompare(b.name))
                        .map(b => `<option value="${b.name}">${b.name}</option>`)
                        .join('');
                brgySelect.disabled = false;
            });
    });
});
</script>
@if (file_exists(public_path('js/psgc-address.js')))
<script src="{{ asset('js/psgc-address.js') }}"></script>
@endif
<script src="{{ asset('js/register-address-fallback.js') }}"></script>
<script src="{{ asset('js/register-validation.js') }}"></script>
<script>
// Attach PSGC cascade for the registration form address
document.addEventListener('DOMContentLoaded', function() {
    if (!window.PsgcAddress && window.RegisterAddressFallback) {
        RegisterAddressFallback.attach({
            region:       '#reg_region',
            province:     '#reg_province',
            city:         '#reg_municipality',
            barangay:     '#reg_barangay',
            provinceCode: '#reg_province_code',
            cityCode:     '#reg_municipality_code',
            old: {
                region:       @json(old('region')),
                province:     @json(old('province')),
                municipality: @json(old('municipality')),
                barangay:     @json(old('barangay')),
            },
        });
    }
    if (window.PsgcAddress) {
        PsgcAddress.attach({
            region:   '#reg_region',
            province: '#reg_province',
            city:     '#reg_municipality',
            barangay: '#reg_barangay',
            old: {
                region:       @json(old('region')),
                province:     @json(old('province_code') ?: old('province')),
                municipality: @json(old('municipality_code') ?: old('municipality')),
                barangay:     @json(old('barangay')),
            },
        });
    }
});
</script>
</x-slot:scripts>
</x-auth-shell>
