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
    .ac-section { background: #fff; border: 1px solid #ECE7E3; border-radius: .75rem; }
    .ac-head { padding: 1rem 1.5rem; border-bottom: 1px solid #F2EEEA; }
    .ac-head h2 { font-size: 1rem; font-weight: 600; color: #222; }
    .ac-head p { margin-top: .15rem; font-size: .8125rem; color: #8a7f78; }
    .ac-body { padding: 1.25rem 1.5rem; }
    .ac-label { display: block; margin-bottom: .3rem; font-size: .8125rem; font-weight: 500; color: #44403C; }
    .ac-note { margin-top: .3rem; font-size: .75rem; color: #8a7f78; }
    .ac-error { margin-top: .3rem; font-size: .75rem; color: #B91C1C; }
</style>

<div class="mx-auto max-w-3xl">

    <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-2xl font-bold" style="color:#222;">Account</h1>
            <p class="mt-0.5 text-sm" style="color:#8a7f78;">Your profile and shop details.</p>
        </div>
        @include('partials.panel.sign-out')
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-md border px-4 py-3 text-sm" style="background:#FEF2F2;border-color:#FECACA;color:#B91C1C;">
            Please fix the highlighted fields below.
        </div>
    @endif

    <form action="{{ route('seller.account.update') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
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
                            class="group relative flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full"
                            style="background:#F5F2EF;" aria-label="Change profile photo">
                        @if ($u->profile_photo_path)
                            <img id="avatar-preview" src="{{ asset('storage/' . $u->profile_photo_path) }}" class="h-full w-full object-cover" alt="">
                        @else
                            <span id="avatar-initial" class="font-display text-xl font-bold" style="color:#8a7f78;">{{ strtoupper(mb_substr($u->name, 0, 1)) }}</span>
                        @endif
                        <span class="absolute inset-0 hidden items-center justify-center text-[11px] font-medium text-white group-hover:flex" style="background:rgba(0,0,0,.45);">Change</span>
                    </button>
                    <div class="min-w-0">
                        <input id="avatar-input" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="hidden" onchange="previewAvatar(this)">
                        <label for="avatar-input" class="inline-block cursor-pointer rounded-md border px-3 py-1.5 text-sm font-medium transition hover:bg-stone-50" style="border-color:#E7E5E4;color:#44403C;">
                            {{ $u->profile_photo_path ? 'Change photo' : 'Upload photo' }}
                        </label>
                        <p id="avatar-filename" class="ac-note">JPG, PNG or WebP, up to 2 MB.</p>
                        @error('avatar')<p class="ac-error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
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
                        <input type="tel" id="phone" name="phone" value="{{ old('phone', $u->phone ?: $u->contact_no) }}" class="input" placeholder="09171234567" inputmode="tel">
                        @error('phone')<p class="ac-error">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="ac-label" for="city">City</label>
                        <input type="text" id="city" name="city" value="{{ old('city', $u->city ?: $u->municipality) }}" class="input">
                    </div>
                    <div>
                        <label class="ac-label" for="address">Address</label>
                        <input type="text" id="address" name="address" value="{{ old('address', $u->address ?: $u->street) }}" class="input">
                    </div>
                    <div>
                        <label class="ac-label" for="zip">ZIP code</label>
                        <input type="text" id="zip" name="zip" value="{{ old('zip', $u->zip) }}" class="input" inputmode="numeric" maxlength="10">
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
                    <span class="rounded-md px-2 py-0.5 text-xs font-semibold"
                          style="{{ $application->isApproved() ? 'background:#ECFDF5;color:#047857;' : 'background:#F5F5F4;color:#57534E;' }}">
                        {{ ucfirst($application->status) }}
                    </span>
                </div>
                <div class="ac-body space-y-5">
                    <div>
                        <div class="mb-1 flex flex-wrap items-baseline justify-between gap-2">
                            <label class="ac-label !mb-0" for="shop_name">Shop name</label>
                            <span class="text-xs" style="color:{{ $canChange ? '#8a7f78' : '#B91C1C' }};">
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
                            <p class="ac-note">3–30 characters. You can rename your shop up to 3 times a month.</p>
                        @enderror
                    </div>

                    @if ($facts)
                        <dl class="grid gap-x-6 gap-y-3 border-t pt-4 text-sm sm:grid-cols-2" style="border-color:#F2EEEA;">
                            @foreach ($facts as $label => $value)
                                <div class="{{ in_array($label, ['Shop address']) ? 'sm:col-span-2' : '' }}">
                                    <dt style="color:#8a7f78;">{{ $label }}</dt>
                                    <dd class="mt-0.5" style="color:#222;">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    @if ($documents)
                        <div class="border-t pt-4" style="border-color:#F2EEEA;">
                            <p class="mb-2 text-sm" style="color:#8a7f78;">Documents</p>
                            <ul class="divide-y rounded-md border" style="border-color:#ECE7E3;">
                                @foreach ($documents as $label => $path)
                                    <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm" style="border-color:#F2EEEA;">
                                        <span style="color:#222;">{{ $label }}</span>
                                        <a href="{{ asset('storage/' . $path) }}" target="_blank" rel="noopener" class="font-medium hover:underline" style="color:#fa4e1c;">View</a>
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
            <div class="ac-body flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold" style="color:#222;">Password</h2>
                    <p class="mt-0.5 text-sm" style="color:#8a7f78;">Change the password you use to sign in.</p>
                </div>
                <a href="{{ route('seller.account.security') }}" class="rounded-md border px-3 py-1.5 text-sm font-medium transition hover:bg-stone-50" style="border-color:#E7E5E4;color:#44403C;">Change password</a>
            </div>
        </section>

        <div class="flex justify-end">
            <button type="submit" class="rounded-md px-5 py-2 text-sm font-semibold text-white transition hover:opacity-90" style="background:#fa4e1c;">Save changes</button>
        </div>
    </form>
</div>

<script>
function previewAvatar(input) {
    const file = input.files && input.files[0];
    if (!file) return;
    document.getElementById('avatar-filename').textContent = file.name;
    const box = document.getElementById('avatar-box');
    document.getElementById('avatar-initial')?.remove();
    let img = document.getElementById('avatar-preview');
    if (!img) {
        img = document.createElement('img');
        img.id = 'avatar-preview';
        img.className = 'h-full w-full object-cover';
        img.alt = '';
        box.prepend(img);
    }
    img.src = URL.createObjectURL(file);
}
</script>
</x-seller-layout>
