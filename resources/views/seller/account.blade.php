<x-seller-layout title="Account Management" active="account">
<div>

    <div class="mb-6">
        <span class="section-eyebrow" style="color:#fa4e1c;">Seller Panel</span>
        <h1 class="mt-1 font-display text-2xl font-bold" style="color:#222222;">Account Management</h1>
    </div>

    @if (session('success'))
        <div class="mb-5 rounded-xl border p-4 text-sm" style="background:rgba(250,78,28,.08);border-color:rgba(250,78,28,.3);color:#d93d0e;">
            ✓ {{ session('success') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-5 rounded-xl border p-4 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
            <ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form action="{{ route('seller.account.update') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf @method('PATCH')

        {{-- ── Profile picture ─────────────────────────────── --}}
        <div class="card p-6">
            <h2 class="font-display text-base font-bold mb-4" style="color:#222222;">Profile Photo</h2>
            <div class="flex items-center gap-6">

                {{-- Avatar preview — click to trigger file input --}}
                <div class="relative shrink-0 cursor-pointer group" onclick="document.getElementById('avatar-input').click()">
                    <div class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-full border-2"
                         style="border-color:#fa4e1c;">
                        @if (auth()->user()->profile_photo_path)
                            <img id="avatar-preview"
                                 src="{{ asset('storage/'.auth()->user()->profile_photo_path) }}"
                                 class="h-24 w-24 rounded-full object-cover" alt="Profile photo">
                        @else
                            <span id="avatar-initial"
                                  class="text-3xl font-extrabold text-white select-none"
                                  style="background:#002b4d;width:100%;height:100%;display:flex;align-items:center;justify-content:center;">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </span>
                        @endif
                    </div>
                    {{-- Camera overlay --}}
                    <div class="absolute inset-0 flex items-center justify-center rounded-full opacity-0 group-hover:opacity-100 transition-opacity"
                         style="background:rgba(0,0,0,.45);">
                        <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <circle cx="12" cy="13" r="3"/>
                        </svg>
                    </div>
                </div>

                <div class="flex-1">
                    <p class="font-bold text-base" style="color:#222222;">{{ auth()->user()->name }}</p>
                    <p class="text-sm mb-3" style="color:#6b90aa;">{{ auth()->user()->email }}</p>
                    <input id="avatar-input" type="file" name="avatar"
                           accept="image/jpg,image/jpeg,image/png,image/webp"
                           class="hidden"
                           onchange="previewAvatar(this)">
                    <label for="avatar-input"
                           class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-4 py-2 text-xs font-semibold transition"
                           style="border-color:#fa4e1c;color:#fa4e1c;"
                           onmouseover="this.style.background='#fff1ee';"
                           onmouseout="this.style.background='';">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        Choose Photo
                    </label>
                    <p id="avatar-filename" class="mt-1.5 text-[11px]" style="color:#B0B0B0;">JPG, PNG or WebP · max 2 MB</p>
                </div>
            </div>
        </div>

        {{-- ── Personal information ─────────────────────────── --}}
        <div class="card p-6 space-y-4">
            <h2 class="font-display text-base font-bold" style="color:#222222;">Personal Information</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Full Name *</label>
                    <input type="text" name="name" value="{{ old('name', auth()->user()->name) }}"
                           class="input mt-1" required>
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Email *</label>
                    <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}"
                           class="input mt-1" required>
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Contact No.</label>
                    <input type="text" name="phone" value="{{ old('phone', auth()->user()->phone) }}"
                           class="input mt-1" placeholder="+63 912 345 6789">
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Address</label>
                    <input type="text" name="address" value="{{ old('address', auth()->user()->address) }}"
                           class="input mt-1">
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">City</label>
                    <input type="text" name="city" value="{{ old('city', auth()->user()->city) }}"
                           class="input mt-1">
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">ZIP Code</label>
                    <input type="text" name="zip" value="{{ old('zip', auth()->user()->zip) }}"
                           class="input mt-1">
                </div>
            </div>
        </div>

        {{-- ── Business information ─────────────────────────── --}}
        @if ($application)
        @php
            $remaining  = $application->shopNameChangesRemaining();
            $canChange  = $application->canChangeShopName();
            $resetsAt   = $application->shopNameResetsAt()->format('M d, Y');
        @endphp
        <div class="card p-6 space-y-4">
            <h2 class="font-display text-base font-bold" style="color:#222222;">Business Information</h2>
            <div class="grid gap-4 sm:grid-cols-2">

                {{-- Shop name — editable with change counter --}}
                <div class="sm:col-span-2">
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Shop Name</label>
                        @if ($canChange)
                            <span class="text-[11px] font-semibold rounded-full px-2 py-0.5"
                                  style="background:{{ $remaining === 1 ? '#FEF9C3' : '#ECFDF5' }};
                                         color:{{ $remaining === 1 ? '#CA8A04' : '#059669' }};">
                                {{ $remaining }} change{{ $remaining !== 1 ? 's' : '' }} left this month
                            </span>
                        @else
                            <span class="text-[11px] font-semibold rounded-full px-2 py-0.5"
                                  style="background:#FEF2F2;color:#DC2626;">
                                Locked until {{ $resetsAt }}
                            </span>
                        @endif
                    </div>
                    @if ($canChange)
                        <input type="text" name="shop_name"
                               value="{{ old('shop_name', $application->shop_name) }}"
                               class="input" maxlength="255">
                        @if ($remaining === 1)
                            <p class="mt-1 text-[11px]" style="color:#CA8A04;">
                                ⚠ This is your last change for this month.
                            </p>
                        @endif
                    @else
                        <input type="text" value="{{ $application->shop_name }}"
                               class="input" readonly style="background:#F9FAFB;cursor:not-allowed;">
                        <p class="mt-1 text-[11px]" style="color:#DC2626;">
                            You've used all 3 changes this month. Resets on {{ $resetsAt }}.
                        </p>
                    @endif
                </div>

                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Business Name</label>
                    <input type="text" value="{{ $application->business_name ?? '—' }}"
                           class="input mt-1" readonly style="background:#F9FAFB;">
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Line of Business</label>
                    <input type="text" value="{{ $application->line_of_business ?? '—' }}"
                           class="input mt-1" readonly style="background:#F9FAFB;">
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Application Status</label>
                    <div class="mt-1">
                        <span class="rounded-full px-3 py-1 text-xs font-bold"
                              style="background:{{ $application->isApproved() ? '#ECFDF5' : '#e8f0f6' }};
                                     color:{{ $application->isApproved() ? '#059669' : '#fa4e1c' }};">
                            {{ ucfirst($application->status) }}
                        </span>
                    </div>
                </div>
            </div>

            {{-- Uploaded documents --}}
            <div class="mt-2 grid gap-4 sm:grid-cols-2">
                @if ($application->government_id_path)
                    <div>
                        <p class="text-xs font-semibold mb-2" style="color:#6b90aa;">Valid ID</p>
                        @php $ext = strtolower(pathinfo($application->government_id_path, PATHINFO_EXTENSION)); @endphp
                        @if (in_array($ext, ['jpg','jpeg','png']))
                            <a href="{{ asset('storage/'.$application->government_id_path) }}" target="_blank">
                                <img src="{{ asset('storage/'.$application->government_id_path) }}"
                                     class="max-h-32 rounded-lg border object-contain transition hover:opacity-90"
                                     style="border-color:#cfdce8;">
                            </a>
                        @else
                            <a href="{{ asset('storage/'.$application->government_id_path) }}" target="_blank"
                               class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium"
                               style="border-color:#cfdce8;color:#fa4e1c;">
                                📄 View ID Document
                            </a>
                        @endif
                    </div>
                @endif
                @if ($application->business_permit_path)
                    <div>
                        <p class="text-xs font-semibold mb-2" style="color:#6b90aa;">Business Permit</p>
                        @php $ext2 = strtolower(pathinfo($application->business_permit_path, PATHINFO_EXTENSION)); @endphp
                        @if (in_array($ext2, ['jpg','jpeg','png']))
                            <a href="{{ asset('storage/'.$application->business_permit_path) }}" target="_blank">
                                <img src="{{ asset('storage/'.$application->business_permit_path) }}"
                                     class="max-h-32 rounded-lg border object-contain transition hover:opacity-90"
                                     style="border-color:#cfdce8;">
                            </a>
                        @else
                            <a href="{{ asset('storage/'.$application->business_permit_path) }}" target="_blank"
                               class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium"
                               style="border-color:#cfdce8;color:#fa4e1c;">
                                📄 View Business Permit
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
        @endif

        {{-- ── Change password ─────────────────────────────── --}}
        <div class="card p-6 space-y-4">
            <h2 class="font-display text-base font-bold" style="color:#222222;">Change Password</h2>
            <p class="text-xs" style="color:#6b90aa;">Leave blank to keep your current password.</p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">New Password</label>
                    <input type="password" name="password" class="input mt-1"
                           placeholder="Minimum 8 characters" minlength="8">
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Confirm New Password</label>
                    <input type="password" name="password_confirmation" class="input mt-1">
                </div>
            </div>
        </div>

        {{-- ── Actions ─────────────────────────────────────── --}}
        <div class="flex gap-3">
            <button type="submit"
                    class="flex-1 rounded-xl py-3 text-sm font-bold text-white transition hover:opacity-90"
                    style="background:#002b4d;">
                Save Changes
            </button>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit"
                        class="rounded-xl border px-6 py-3 text-sm font-semibold transition"
                        style="border-color:#EF4444;color:#EF4444;"
                        onmouseover="this.style.background='#FEF2F2';"
                        onmouseout="this.style.background='';">
                    Logout
                </button>
            </form>
        </div>
    </form>
</div>

<script>
function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];

    // Show filename
    document.getElementById('avatar-filename').textContent = file.name;

    // Live preview
    const reader = new FileReader();
    reader.onload = function (e) {
        // Remove initial letter span if present
        const initial = document.getElementById('avatar-initial');
        if (initial) initial.remove();

        // Update or create the img tag
        let img = document.getElementById('avatar-preview');
        if (!img) {
            img = document.createElement('img');
            img.id = 'avatar-preview';
            img.className = 'h-24 w-24 rounded-full object-cover';
            img.alt = 'Profile photo';
            input.closest('form').querySelector('.rounded-full').appendChild(img);
        }
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}
</script>
</x-seller-layout>
