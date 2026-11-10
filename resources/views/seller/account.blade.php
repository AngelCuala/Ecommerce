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
    $nav = \App\Support\AccountNav::seller('account');
    $badges = array_values(array_filter([
        ['text' => 'Seller'],
        $application ? ['text' => $application->isApproved() ? 'Approved' : ucfirst($application->status), 'tone' => $application->isApproved() ? 'green' : 'gray'] : null,
        ['text' => 'Since ' . $u->created_at->format('M Y'), 'tone' => 'gray'],
    ]));
@endphp
<style>
    /* The bar that appears with unsaved changes */
    .ac-save { display: flex; flex-wrap: wrap; align-items: center; justify-content: flex-end; gap: .75rem; border-radius: 1rem; transition: box-shadow .15s; }
    .ac-save.is-dirty { position: sticky; bottom: 1rem; z-index: 20; justify-content: space-between; border: 1px solid #F0E4DC; background: #fff; padding: .75rem 1rem; box-shadow: 0 8px 28px rgba(34, 34, 34, .16); }
    .ac-save-state { display: none; font-size: .8125rem; font-weight: 600; color: #92400E; }
    .ac-save > span:last-child { margin-left: auto; }
    .ac-save.is-dirty .ac-save-state, .ac-save.is-dirty [data-discard] { display: inline-flex; }
    .acs-input:has(+ .acs-error) { border-color: #DC2626; box-shadow: 0 0 0 3px rgba(220, 38, 38, .12); }
    .ac-docs { display: grid; gap: 10px; grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); }
    .ac-doc { display: flex; align-items: center; gap: 10px; border: 1px solid #F5EBE4; border-radius: 12px; padding: 10px 12px; background: #FDF8F5; font-size: 13.5px; font-weight: 600; }
    .ac-doc a { margin-left: auto; font-weight: 700; color: #fa4e1c; text-decoration: none; }
</style>

<x-account-shell title="My Account" subtitle="Manage your profile and shop details." portal="Seller Centre" :nav="$nav" :badges="$badges" :sign-out="true">
    @if ($errors->any())
        <div class="acs-alert acs-alert-error" role="alert">Nothing was saved yet. Please fix the highlighted {{ \Illuminate\Support\Str::plural('field', $errors->count()) }} below.</div>
    @endif

    <form action="{{ route('seller.account.update') }}" method="POST" enctype="multipart/form-data" style="display:grid;gap:20px;" data-account-form data-start-dirty="{{ $errors->any() ? '1' : '0' }}">
        @csrf @method('PATCH')

        {{-- Personal information --}}
        <section class="acs-card acs-pad" data-account-hero>
            <div class="acs-card-head">
                <div>
                    <span class="acs-icon"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg></span>
                    <div><h2>Personal Information</h2><p>How you appear to buyers and the ALVY team.</p></div>
                </div>
            </div>
            <div class="acs-fields">
                <div class="wide">
                    <label class="acs-label" for="name">Full name</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $u->name) }}" class="acs-input" required>
                    @error('name')<p class="acs-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="acs-label" for="email">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $u->email) }}" class="acs-input" required>
                    @error('email')<p class="acs-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="acs-label" for="phone">Mobile number</label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone', $u->phone ?: $u->contact_no) }}" class="acs-input" placeholder="09171234567" maxlength="13" inputmode="tel" autocomplete="tel" data-mobile oninput="this.value=this.value.startsWith('+')?('+'+this.value.slice(1).replace(/\D/g,'')).slice(0,13):this.value.replace(/\D/g,'').slice(0,11)">
                    @error('phone')<p class="acs-error">{{ $message }}</p>@enderror
                </div>
                <div class="wide">
                    <label class="acs-label" for="address">House no., street and barangay</label>
                    <input type="text" id="address" name="address" value="{{ old('address', $u->address ?: $u->street) }}" class="acs-input" placeholder="e.g. 12 Rizal Street, Barangay Uno" autocomplete="street-address">
                </div>
                <div>
                    <label class="acs-label" for="city">City</label>
                    <input type="text" id="city" name="city" value="{{ old('city', $u->city ?: $u->municipality) }}" class="acs-input" placeholder="e.g. Calamba" autocomplete="address-level2">
                </div>
                <div>
                    <label class="acs-label" for="zip">ZIP code</label>
                    <input type="text" id="zip" name="zip" value="{{ old('zip', $u->zip) }}" class="acs-input" inputmode="numeric" maxlength="10" placeholder="e.g. 4027" autocomplete="postal-code">
                </div>
            </div>
            @error('avatar')<p class="acs-error">{{ $message }}</p>@enderror
        </section>

        {{-- Shop --}}
        @if ($application)
            <section class="acs-card acs-pad">
                <div class="acs-card-head">
                    <div>
                        <span class="acs-icon" style="background:#EEF3F7;color:#002b4d;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9l1.5-5h15L21 9M3 9h18M3 9a3 3 0 006 0 3 3 0 006 0 3 3 0 006 0M5 12v8h14v-8"/></svg></span>
                        <div><h2>Shop</h2><p>Details from your approved seller application.</p></div>
                    </div>
                    <span class="acs-badge {{ $application->isApproved() ? 'acs-badge-green' : 'acs-badge-gray' }}">{{ ucfirst($application->status) }}</span>
                </div>

                <div style="margin-bottom:18px;">
                    <div style="display:flex;flex-wrap:wrap;align-items:baseline;justify-content:space-between;gap:8px;margin-bottom:6px;">
                        <label class="acs-label" for="shop_name" style="margin:0;">Shop name</label>
                        <span class="acs-badge {{ $canChange ? 'acs-badge-gray' : 'acs-badge-red' }}">{{ $canChange ? $remaining . ' of 3 changes left this month' : 'No changes left until ' . $resetsAt }}</span>
                    </div>
                    @if ($canChange)
                        <input type="text" id="shop_name" name="shop_name" value="{{ old('shop_name', $application->shop_name) }}" class="acs-input" maxlength="30">
                    @else
                        <input type="text" id="shop_name" value="{{ $application->shop_name }}" class="acs-input" disabled>
                    @endif
                    @error('shop_name')
                        <p class="acs-error">{{ $message }}</p>
                    @else
                        <p class="acs-note" style="display:flex;justify-content:space-between;gap:12px;"><span>3–30 characters. You can rename your shop up to 3 times a month.</span>@if ($canChange)<span style="flex:none;"><span data-shop-count>{{ mb_strlen(old('shop_name', $application->shop_name)) }}</span> / 30</span>@endif</p>
                    @enderror
                </div>

                @if ($facts)
                    <dl class="acs-tiles">
                        @foreach ($facts as $label => $value)
                            <div class="acs-tile {{ $label === 'Shop address' ? 'wide' : '' }}"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                        @endforeach
                    </dl>
                @endif

                @if ($documents)
                    <p class="acs-label" style="margin:18px 0 8px;">Documents</p>
                    <div class="ac-docs">
                        @foreach ($documents as $label => $path)
                            <div class="ac-doc">
                                <svg width="16" height="16" fill="none" stroke="#fa4e1c" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6Zm0 0v6h6"/></svg>
                                <span>{{ $label }}</span>
                                <a href="{{ \App\Support\ProtectedFile::url($path) }}" target="_blank" rel="noopener" aria-label="View {{ $label }}">View</a>
                            </div>
                        @endforeach
                    </div>
                    <p class="acs-note">To change your business details or documents, contact ALVY support.</p>
                @endif
            </section>
        @endif

        <div class="ac-save" data-save-bar>
            <span class="ac-save-state" role="status">You have unsaved changes</span>
            <span style="display:flex;align-items:center;gap:12px;">
                <button type="button" class="acs-btn-ghost" style="display:none;" data-discard>Discard</button>
                <button type="submit" class="acs-btn" data-save>Save changes</button>
            </span>
        </div>
    </form>
</x-account-shell>

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
        discard.style.display = d ? '' : 'none';
        save.disabled = !d;
        var shop = form.elements.shop_name;
        if (shopCount && shop) shopCount.textContent = shop.value.length;
    }

    form.addEventListener('input', sync);
    // The name in the profile card follows what is typed.
    var cardName = document.querySelector('[data-account-name]');
    if (cardName) form.elements.name.addEventListener('input', function () { cardName.textContent = form.elements.name.value.trim() || '—'; });
    discard.addEventListener('click', function () {
        if (failedSave) { window.location.href = window.location.pathname; return; }
        form.reset();
        sync();
    });
    form.addEventListener('submit', function () {
        leaving = true;
        save.textContent = 'Saving…';
        setTimeout(function () { save.disabled = true; }, 0);
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

    var first = form.querySelector('.acs-input:has(+ .acs-error)');
    if (first) { first.scrollIntoView({ block: 'center' }); first.focus({ preventScroll: true }); }
})();
</script>
</x-seller-layout>
