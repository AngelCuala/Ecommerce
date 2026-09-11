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

        {{-- Profile picture --}}
        <div class="card p-6 flex items-center gap-6">
            <div class="relative">
                <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full"
                     style="background:#002b4d;">
                    @if (auth()->user()->profile_photo_path)
                        <img src="{{ asset('storage/'.auth()->user()->profile_photo_path) }}"
                             class="h-20 w-20 rounded-full object-cover" alt="Avatar">
                    @else
                        <span class="text-3xl font-extrabold text-white">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="flex-1">
                <p class="font-bold text-base" style="color:#222222;">{{ auth()->user()->name }}</p>
                <p class="text-sm" style="color:#6b90aa;">{{ auth()->user()->email }}</p>
                <div class="mt-3">
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Change Profile Photo</label>
                    <input type="file" name="avatar" accept="image/*" class="input mt-1 py-1.5 text-xs">
                    <p class="text-[11px] mt-1" style="color:#B0B0B0;">JPG, PNG or WebP · max 2 MB</p>
                </div>
            </div>
        </div>

        {{-- Personal info --}}
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

        {{-- Business info --}}
        @if ($application)
        <div class="card p-6 space-y-4">
            <h2 class="font-display text-base font-bold" style="color:#222222;">Business Information</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Shop Name</label>
                    <input type="text" value="{{ $application->shop_name }}" class="input mt-1" readonly
                           style="background:#F9FAFB;">
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Business Name</label>
                    <input type="text" value="{{ $application->business_name ?? '—' }}" class="input mt-1" readonly
                           style="background:#F9FAFB;">
                </div>
                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Line of Business</label>
                    <input type="text" value="{{ $application->line_of_business ?? '—' }}" class="input mt-1" readonly
                           style="background:#F9FAFB;">
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
            <p class="text-xs" style="color:#6b90aa;">
                To update business information, please contact ALVY support.
            </p>

            {{-- Uploaded documents --}}
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                @if ($application->government_id_path)
                    <div>
                        <p class="text-xs font-semibold mb-2" style="color:#6b90aa;">Valid ID</p>
                        @php $ext = pathinfo($application->government_id_path, PATHINFO_EXTENSION); @endphp
                        @if (in_array(strtolower($ext), ['jpg','jpeg','png']))
                            <a href="{{ asset('storage/'.$application->government_id_path) }}" target="_blank">
                                <img src="{{ asset('storage/'.$application->government_id_path) }}"
                                     class="max-h-32 rounded-lg border object-contain" style="border-color:#cfdce8;">
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
                        @php $ext2 = pathinfo($application->business_permit_path, PATHINFO_EXTENSION); @endphp
                        @if (in_array(strtolower($ext2), ['jpg','jpeg','png']))
                            <a href="{{ asset('storage/'.$application->business_permit_path) }}" target="_blank">
                                <img src="{{ asset('storage/'.$application->business_permit_path) }}"
                                     class="max-h-32 rounded-lg border object-contain" style="border-color:#cfdce8;">
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

        {{-- Change password --}}
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

        <div class="flex gap-3">
            <button type="submit"
                    class="flex-1 rounded-xl py-3 text-sm font-bold text-white transition hover:opacity-90"
                    style="background:#002b4d;">Save Changes</button>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit"
                        class="rounded-xl border px-6 py-3 text-sm font-semibold transition"
                        style="border-color:#EF4444;color:#EF4444;"
                        onmouseover="this.style.background='#FEF2F2';"
                        onmouseout="this.style.background='';">
                    🚪 Logout
                </button>
            </form>
        </div>
    </form>
</div>
</x-seller-layout>
