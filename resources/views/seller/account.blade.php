<x-seller-layout title="Account" active="account">
@php
    $u = auth()->user();
    if ($application) {
        $remaining = $application->shopNameChangesRemaining();
        $canChange = $application->canChangeShopName();
        $resetsAt  = $application->shopNameResetsAt()->format('M j, Y');
        $documents = array_filter([
            'Government-issued ID' => $application->government_id_path,
            'DTI Certificate'      => $application->dti_certificate_path,
            'SEC Certificate'      => $application->sec_certificate_path,
            'BIR Form 2303'        => $application->bir_2303_path,
            'Business Permit'      => $application->business_permit_path,
        ]);
        $facts = array_filter([
            'Owner'         => $application->fullName(),
            'Category'      => $application->line_of_business,
            'Seller type'   => $application->seller_type,
            'TIN'           => $application->tin,
            'Business name' => $application->business_name,
            'Shop contact'  => $application->phone,
            'Shop address'  => $application->address,
        ]);
    }
@endphp
<style>
    .ac-section { background: #fff; border: 1px solid #ECE7E3; border-radius: 1rem; box-shadow: 0 1px 2px rgba(34, 34, 34, .04); }
    .ac-head { padding: 1.25rem 1.5rem; border-bottom: 1px solid #F2EEEA; }
    .ac-head h2 { font-size: 1rem; font-weight: 600; color: #222; }
    .ac-head p { margin-top: .15rem; font-size: .8125rem; color: #8a7f78; }
    .ac-body { padding: 1.25rem 1.5rem; }
    .ac-label { display: block; margin-bottom: .3rem; font-size: .8125rem; font-weight: 500; color: #44403C; }
    .ac-note { margin-top: .3rem; font-size: .75rem; color: #8a7f78; }
    .ac-error { margin-top: .3rem; font-size: .75rem; font-weight: 500; color: #B91C1C; }
    .input:has(+ .ac-error) { border-color: #DC2626; box-shadow: 0 0 0 3px rgba(220, 38, 38, .12); }
    .input:disabled { background: #FAFAF9; color: #8a7f78; cursor: not-allowed; }
    .ac-group { padding-top: 1.25rem; border-top: 1px solid #F2EEEA; }
    .ac-group-title { margin-bottom: .75rem; font-size: .8125rem; font-weight: 600; color: #222; }
    .ac-btn { display: inline-flex; align-items: center; gap: .4rem; border: 1px solid #E7E5E4; border-radius: .5rem; padding: .45rem .875rem; background: #fff; font-size: .8125rem; font-weight: 600; color: #44403C; cursor: pointer; transition: border-color .15s, color .15s; }
    .ac-btn:hover { border-color: #fa4e1c; color: #fa4e1c; }
    .ac-pill { padding: .15rem .6rem; border-radius: 9999px; font-size: .75rem; font-weight: 600; }
    .ac-fact { padding: .625rem .875rem; border-radius: .625rem; background: #FAFAF9; }
    .ac-fact dt { font-size: .75rem; color: #8a7f78; }
    .ac-fact dd { margin-top: .1rem; font-size: .875rem; font-weight: 500; color: #222; overflow-wrap: anywhere; }
    .ac-doc-icon { display: flex; flex: none; height: 2rem; width: 2rem; align-items: center; justify-content: center; border-radius: .5rem; background: #FFF1EB; color: #fa4e1c; }
    .ac-row-icon { display: flex; flex: none; height: 2.5rem; width: 2.5rem; align-items: center; justify-content: center; border-radius: .625rem; }
    .ac-save { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: .75rem; border-radius: 1rem; transition: box-shadow .15s; }
    .ac-save.is-dirty { position: sticky; bottom: 1rem; z-index: 20; justify-content: space-between; border: 1px solid #ECE7E3; background: #fff; padding: .75rem 1rem; box-shadow: 0 8px 28px rgba(34, 34, 34, .16); }
    .ac-save-state { display: none; font-size: .8125rem; font-weight: 500; color: #92400E; }
    .ac-save > span:last-child { margin-left: auto; }
    .ac-save.is-dirty .ac-save-state, .ac-save.is-dirty [data-discard] { display: inline-block; }
    .ac-primary { border-radius: .5rem; padding: .55rem 1.25rem; background: #fa4e1c; font-size: .875rem; font-weight: 600; color: #fff; transition: opacity .15s; }
    .ac-primary:hover { opacity: .9; }
    .ac-primary:disabled { background: #F5F2EF; color: #a39890; opacity: 1; cursor: default; }
</style>

<div class="mx-auto max-w-3xl">

    <div class="mb-5">
        <h1 class="font-display text-2xl font-bold" style="color:#222;">Account</h1>
        <p class="mt-0.5 text-sm" style="color:#8a7f78;">Your profile and shop details.</p>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-2xl border px-5 py-3.5 text-sm font-medium" style="background:#FEF2F2;border-color:#FECACA;color:#991B1B;" role="alert">
            Nothing was saved yet. Please fix the highlighted {{ \Illuminate\Support\Str::plural('field', $errors->count()) }} below.
        </div>
    @endif

    <form action="{{ route('seller.account.update') }}" method="POST" enctype="multipart/form-data" class="space-y-5" data-account-form data-start-dirty="{{ $errors->any() ? '1' : '0' }}">
        @csrf @method('PATCH')

        {{-- ── Profile ─────────────────────────────────────── --}}
        <section class="ac-section">
            <div class="ac-head">
                <h2>Profile</h2>
                <p>How you appear to buyers and the ALVY team.</p>
            </div>
            <div class="ac-body space-y-5">
                <div class="flex items-center gap-4">
                    <button type="button" id="avatar-box" onclick="document.getElementById('avatar-input').click()"
                            class="group relative flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full"
                            style="background:#FFF1EB;box-shadow:0 0 0 3px #fff,0 0 0 4px #ECE7E3;" aria-label="Change profile photo">
                        <img id="avatar-preview" data-photo-preview src="{{ $u->profile_photo_path ? asset('storage/' . $u->profile_photo_path) : '' }}"
                             class="{{ $u->profile_photo_path ? '' : 'hidden' }} h-full w-full object-cover" alt="">
                        @unless ($u->profile_photo_path)
                            <span id="avatar-initial" data-photo-initial class="font-display text-2xl font-bold" style="color:#d93d0e;">{{ strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                        @endunless
                        <span class="absolute inset-0 hidden items-center justify-center text-[11px] font-medium text-white group-hover:flex" style="background:rgba(0,0,0,.45);">Change</span>
                    </button>
                    <div class="min-w-0">
                        <input id="avatar-input" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" data-photo-upload data-photo-url="{{ route('account.photo') }}">
                        <label for="avatar-input" class="ac-btn">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 0 1 2-2h1.2a2 2 0 0 0 1.7-.9l.7-1.2A2 2 0 0 1 10.3 4h3.4a2 2 0 0 1 1.7.9l.7 1.2a2 2 0 0 0 1.7.9H19a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9Z"/><circle cx="12" cy="13" r="3.5"/></svg>
                            {{ $u->profile_photo_path ? 'Change photo' : 'Upload photo' }}
                        </label>
                        <p id="avatar-filename" class="ac-note" data-photo-status>JPG, PNG or WebP, up to 5 MB. Saved as soon as you choose it.</p>
                        @error('avatar')<p class="ac-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="ac-label" for="name">Full name</label>
                        <input type="text" id="name" name="name" value="{{ old('name', $u->name) }}" class="input" required>
                        @error('name')<p class="ac-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="ac-label" for="email">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email', $u->email) }}" class="input" required>
                        @error('email')<p class="ac-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="ac-label" for="phone">Mobile number</label>
                        <input type="tel" id="phone" name="phone" value="{{ old('phone', $u->phone ?: $u->contact_no) }}" class="input" placeholder="09171234567" maxlength="13" inputmode="tel" autocomplete="tel" data-mobile oninput="this.value=this.value.startsWith('+')?('+'+this.value.slice(1).replace(/\D/g,'')).slice(0,13):this.value.replace(/\D/g,'').slice(0,11)">
                        @error('phone')<p class="ac-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="ac-group">
                    <p class="ac-group-title">Address</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label class="ac-label" for="address">House no., street and barangay</label>
                            <input type="text" id="address" name="address" value="{{ old('address', $u->address ?: $u->street) }}" class="input" placeholder="e.g. 12 Rizal Street, Barangay Uno" autocomplete="street-address">
                        </div>
                        <div>
                            <label class="ac-label" for="city">City</label>
                            <input type="text" id="city" name="city" value="{{ old('city', $u->city ?: $u->municipality) }}" class="input" placeholder="e.g. Calamba" autocomplete="address-level2">
                        </div>
                        <div>
                            <label class="ac-label" for="zip">ZIP code</label>
                            <input type="text" id="zip" name="zip" value="{{ old('zip', $u->zip) }}" class="input" inputmode="numeric" maxlength="10" placeholder="e.g. 4027" autocomplete="postal-code">
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ── Shop ────────────────────────────────────────── --}}
        @if ($application)
            <section class="ac-section">
                <div class="ac-head flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2>Shop</h2>
                        <p>Details from your approved seller application.</p>
                    </div>
                    <span class="ac-pill"
                          style="{{ $application->isApproved() ? 'background:#ECFDF5;color:#047857;' : 'background:#F5F5F4;color:#57534E;' }}">
                        {{ ucfirst($application->status) }}
                    </span>
                </div>
                <div class="ac-body space-y-5">
                    <div>
                        <div class="mb-1 flex flex-wrap items-baseline justify-between gap-2">
                            <label class="ac-label !mb-0" for="shop_name">Shop name</label>
                            <span class="ac-pill" style="{{ $canChange ? 'background:#F5F2EF;color:#57534E;' : 'background:#FEF2F2;color:#B91C1C;' }}">
                                @if ($canChange)
                                    {{ $remaining }} of 3 changes left this month
                                @else
                                    No changes left until {{ $resetsAt }}
                                @endif
                            </span>
                        </div>
                        @if ($canChange)
                            <input type="text" id="shop_name" name="shop_name" value="{{ old('shop_name', $application->shop_name) }}" class="input" maxlength="30">
                        @else
                            <input type="text" id="shop_name" value="{{ $application->shop_name }}" class="input" disabled>
                        @endif
                        @error('shop_name')
                            <p class="ac-error">{{ $message }}</p>
                        @else
                            <p class="ac-note flex justify-between gap-3"><span>3–30 characters. You can rename your shop up to 3 times a month.</span>@if ($canChange)<span class="flex-none"><span data-shop-count>{{ mb_strlen(old('shop_name', $application->shop_name)) }}</span> / 30</span>@endif</p>
                        @enderror
                    </div>

                    @if ($facts)
                        <dl class="ac-group grid gap-2 sm:grid-cols-2">
                            @foreach ($facts as $label => $value)
                                <div class="ac-fact {{ in_array($label, ['Shop address']) ? 'sm:col-span-2' : '' }}">
                                    <dt>{{ $label }}</dt>
                                    <dd>{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    @if ($documents)
                        <div class="ac-group">
                            <p class="ac-group-title">Documents</p>
                            <ul class="divide-y overflow-hidden rounded-xl border" style="border-color:#ECE7E3;">
                                @foreach ($documents as $label => $path)
                                    <li class="flex items-center gap-3 px-3 py-2.5 text-sm" style="border-color:#F2EEEA;">
                                        <span class="ac-doc-icon" aria-hidden="true"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 0v6h6"/></svg></span>
                                        <span class="min-w-0 flex-1 font-medium" style="color:#222;">{{ $label }}</span>
                                        <a href="{{ \App\Support\ProtectedFile::url($path) }}" target="_blank" rel="noopener" class="flex-none font-semibold hover:underline" style="color:#fa4e1c;" aria-label="View {{ $label }}">View</a>
                                    </li>
                                @endforeach
                            </ul>
                            <p class="ac-note">To change your business details or documents, contact ALVY support.</p>
                        </div>
                    @endif
                </div>
            </section>
        @endif

        {{-- ── Password ────────────────────────────────────── --}}
        <section class="ac-section">
            <div class="ac-body flex flex-wrap items-center gap-3">
                <span class="ac-row-icon" style="background:#F5F2EF;color:#57534E;" aria-hidden="true"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="4" y="11" width="16" height="10" rx="2"/><path stroke-linecap="round" d="M8 11V7a4 4 0 0 1 8 0v4"/></svg></span>
                <div class="min-w-0 flex-1" style="min-width:12rem;">
                    <h2 class="text-base font-semibold" style="color:#222;">Password</h2>
                    <p class="mt-0.5 text-sm" style="color:#8a7f78;">Change the password you use to sign in.</p>
                </div>
                <a href="{{ route('seller.account.security') }}" class="ac-btn">Change password</a>
            </div>
        </section>

        <div class="ac-save" data-save-bar>
            <span class="ac-save-state" role="status">You have unsaved changes</span>
            <span class="flex items-center gap-3">
                <button type="button" class="hidden text-sm font-semibold hover:underline" style="color:#57534E;" data-discard>Discard</button>
                <button type="submit" class="ac-primary" data-save>Save changes</button>
            </span>
        </div>
    </form>

    {{-- Outside the form above: a form can't hold another form. --}}
    <section class="ac-section mt-5" style="background:#FEF2F2;border-color:#FECACA;">
        <div class="ac-body flex flex-wrap items-center gap-3">
            <span class="ac-row-icon" style="background:#FEE2E2;color:#DC2626;" aria-hidden="true"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg></span>
            <div class="min-w-0 flex-1" style="min-width:12rem;">
                <h2 class="text-base font-semibold" style="color:#7F1D1D;">Sign out</h2>
                <p class="mt-0.5 text-sm" style="color:#991B1B;">End your session on this browser.</p>
            </div>
            @include('partials.panel.sign-out')
        </div>
    </section>
</div>

<script>
(function () {
    var form = document.querySelector('[data-account-form]'),
        bar = form.querySelector('[data-save-bar]'),
        save = form.querySelector('[data-save]'),
        discard = form.querySelector('[data-discard]'),
        shopCount = form.querySelector('[data-shop-count]'),
        failedSave = form.dataset.startDirty === '1',   // what was typed is back in the fields, still unsaved
        leaving = false;
    var fields = Array.prototype.filter.call(form.elements, function (el) {
        return el.name && el.type !== 'hidden' && el.type !== 'file' && !el.disabled;
    });

    function dirty() {
        return failedSave || fields.some(function (el) { return el.value !== el.defaultValue; });
    }
    function sync() {
        var d = dirty();
        bar.classList.toggle('is-dirty', d);
        discard.classList.toggle('hidden', !d);
        save.disabled = !d;
        var shop = form.elements.shop_name;
        if (shopCount && shop) shopCount.textContent = shop.value.length;
    }

    form.addEventListener('input', sync);
    discard.addEventListener('click', function () {
        if (failedSave) { window.location.href = window.location.pathname; return; }   // the page's fields hold the failed values
        form.reset();
        sync();
    });
    form.addEventListener('submit', function () {
        leaving = true;
        save.textContent = 'Saving…';
        setTimeout(function () { save.disabled = true; }, 0);   // after the browser has taken the click
    });
    window.addEventListener('beforeunload', function (e) {
        if (leaving || !dirty() || failedSave) return;
        e.preventDefault();
        e.returnValue = '';
    });
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) { leaving = false; save.textContent = 'Save changes'; sync(); }
    });

    sync();

    // A failed save: go straight to the first field that needs fixing.
    var first = form.querySelector('.input:has(+ .ac-error)');
    if (first) {
        first.scrollIntoView({ block: 'center' });
        first.focus({ preventScroll: true });
    }
})();
</script>

<script src="{{ asset('js/photo-upload.js') }}"></script>
</x-seller-layout>
