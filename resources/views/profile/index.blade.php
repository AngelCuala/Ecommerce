<x-layout title="My Profile — ALVY">
@php $u = auth()->user(); @endphp
<div class="pf-page">
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">

    @include('profile._page-header', ['title' => 'My Profile', 'subtitle' => 'Manage your personal details and account.'])

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">

        @include('profile._sidebar')

        <div class="min-w-0 flex-1 space-y-5">

            {{-- Flash / errors --}}
            @if (session('success'))
                <div class="pf-alert pf-alert-success">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="pf-alert pf-alert-error">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{-- ═══════════ WELCOME + QUICK LINKS ═══════════ --}}
            <div class="pf-card overflow-hidden">
                <div class="relative p-6 sm:p-7" style="background:linear-gradient(120deg,#fff5f3 0%,#fff 60%);">
                    <p class="text-sm pf-muted">Welcome back,</p>
                    <h2 class="font-display text-2xl font-extrabold" style="color:#222;">{{ \Illuminate\Support\Str::of($u->name)->before(' ') }} 👋</h2>
                    <p class="mt-1 text-sm pf-muted">Here's a quick look at your account.</p>
                </div>
                <div class="grid grid-cols-2 gap-px border-t sm:grid-cols-4" style="border-color:#F0E4DC;background:#F0E4DC;">
                    @foreach ([
                        ['profile.orders',        'My Orders',     'Track & review',  '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>'],
                        ['profile.addresses',     'Addresses',     'Delivery spots',  '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>'],
                        ['profile.notifications', 'Notifications', 'Latest updates',  '<path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 01-3.4 0"/>'],
                        ['profile.security',      'Security',      'Password & login','<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'],
                    ] as [$r, $label, $hint, $icon])
                        <a href="{{ route($r) }}" class="group flex items-center gap-3 bg-white p-4 transition hover:bg-[#fff5f3]">
                            <span class="pf-icon transition group-hover:scale-105">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $icon !!}</svg>
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-bold" style="color:#222;">{{ $label }}</span>
                                <span class="block truncate text-xs pf-muted">{{ $hint }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Seller application status banner --}}
            @php $app = $u->sellerApplication; @endphp
            @if ($app)
                @if ($app->isPending())
                    <div class="pf-alert" style="background:#fff5f3;border-color:rgba(250,78,28,.25);">
                        <span class="pf-icon h-9 w-9">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
                        </span>
                        <div>
                            <strong style="color:#fa4e1c;">Seller application under review</strong>
                            <p class="pf-text">Submitted {{ $app->created_at->diffForHumans() }}. We'll email you once it's reviewed.</p>
                        </div>
                    </div>
                @elseif ($app->isApproved())
                    <div class="pf-alert pf-alert-success items-center justify-between">
                        <strong>Your seller account is approved.</strong>
                        <a href="{{ route('seller.dashboard') }}" class="shrink-0 font-semibold underline">Seller Dashboard →</a>
                    </div>
                @elseif ($app->isRejected())
                    <div class="pf-alert pf-alert-error flex-col">
                        <div>
                            <strong>Seller application rejected.</strong>
                            @if ($app->rejection_reason)<span style="color:#d93d0e;"> Reason: {{ $app->rejection_reason }}</span>@endif
                        </div>
                        <a href="{{ route('seller.apply') }}" class="pf-btn mt-1">
                            Re-apply
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                    </div>
                @endif
            @elseif (($u->role ?? 'buyer') === 'buyer')
                {{-- ═══════════ BECOME A SELLER (no application yet) ═══════════ --}}
                <div class="relative overflow-hidden rounded-[1.25rem] p-6 text-white sm:p-7"
                     style="background:linear-gradient(120deg,#002b4d 0%,#0b3d66 55%,#fa4e1c 140%);">
                    <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full" style="background:rgba(250,78,28,.35);filter:blur(30px);"></div>
                    <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl" style="background:#fa4e1c;">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M3 9l1-5h16l1 5M4 9h16v10a1 1 0 01-1 1H5a1 1 0 01-1-1V9zM9 13h6"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-display text-lg font-extrabold">Start selling on ALVY</h3>
                                <p class="mt-0.5 text-sm text-white/75">
                                    Turn your buyer account into a seller account and reach more customers. It only takes a few minutes to apply.
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('seller.apply') }}" class="pf-btn shrink-0">
                            Become a Seller
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                    </div>
                </div>
            @endif

            {{-- ═══════════ PERSONAL INFORMATION (read view) ═══════════ --}}
            <div id="info-view" class="pf-card p-6 sm:p-7">
                <div class="mb-6 flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="pf-icon">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                        </span>
                        <div>
                            <h3 class="pf-card-title">Personal Information</h3>
                            <p class="text-xs pf-muted">Your details are only visible to you.</p>
                        </div>
                    </div>
                    <button type="button" onclick="toggleEdit(true)" class="pf-btn-ghost">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Edit
                    </button>
                </div>

                <dl class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        ['Full Name',     $u->name],
                        ['Gender',        $u->sex],
                        ['Date of Birth', $u->birthday ? $u->birthday->format('M d, Y') : null],
                        ['Email Address', $u->email],
                        ['Phone Number',  $u->phone],
                        ['User Role',     ucfirst($u->role ?? 'Buyer')],
                    ] as [$label, $value])
                        <div class="rounded-xl p-3.5" style="background:#FDF8F5;border:1px solid #F5EBE4;">
                            <dt class="text-[11px] font-semibold uppercase tracking-wide pf-muted">{{ $label }}</dt>
                            <dd class="mt-1 break-words text-sm font-semibold" style="color:#222;">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                    <div class="rounded-xl p-3.5 sm:col-span-2 lg:col-span-3" style="background:#FDF8F5;border:1px solid #F5EBE4;">
                        <dt class="text-[11px] font-semibold uppercase tracking-wide pf-muted">Bio</dt>
                        <dd class="mt-1 text-sm pf-text">{{ $u->bio ?: 'No bio added yet.' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- ═══════════ PERSONAL INFORMATION (edit form) ═══════════ --}}
            <form id="info-edit" action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data"
                  class="{{ $errors->any() ? '' : 'hidden' }} pf-card space-y-6 p-6 sm:p-7">
                @csrf @method('PATCH')

                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="pf-icon">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </span>
                        <h3 class="pf-card-title">Edit Profile</h3>
                    </div>
                    <button type="button" onclick="toggleEdit(false)" class="pf-btn-ghost">Cancel</button>
                </div>

                {{-- Profile picture --}}
                <div class="flex items-center gap-5 rounded-xl p-4" style="background:#FDF8F5;border:1px solid #F5EBE4;">
                    <div class="group relative shrink-0 cursor-pointer" onclick="document.getElementById('avatar-input').click()">
                        <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-2xl" style="background:#002b4d;">
                            @if ($u->profile_photo_path)
                                <img id="avatar-preview" src="{{ asset('storage/'.$u->profile_photo_path) }}" class="h-20 w-20 object-cover" alt="Profile photo">
                            @else
                                <span id="avatar-initial" class="select-none text-2xl font-extrabold text-white" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;">{{ strtoupper(substr($u->name, 0, 1)) }}</span>
                                <img id="avatar-preview" src="" class="hidden h-20 w-20 object-cover" alt="Profile photo">
                            @endif
                        </div>
                        <div class="absolute inset-0 flex items-center justify-center rounded-2xl opacity-0 transition group-hover:opacity-100" style="background:rgba(0,0,0,.45);">
                            <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-bold" style="color:#222;">Profile photo</p>
                        <input id="avatar-input" type="file" name="avatar" accept="image/jpg,image/jpeg,image/png,image/webp" class="hidden" onchange="previewProfileAvatar(this)">
                        <label for="avatar-input" class="pf-btn-ghost mt-2 px-4 py-1.5 text-xs">Choose Photo</label>
                        <p id="avatar-filename" class="mt-1.5 text-[11px] pf-muted">JPG, PNG or WebP · max 2 MB</p>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="pf-label">Full Name</label>
                        <input type="text" name="name" value="{{ old('name', $u->name) }}" class="pf-input" required>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="pf-label">Bio</label>
                        <textarea name="bio" rows="3" maxlength="500" class="pf-input resize-none"
                                  placeholder="Tell us a little about yourself…">{{ old('bio', $u->bio) }}</textarea>
                    </div>
                    <div>
                        <label class="pf-label">Gender</label>
                        <select name="sex" class="pf-input">
                            <option value="">Prefer not to say</option>
                            <option value="Male"   @selected(old('sex', $u->sex) === 'Male')>Male</option>
                            <option value="Female" @selected(old('sex', $u->sex) === 'Female')>Female</option>
                        </select>
                    </div>
                    <div>
                        <label class="pf-label">Date of Birth</label>
                        <input type="date" name="birthday" value="{{ old('birthday', optional($u->birthday)->format('Y-m-d')) }}" max="{{ now()->toDateString() }}" class="pf-input">
                    </div>
                    <div>
                        <label class="pf-label">Phone Number</label>
                        <input type="text" name="phone" value="{{ old('phone', $u->phone) }}" class="pf-input" placeholder="+63 912 345 6789">
                    </div>
                    <div>
                        <label class="pf-label">Email Address</label>
                        <input type="email" name="email" value="{{ old('email', $u->email) }}" class="pf-input" required>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 border-t pt-5" style="border-color:#F0E4DC;">
                    <button type="submit" class="pf-btn px-8">Save Changes</button>
                    <button type="button" onclick="toggleEdit(false)" class="pf-btn-ghost">Cancel</button>
                </div>
            </form>

        </div>
    </div>
</div>
</div>

<script src="{{ asset('js/profile.js') }}"></script>
@if ($errors->any())
    <script>
        // Validation errors present — keep the edit form open and hide the read view
        document.getElementById('info-view').classList.add('hidden');
        document.getElementById('info-edit').classList.remove('hidden');
    </script>
@endif
</x-layout>
