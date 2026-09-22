<x-layout title="My Profile — ALVY">
@php $u = auth()->user(); @endphp
<div class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">

    <h1 class="font-display text-2xl font-bold mb-8" style="color:#002b4d;">My Account</h1>

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">

        @include('profile._sidebar')

        <div class="flex-1 space-y-5">

            {{-- Flash / errors --}}
            @if (session('success'))
                <div class="rounded-2xl border p-3.5 text-sm flex items-center gap-2"
                     style="background:#ECFDF5;border-color:rgba(5,150,105,.25);color:#059669;">
                    <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="rounded-2xl border p-3.5 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{-- Seller application status banner --}}
            @php $app = $u->sellerApplication; @endphp
            @if ($app)
                @if ($app->isPending())
                    <div class="rounded-2xl border p-4 text-sm" style="background:rgba(250,78,28,.06);border-color:rgba(250,78,28,.25);">
                        <strong style="color:#fa4e1c;">Seller application under review</strong>
                        <span style="color:#1a4d6e;"> — submitted {{ $app->created_at->diffForHumans() }}. We'll email you once it's reviewed.</span>
                    </div>
                @elseif ($app->isApproved())
                    <div class="rounded-2xl border p-4 text-sm flex items-center justify-between gap-3" style="background:#ECFDF5;border-color:rgba(5,150,105,.25);">
                        <strong style="color:#059669;">Your seller account is approved.</strong>
                        <a href="{{ route('seller.dashboard') }}" style="color:#059669;" class="shrink-0 underline">Seller Dashboard →</a>
                    </div>
                @elseif ($app->isRejected())
                    <div class="rounded-2xl border p-4 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);">
                        <strong style="color:#DC2626;">Seller application rejected.</strong>
                        @if ($app->rejection_reason)<span style="color:#d93d0e;"> Reason: {{ $app->rejection_reason }}</span>@endif
                        <div class="mt-3">
                            <a href="{{ route('seller.apply') }}"
                               class="inline-flex items-center gap-1.5 rounded-full px-5 py-2 text-sm font-semibold text-white transition"
                               style="background:#fa4e1c;"
                               onmouseover="this.style.background='#E14F00';" onmouseout="this.style.background='#fa4e1c';">
                                Re-apply
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                            </a>
                        </div>
                    </div>
                @endif
            @elseif (($u->role ?? 'buyer') === 'buyer')
                {{-- ═══════════ BECOME A SELLER (no application yet) ═══════════ --}}
                <div class="overflow-hidden rounded-2xl border" style="border-color:rgba(250,78,28,.25);">
                    <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between"
                         style="background:linear-gradient(135deg,rgba(250,78,28,.08),rgba(0,43,77,.06));">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl" style="background:#fa4e1c;">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                    <path d="M3 9l1-5h16l1 5M4 9h16v10a1 1 0 01-1 1H5a1 1 0 01-1-1V9zM9 13h6"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-display text-lg font-bold" style="color:#002b4d;">Start selling on ALVY</h3>
                                <p class="mt-0.5 text-sm" style="color:#54728a;">
                                    Turn your buyer account into a seller account and reach more customers. It only takes a few minutes to apply.
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('seller.apply') }}"
                           class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-full px-6 py-2.5 text-sm font-semibold text-white transition"
                           style="background:#fa4e1c;"
                           onmouseover="this.style.background='#E14F00';" onmouseout="this.style.background='#fa4e1c';">
                            Become a Seller
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                        </a>
                    </div>
                </div>
            @endif

            {{-- ═══════════ PROFILE HEADER CARD ═══════════ --}}
            <div class="rounded-2xl p-6 flex items-center gap-5" style="background:#fff;border:1px solid #eef2f6;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                <div class="relative shrink-0">
                    <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full"
                         style="background:#002b4d;">
                        @if ($u->profile_photo_path)
                            <img src="{{ asset('storage/'.$u->profile_photo_path) }}"
                                 class="h-20 w-20 rounded-full object-cover" alt="Profile photo">
                        @else
                            <span class="text-2xl font-extrabold text-white">{{ strtoupper(substr($u->name, 0, 1)) }}</span>
                        @endif
                    </div>
                </div>
                <div class="min-w-0">
                    <h2 class="font-display text-xl font-bold truncate" style="color:#002b4d;">{{ $u->name }}</h2>
                    <span class="mt-1 inline-block rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide"
                          style="background:rgba(250,78,28,.1);color:#fa4e1c;">{{ ucfirst($u->role ?? 'Buyer') }}</span>
                    <p class="mt-1.5 text-sm truncate" style="color:#6b90aa;">{{ $u->email }}</p>
                </div>
            </div>

            {{-- ═══════════ PERSONAL INFORMATION (read view) ═══════════ --}}
            <div id="info-view" class="rounded-2xl p-6 sm:p-7" style="background:#fff;border:1px solid #eef2f6;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="font-display text-lg font-bold" style="color:#002b4d;">Personal Information</h3>
                    <button type="button" onclick="toggleEdit(true)"
                            class="inline-flex items-center gap-1.5 rounded-lg px-4 py-1.5 text-sm font-semibold text-white transition"
                            style="background:#fa4e1c;"
                            onmouseover="this.style.background='#E14F00';" onmouseout="this.style.background='#fa4e1c';">
                        Edit
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    </button>
                </div>

                <dl class="grid gap-x-8 gap-y-6 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs mb-1" style="color:#9db3c4;">Full Name</dt>
                        <dd class="text-sm font-semibold" style="color:#002b4d;">{{ $u->name ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs mb-1" style="color:#9db3c4;">Gender</dt>
                        <dd class="text-sm font-semibold" style="color:#002b4d;">{{ $u->sex ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs mb-1" style="color:#9db3c4;">Date of Birth</dt>
                        <dd class="text-sm font-semibold" style="color:#002b4d;">{{ $u->birthday ? $u->birthday->format('M d, Y') : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs mb-1" style="color:#9db3c4;">Email Address</dt>
                        <dd class="text-sm font-semibold break-words" style="color:#002b4d;">{{ $u->email ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs mb-1" style="color:#9db3c4;">Phone Number</dt>
                        <dd class="text-sm font-semibold" style="color:#002b4d;">{{ $u->phone ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs mb-1" style="color:#9db3c4;">User Role</dt>
                        <dd class="text-sm font-semibold" style="color:#002b4d;">{{ ucfirst($u->role ?? 'Buyer') }}</dd>
                    </div>
                    <div class="sm:col-span-3">
                        <dt class="text-xs mb-1" style="color:#9db3c4;">Bio</dt>
                        <dd class="text-sm" style="color:#334e63;">{{ $u->bio ?: 'No bio added yet.' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- ═══════════ PERSONAL INFORMATION (edit form) ═══════════ --}}
            <form id="info-edit" action="{{ route('profile.update') }}" method="POST" enctype="multipart/form-data"
                  class="{{ $errors->any() ? '' : 'hidden' }} rounded-2xl p-6 sm:p-7 space-y-6"
                  style="background:#fff;border:1px solid #eef2f6;box-shadow:0 1px 3px rgba(0,0,0,.04);">
                @csrf @method('PATCH')

                <div class="flex items-center justify-between">
                    <h3 class="font-display text-lg font-bold" style="color:#002b4d;">Edit Profile</h3>
                    <button type="button" onclick="toggleEdit(false)"
                            class="rounded-lg border px-4 py-1.5 text-sm font-semibold transition"
                            style="border-color:#dce8f0;color:#6b90aa;"
                            onmouseover="this.style.background='#f5f8fb';" onmouseout="this.style.background='';">
                        Cancel
                    </button>
                </div>

                {{-- Profile picture --}}
                <div class="flex items-center gap-5">
                    <div class="relative shrink-0 cursor-pointer group" onclick="document.getElementById('avatar-input').click()">
                        <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full" style="background:#002b4d;">
                            @if ($u->profile_photo_path)
                                <img id="avatar-preview" src="{{ asset('storage/'.$u->profile_photo_path) }}" class="h-20 w-20 rounded-full object-cover" alt="Profile photo">
                            @else
                                <span id="avatar-initial" class="text-2xl font-extrabold text-white select-none" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;">{{ strtoupper(substr($u->name, 0, 1)) }}</span>
                                <img id="avatar-preview" src="" class="hidden h-20 w-20 rounded-full object-cover" alt="Profile photo">
                            @endif
                        </div>
                        <div class="absolute inset-0 flex items-center justify-center rounded-full opacity-0 group-hover:opacity-100 transition" style="background:rgba(0,0,0,.45);">
                            <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <input id="avatar-input" type="file" name="avatar" accept="image/jpg,image/jpeg,image/png,image/webp" class="hidden" onchange="previewProfileAvatar(this)">
                        <label for="avatar-input" class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-4 py-2 text-xs font-semibold transition"
                               style="border-color:#fa4e1c;color:#fa4e1c;"
                               onmouseover="this.style.background='#FFF6EE';" onmouseout="this.style.background='';">
                            Choose Photo
                        </label>
                        <p id="avatar-filename" class="mt-1.5 text-[11px]" style="color:#6b90aa;">JPG, PNG or WebP · max 2 MB</p>
                    </div>
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Full Name</label>
                        <input type="text" name="name" value="{{ old('name', $u->name) }}"
                               class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none" style="border-color:#cfdce8;color:#002b4d;"
                               onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';" required>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Bio</label>
                        <textarea name="bio" rows="3" maxlength="500"
                                  class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none resize-none" style="border-color:#cfdce8;color:#002b4d;"
                                  onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';"
                                  placeholder="Tell us a little about yourself…">{{ old('bio', $u->bio) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Gender</label>
                        <select name="sex" class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none" style="border-color:#cfdce8;color:#002b4d;"
                                onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';">
                            <option value="">Prefer not to say</option>
                            <option value="Male"   @selected(old('sex', $u->sex) === 'Male')>Male</option>
                            <option value="Female" @selected(old('sex', $u->sex) === 'Female')>Female</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Date of Birth</label>
                        <input type="date" name="birthday" value="{{ old('birthday', optional($u->birthday)->format('Y-m-d')) }}" max="{{ now()->toDateString() }}"
                               class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none" style="border-color:#cfdce8;color:#002b4d;"
                               onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Phone Number</label>
                        <input type="text" name="phone" value="{{ old('phone', $u->phone) }}"
                               class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none" style="border-color:#cfdce8;color:#002b4d;"
                               onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';" placeholder="+63 912 345 6789">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold mb-1" style="color:#6b90aa;">Email Address</label>
                        <input type="email" name="email" value="{{ old('email', $u->email) }}"
                               class="w-full rounded-xl border px-4 py-2.5 text-sm outline-none" style="border-color:#cfdce8;color:#002b4d;"
                               onfocus="this.style.borderColor='#fa4e1c';" onblur="this.style.borderColor='#cfdce8';" required>
                    </div>
                </div>

                <div class="flex gap-3 pt-1">
                    <button type="submit" class="rounded-full px-8 py-2.5 text-sm font-semibold text-white transition"
                            style="background:#fa4e1c;"
                            onmouseover="this.style.background='#E14F00';" onmouseout="this.style.background='#fa4e1c';">
                        Save Changes
                    </button>
                    <button type="button" onclick="toggleEdit(false)"
                            class="rounded-full border px-6 py-2.5 text-sm font-semibold transition"
                            style="border-color:#dce8f0;color:#6b90aa;"
                            onmouseover="this.style.background='#f5f8fb';" onmouseout="this.style.background='';">
                        Cancel
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
function toggleEdit(show) {
    document.getElementById('info-view').classList.toggle('hidden', show);
    document.getElementById('info-edit').classList.toggle('hidden', !show);
    if (show) document.getElementById('info-edit').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function previewProfileAvatar(input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    document.getElementById('avatar-filename').textContent = file.name;
    var reader = new FileReader();
    reader.onload = function (e) {
        var initial = document.getElementById('avatar-initial');
        if (initial) initial.style.display = 'none';
        var img = document.getElementById('avatar-preview');
        img.src = e.target.result;
        img.classList.remove('hidden');
    };
    reader.readAsDataURL(file);
}

// If there were validation errors, keep the edit form open and hide the read view
@if ($errors->any())
    document.getElementById('info-view').classList.add('hidden');
@endif
</script>
</x-layout>
