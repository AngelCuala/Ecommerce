<x-auth-shell title="Create Account — ALVY" heading="Create your account" subtitle="Join ALVY in a few minutes — it's free." tab="signup" width="wide">
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

        /* ── Step-by-step wizard ── */
        [hidden] { display: none !important; } /* beat Tailwind's .flex on toggled buttons */
        .file-upload-box.is-invalid { border-color: #DC2626; background: #FEF2F2; }
        .wiz-step:not(.is-current) { display: none !important; }
        .wiz-step.is-current { animation: wizIn .22s ease; }
        @keyframes wizIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
        .wiz-bar { height: 6px; border-radius: 999px; background: #F3E8E0; overflow: hidden; }
        .wiz-bar-fill { height: 100%; width: 0; border-radius: 999px; background: linear-gradient(90deg,#fa4e1c,#ff8a5b); transition: width .3s ease; }
        .wiz-dots { display: flex; gap: .25rem; }
        .wiz-dots li { flex: 1; display: flex; flex-direction: column; align-items: center; gap: .25rem; font-size: .68rem; font-weight: 600; color: #b5a79d; text-align: center; }
        .wiz-dots .dot { display: inline-flex; align-items: center; justify-content: center; width: 1.6rem; height: 1.6rem; border-radius: 999px;
                         background: #F7F1ED; color: #8a7a70; font-size: .7rem; font-weight: 800; transition: all .2s; }
        .wiz-dots li.is-done .dot { background: #fff1ee; color: #fa4e1c; }
        .wiz-dots li.is-current { color: #222; }
        .wiz-dots li.is-current .dot { background: #fa4e1c; color: #fff; box-shadow: 0 6px 14px -6px rgba(250,78,28,.7); }
        @media (max-width: 480px) { .wiz-dots .lbl { display: none; } }
        .btn-ghost { border: 1.5px solid #E6D9CF; color: #5a4d45; background: #fff; }
        .btn-ghost:hover { border-color: #fa4e1c; color: #fa4e1c; }
    </style>
</x-slot:head>

        <form id="registrationForm" method="POST" action="{{ route('register') }}" enctype="multipart/form-data" novalidate>
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

                {{-- Progress: filled in by register-wizard.js from the steps of the chosen role --}}
                <div class="wiz-progress" aria-live="polite">
                    <div class="flex items-center justify-between text-xs font-semibold" style="color:#8a7a70;">
                        <span id="wizStepText">Step 1</span>
                        <span id="wizStepName"></span>
                    </div>
                    <div class="wiz-bar mt-2"><div class="wiz-bar-fill" id="wizBarFill"></div></div>
                    <ol class="wiz-dots mt-3" id="wizDots"></ol>
                </div>

                {{-- 1. Account type --}}
                <section class="form-card wiz-step" data-step="type" data-title="Account type">
                    <h2 class="card-title"><span class="card-num">1</span> What kind of account do you want?</h2>
                    <p class="-mt-2 mb-3 text-xs" style="color:#8a7a70;">Pick one to continue — the next steps depend on your choice.</p>
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <div class="role-chip p-3 text-center" data-role="buyer">
                            <svg class="mx-auto mb-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            <span class="block text-xs font-semibold">Buyer</span>
                            <span class="block text-[10px] opacity-75">Shop and order</span>
                        </div>
                        <div class="role-chip p-3 text-center" data-role="seller">
                            <svg class="mx-auto mb-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                            </svg>
                            <span class="block text-xs font-semibold">Seller</span>
                            <span class="block text-[10px] opacity-75">Open a shop</span>
                        </div>
                        <div class="role-chip p-3 text-center" data-role="courier">
                            <svg class="mx-auto mb-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <span class="block text-xs font-semibold">Courier</span>
                            <span class="block text-[10px] opacity-75">Deliver parcels</span>
                        </div>
                        <div class="role-chip p-3 text-center" data-role="logistics">
                            <svg class="mx-auto mb-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            <span class="block text-xs font-semibold">Logistics</span>
                            <span class="block text-[10px] opacity-75">Run a sorting hub</span>
                        </div>
                    </div>
                    <input type="hidden" name="role" id="roleInput" value="">
                    <p class="field-error" data-error-for="roleInput"></p>
                </section>

                {{-- 2. Personal information --}}
                <section class="form-card wiz-step" data-step="personal" data-title="Personal">
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
                        @if ($google = \App\Support\GoogleSignup::pending(request()))
                            {{-- From "Continue with Google": the email is fixed and already verified. --}}
                            <input type="email" name="email" id="email" class="form-input" value="{{ $google['email'] }}" readonly required>
                            <p class="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs" style="color:#15803d;">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" aria-hidden="true"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>
                                <span class="font-semibold">Verified with Google</span> — no email code needed.
                                <a href="{{ route('register', ['google' => 'forget']) }}" class="font-semibold underline" style="color:#8a7a70;">Use a different email</a>
                            </p>
                        @else
                            <input type="email" name="email" id="email" class="form-input" placeholder="example@gmail.com" value="{{ old('email') }}" required>
                        @endif
                        <p class="field-error" data-error-for="email">@error('email'){{ $message }}@enderror</p>
                    </div>

                    <div class="mt-3">
                        <label class="fld-label">Sex*</label>
                        {{-- users.sex is enum('Male','Female') --}}
                        <div class="grid grid-cols-2 gap-2">
                            <div class="gender-chip active px-3 py-2.5 text-center text-sm font-semibold" data-gender="male">Male</div>
                            <div class="gender-chip px-3 py-2.5 text-center text-sm font-semibold" data-gender="female">Female</div>
                        </div>
                        <input type="hidden" name="sex" id="genderInput" value="male">
                    </div>

                    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-5">
                        <div class="sm:col-span-2">
                            <label class="fld-label" for="contactNo">Contact No.*</label>
                            <input type="tel" name="contact_no" id="contactNo" class="form-input" placeholder="09171234567"
                                   inputmode="numeric" value="{{ old('contact_no') }}" required>
                            <p class="field-error" data-error-for="contactNo">@error('contact_no'){{ $message }}@enderror @error('phone'){{ $message }}@enderror</p>
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
                <section class="form-card wiz-step" data-step="address" data-title="Address">
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
                <section class="form-card wiz-step" data-step="security" data-title="Security">
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
                        <input type="file" id="buyerIdUpload" name="valid_id" required accept="image/jpeg,image/png,.pdf" class="hidden">
                        <img id="buyerIdPreview" class="file-preview hidden">
                    </div>
                </section>

                {{-- SELLER FIELDS --}}
                <section id="sellerSection" class="hidden-section form-card wiz-step" data-step="seller" data-title="Shop">
                    <h2 class="card-title"><span class="card-num">5</span> Shop Information</h2>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="fld-label" for="shopName">Shop Name* <span class="font-normal" style="color:#8a7a70;">(3–30 characters)</span></label>
                            <input type="text" name="shop_name" id="shopName" required class="form-input @error('shop_name') is-invalid @enderror"
                                   placeholder="e.g. Maria Clara Handicrafts" value="{{ old('shop_name') }}" maxlength="30">
                            @error('shop_name')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="fld-label" for="lineOfBusiness">Shop Category*</label>
                            <select name="line_of_business" id="lineOfBusiness" required class="form-input @error('line_of_business') is-invalid @enderror">
                                <option value="">Select category</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat }}" @selected(old('line_of_business') === $cat)>{{ $cat }}</option>
                                @endforeach
                            </select>
                            @error('line_of_business')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="fld-label" for="sellerType">Seller Type*</label>
                            <select name="seller_type" id="sellerType" required class="form-input @error('seller_type') is-invalid @enderror">
                                <option value="">Select seller type</option>
                                @foreach ($sellerTypes as $type)
                                    <option value="{{ $type }}" @selected(old('seller_type') === $type)>{{ $type }}</option>
                                @endforeach
                            </select>
                            @error('seller_type')<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="fld-label" for="tin">TIN*</label>
                            <input type="text" name="tin" id="tin" required class="form-input @error('tin') is-invalid @enderror"
                                   placeholder="000-000-000-000" inputmode="numeric" maxlength="15" value="{{ old('tin') }}">
                            @error('tin')
                                <p class="field-error">{{ $message }}</p>
                            @else
                                <p class="mt-1 text-[11px]" style="color:#8a7a70;">12 digits, including your 3-digit branch code (000 for the main office).</p>
                            @enderror
                        </div>
                    </div>

                    <div class="section-divider">
                        <div class="section-bar"></div>
                        <h3 class="text-sm font-bold" style="color:#3a2f29;">Business Documents</h3>
                    </div>
                    <p class="mb-3 text-xs" style="color:#8a7a70;">JPG, PNG or PDF · max 5 MB each. Only the admin team can see these.</p>

                    <div class="space-y-3">
                        @foreach ([
                            ['government_id',   'Government-issued ID*', 'Passport, driver\'s license, UMID, PhilID, etc.', ''],
                            ['dti_certificate', 'DTI Certificate*',      'Business name registration certificate.',        'dti'],
                            ['sec_certificate', 'SEC Certificate*',      'Required for corporations.',                     'sec'],
                            ['bir_2303',        'BIR Form 2303*',        'Certificate of Registration from the BIR.',      ''],
                        ] as [$field, $label, $hint, $group])
                            <div @if ($group) data-doc-group="{{ $group }}" @endif>
                                <label class="fld-label">{{ $label }}</label>
                                <div class="file-upload-box" onclick="document.getElementById('seller_{{ $field }}').click()">
                                    <span class="upload-icon">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold" style="color:#3a2f29;" id="seller_{{ $field }}_label">Click to upload</p>
                                        <p class="truncate text-xs" style="color:#9a8b81;">{{ $hint }}</p>
                                    </div>
                                </div>
                                <input type="file" id="seller_{{ $field }}" name="{{ $field }}" required accept="image/jpeg,image/png,.pdf" class="hidden">
                                <img id="seller_{{ $field }}_preview" class="file-preview hidden">
                                @error($field)<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-3 flex gap-2 rounded-lg border p-3 text-sm" style="background:#fff5f3;border-color:#fdd5c8;">
                        <svg class="mt-0.5 h-4 w-4 flex-shrink-0" style="color:#fa4e1c;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p style="color:#b4380f;">After you verify your email, our team will review your shop in 1–3 business days.</p>
                    </div>
                </section>

                {{-- COURIER FIELDS --}}
                <section id="courierSection" class="hidden-section form-card wiz-step" data-step="courier" data-title="Courier">
                    <h2 class="card-title"><span class="card-num">5</span> Courier Details</h2>

                    <div class="section-divider">
                        <div class="section-bar"></div>
                        <h3 class="text-sm font-bold" style="color:#3a2f29;">Vehicle Information</h3>
                    </div>

                    <div class="mb-3">
                        <label class="fld-label">Vehicle Type</label>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
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
                            <div class="wheel-chip p-3 text-center" data-wheel="truck">
                                <svg class="mx-auto mb-1 h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2 6h12v11H2zM14 10h4l4 4v3h-8"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>
                                </svg>
                                <p class="text-xs font-semibold">6+ Wheels<br/>(Truck)</p>
                            </div>
                        </div>
                        <input type="hidden" name="vehicle_type" id="vehicleTypeInput" value="motorcycle">
                    </div>

                    <div class="mb-3">
                        <label class="fld-label" for="plateNumber">Plate Number*</label>
                        <input type="text" name="plate_number" id="plateNumber" required class="form-input @error('plate_number') is-invalid @enderror" placeholder="ABC 1234" value="{{ old('plate_number') }}" style="text-transform:uppercase;">
                        @error('plate_number')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="fld-label">Driver's License*</label>
                        <div class="file-upload-box" onclick="document.getElementById('driverLicenseUpload').click()">
                            <span class="upload-icon">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            </span>
                            <p class="text-sm font-semibold" style="color:#3a2f29;">Click to upload Driver's License</p>
                        </div>
                        <input type="file" id="driverLicenseUpload" name="or_cr_image" required accept="image/jpeg,image/png,.pdf" class="hidden">
                        <img id="driverLicensePreview" class="file-preview hidden">
                        @error('or_cr_image')<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <div class="mb-3">
                        <label class="fld-label">Vehicle Registration (OR/CR)*</label>
                        <div class="file-upload-box" onclick="document.getElementById('vehicleRegUpload').click()">
                            <span class="upload-icon">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                            </span>
                            <p class="text-sm font-semibold" style="color:#3a2f29;">Click to upload Vehicle Registration</p>
                        </div>
                        <input type="file" id="vehicleRegUpload" name="vehicle_reg_image" required accept="image/jpeg,image/png,.pdf" class="hidden">
                        <img id="vehicleRegPreview" class="file-preview hidden">
                        @error('vehicle_reg_image')<p class="field-error">{{ $message }}</p>@enderror
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

                {{-- LOGISTICS (Sorting Center) FIELDS — the Address step is the hub address --}}
                <section id="logisticsSection" class="hidden-section form-card wiz-step" data-step="logistics" data-title="Sorting Center">
                    <h2 class="card-title"><span class="card-num">5</span> Sorting Center Information</h2>

                    <div class="mb-3">
                        <label class="fld-label" for="logisticsBusinessName">Business / Center Name*</label>
                        <input type="text" name="business_name" id="logisticsBusinessName" required maxlength="150"
                               class="form-input @error('business_name') is-invalid @enderror" placeholder="e.g. ALVY Hub Lipa" value="{{ old('business_name') }}">
                        @error('business_name')<p class="field-error">{{ $message }}</p>@enderror
                        <p class="mt-1 text-[11px]" style="color:#8a7a70;">The address you entered in the Address step is used as the hub address.</p>
                    </div>

                    <div class="section-divider">
                        <div class="section-bar"></div>
                        <h3 class="text-sm font-bold" style="color:#3a2f29;">Documents</h3>
                    </div>
                    <p class="mb-3 text-xs" style="color:#8a7a70;">JPG, PNG or PDF · max 5 MB each. Only the admin team can see these.</p>

                    @foreach ([
                        ['government_id',   'Government-issued ID*',  'Passport, driver\'s license, UMID, PhilID, etc.'],
                        ['business_permit', 'Business / DTI Permit*', 'Business permit or DTI business name registration.'],
                    ] as [$field, $label, $hint])
                        <div class="mb-3">
                            <label class="fld-label">{{ $label }}</label>
                            <div class="file-upload-box" onclick="document.getElementById('logistics_{{ $field }}').click()">
                                <span class="upload-icon">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold" style="color:#3a2f29;" id="logistics_{{ $field }}_label">Click to upload</p>
                                    <p class="truncate text-xs" style="color:#9a8b81;">{{ $hint }}</p>
                                </div>
                            </div>
                            <input type="file" id="logistics_{{ $field }}" name="{{ $field }}" required accept="image/jpeg,image/png,.pdf" class="hidden">
                            <img id="logistics_{{ $field }}_preview" class="file-preview hidden">
                            @error($field)<p class="field-error">{{ $message }}</p>@enderror
                        </div>
                    @endforeach

                    <div class="flex gap-2 rounded-lg border p-3 text-sm" style="background:#fff5f3;border-color:#fdd5c8;">
                        <svg class="mt-0.5 h-4 w-4 flex-shrink-0" style="color:#fa4e1c;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p style="color:#b4380f;">Your documents and hub address will be reviewed by the admin. You can sign in to the Sorting Center portal once approved.</p>
                    </div>
                </section>
            </div>

            {{-- Footer (sticky): Back / Next, and terms + submit on the last step --}}
            <div class="sticky-submit px-6 py-4 sm:px-10">
                <label class="flex cursor-pointer items-start gap-3" id="wizTerms" hidden>
                    <input type="checkbox" id="agreeTerms" name="terms" value="1" class="mt-0.5 h-4 w-4 flex-shrink-0 rounded" style="accent-color:#fa4e1c;" @checked(old('terms')) required>
                    <span class="text-sm" style="color:#5a4d45;">
                        I agree to ALVY's
                        <a href="{{ route('policies.show', 'terms') }}" target="_blank" class="font-semibold underline" style="color:#fa4e1c;" onclick="event.stopPropagation()">Terms and Conditions</a>
                        and
                        <a href="{{ route('policies.show', 'privacy') }}" target="_blank" class="font-semibold underline" style="color:#fa4e1c;" onclick="event.stopPropagation()">Privacy Policy</a>.
                    </span>
                </label>
                @error('terms')
                    <p class="mt-2 text-xs" style="color:#DC2626;" data-wiz-terms-error>{{ $message }}</p>
                @enderror

                <div class="mt-4 flex flex-col-reverse items-center gap-3 sm:flex-row sm:justify-between">
                    <p class="text-sm" style="color:#8a7a70;">
                        Already have an account?
                        <a href="{{ route('login') }}" class="font-bold" style="color:#222;"
                           onmouseover="this.style.color='#fa4e1c';" onmouseout="this.style.color='#222';">Log In</a>
                    </p>
                    <div class="flex w-full gap-2 sm:w-auto">
                        <button type="button" id="wizBack" hidden
                                class="btn-ghost flex flex-1 items-center justify-center gap-1 rounded-xl px-5 py-3 text-sm font-bold transition sm:flex-none">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                            Back
                        </button>
                        <button type="button" id="wizNext"
                                class="btn-primary-grad flex flex-1 items-center justify-center gap-1 rounded-xl px-8 py-3 text-sm font-bold text-white transition sm:flex-none">
                            Next
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        </button>
                        <button type="submit" id="submitBtn" hidden
                                class="btn-primary-grad flex flex-1 items-center justify-center gap-2 rounded-xl px-8 py-3 text-sm font-bold text-white transition sm:flex-none"
                                style="opacity:0.5;cursor:not-allowed;"
                                disabled>
                            Create Account
                        </button>
                    </div>
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
    // (seller and logistics sections share field names like government_id)
    function syncSectionInputs() {
        [sellerSection, courierSection, logisticsSection].forEach(sec => {
            const hidden = sec.classList.contains('hidden-section');
            sec.querySelectorAll('input, select').forEach(el => {
                if (hidden) {
                    el.disabled = true;
                } else {
                    el.disabled = false;
                }
            });
        });
    }
    syncSectionInputs();

    // Role Selection
    roleChips.forEach(chip => {
        chip.addEventListener('click', function() {
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
    ['government_id', 'dti_certificate', 'sec_certificate', 'bir_2303'].forEach(function (f) {
        setupFilePreview('seller_' + f, 'seller_' + f + '_preview', 'seller_' + f + '_label');
    });
    setupFilePreview('driverLicenseUpload', 'driverLicensePreview');
    setupFilePreview('vehicleRegUpload', 'vehicleRegPreview');
    ['government_id', 'business_permit'].forEach(function (f) {
        setupFilePreview('logistics_' + f, 'logistics_' + f + '_preview', 'logistics_' + f + '_label');
    });
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

    // Sellers: corporations upload an SEC certificate, everyone else a DTI certificate.
    const sellerType = document.getElementById('sellerType');
    function syncSellerDocs() {
        const corp = sellerType.value === 'Corporation';
        document.querySelector('[data-doc-group="dti"]').style.display = corp ? 'none' : '';
        document.querySelector('[data-doc-group="sec"]').style.display = corp ? '' : 'none';
    }
    sellerType.addEventListener('change', syncSellerDocs);
    syncSellerDocs();

    // Re-open the role from a failed submit, or from links like /register?role=seller.
    // (A programmatic click selects the role without moving the wizard forward.)
    const initialRole = @json(old('role', request('role', '')));
    const initialChip = initialRole ? document.querySelector('.role-chip[data-role="' + initialRole + '"]') : null;
    if (initialChip) initialChip.click();
    
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

</script>
@if (file_exists(public_path('js/psgc-address.js')))
<script src="{{ asset('js/psgc-address.js') }}"></script>
@endif
<script src="{{ asset('js/register-address-fallback.js') }}"></script>
<script src="{{ asset('js/register-validation.js') }}"></script>
<script src="{{ asset('js/register-wizard.js') }}"></script>
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
