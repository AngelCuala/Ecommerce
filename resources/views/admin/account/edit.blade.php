<x-admin-layout title="Profile" active="account">

<div class="mx-auto max-w-3xl space-y-6">

    <div>
        <h1 class="font-display text-2xl font-bold" style="color:#222222;">My Profile</h1>
        <p class="mt-1 text-sm" style="color:#6b90aa;">Your personal and account information.</p>
    </div>

    @if (session('success'))
        <div class="rounded-xl border p-4 text-sm flex items-center gap-2"
             style="background:#ECFDF5;border-color:rgba(5,150,105,.25);color:#059669;">
            <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
            {{ session('success') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="rounded-xl border p-4 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    @php
        $statusVal   = $user->status;
        $statusMap   = [
            'approved'  => ['label' => 'Active',      'bg' => '#ECFDF5', 'fg' => '#059669'],
            'active'    => ['label' => 'Active',      'bg' => '#ECFDF5', 'fg' => '#059669'],
            'pending'   => ['label' => 'Pending',     'bg' => '#FEF9C3', 'fg' => '#CA8A04'],
            'suspended' => ['label' => 'Suspended',   'bg' => '#FEF2F2', 'fg' => '#DC2626'],
            'rejected'  => ['label' => 'Rejected',    'bg' => '#FEF2F2', 'fg' => '#DC2626'],
        ];
        $statusInfo  = $statusMap[$statusVal] ?? ['label' => ucfirst($statusVal), 'bg' => '#e8f0f6', 'fg' => '#6b90aa'];
    @endphp

    {{-- ═══════════ IDENTITY HEADER ═══════════ --}}
    <div class="card p-6">
        <div class="flex flex-col items-center gap-5 sm:flex-row sm:items-center">
            <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-full border-2"
                 style="border-color:#fa4e1c;">
                @if ($user->profile_photo_path)
                    <img src="{{ asset('storage/'.$user->profile_photo_path) }}"
                         class="h-24 w-24 rounded-full object-cover" alt="Profile photo">
                @else
                    <span class="text-3xl font-extrabold text-white"
                          style="background:#002b4d;width:100%;height:100%;display:flex;align-items:center;justify-content:center;">
                        {{ strtoupper(substr($user->name, 0, 1)) }}
                    </span>
                @endif
            </div>
            <div class="min-w-0 text-center sm:text-left">
                <h2 class="font-display text-xl font-bold" style="color:#222222;">{{ $user->full_name ?: $user->name }}</h2>
                <p class="mt-0.5 text-sm" style="color:#6b90aa;">{{ '@'.($user->username ?? 'admin') }}</p>
                <div class="mt-2 flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                    <span class="rounded-full px-3 py-1 text-xs font-bold"
                          style="background:rgba(220,38,38,.12);color:#DC2626;">{{ ucfirst($user->role) }}</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold"
                          style="background:{{ $statusInfo['bg'] }};color:{{ $statusInfo['fg'] }};">
                        <span class="h-1.5 w-1.5 rounded-full" style="background:{{ $statusInfo['fg'] }};"></span>{{ $statusInfo['label'] }}
                    </span>
                </div>
            </div>
            <div class="sm:ml-auto">
                <a href="{{ route('admin.account.security') }}"
                   class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-bold text-white transition hover:opacity-90"
                   style="background:#002b4d;">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    Security Settings
                </a>
            </div>
        </div>
    </div>

    {{-- ═══════════ ACCOUNT INFORMATION (read view) ═══════════ --}}
    <div id="info-view" class="card p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="font-display text-base font-bold" style="color:#222222;">Account Information</h3>
            <button type="button" onclick="toggleEdit(true)"
                    class="inline-flex items-center gap-1.5 rounded-lg px-4 py-1.5 text-sm font-semibold text-white transition"
                    style="background:#fa4e1c;"
                    onmouseover="this.style.background='#E14F00';" onmouseout="this.style.background='#fa4e1c';">
                Edit
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7M18.5 2.5a2.12 2.12 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
        </div>

        <dl class="grid gap-x-8 gap-y-6 sm:grid-cols-2">
            <div>
                <dt class="text-xs mb-1" style="color:#9db3c4;">Full Name</dt>
                <dd class="text-sm font-semibold" style="color:#222222;">{{ $user->full_name ?: $user->name ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs mb-1" style="color:#9db3c4;">Username</dt>
                <dd class="text-sm font-semibold" style="color:#222222;">{{ $user->username ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs mb-1" style="color:#9db3c4;">Email Address</dt>
                <dd class="text-sm font-semibold" style="color:#222222;">{{ $maskedEmail }}</dd>
            </div>
            <div>
                <dt class="text-xs mb-1" style="color:#9db3c4;">Role</dt>
                <dd class="text-sm font-semibold" style="color:#222222;">{{ ucfirst($user->role) }}</dd>
            </div>
            <div>
                <dt class="text-xs mb-1" style="color:#9db3c4;">Account Status</dt>
                <dd class="text-sm font-semibold" style="color:{{ $statusInfo['fg'] }};">{{ $statusInfo['label'] }}</dd>
            </div>
            <div>
                <dt class="text-xs mb-1" style="color:#9db3c4;">Date Joined</dt>
                <dd class="text-sm font-semibold" style="color:#222222;">{{ $user->created_at?->format('M d, Y') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs mb-1" style="color:#9db3c4;">Last Login</dt>
                <dd class="text-sm font-semibold" style="color:#222222;">
                    {{ $lastLogin ? \Illuminate\Support\Carbon::parse($lastLogin)->format('M d, Y · g:i A') : 'First login' }}
                </dd>
            </div>
        </dl>
    </div>

    {{-- ═══════════ ACCOUNT INFORMATION (edit form) ═══════════ --}}
    <form id="info-edit" action="{{ route('admin.account.update') }}" method="POST" enctype="multipart/form-data"
          class="{{ $errors->any() ? '' : 'hidden' }} card p-6 space-y-5">
        @csrf @method('PUT')

        <div class="flex items-center justify-between">
            <h3 class="font-display text-base font-bold" style="color:#222222;">Edit Profile</h3>
            <button type="button" onclick="toggleEdit(false)"
                    class="rounded-lg border px-4 py-1.5 text-sm font-semibold transition"
                    style="border-color:#dce8f0;color:#6b90aa;"
                    onmouseover="this.style.background='#f5f8fb';" onmouseout="this.style.background='';">
                Cancel
            </button>
        </div>

        {{-- Avatar --}}
        <div class="flex items-center gap-5">
            <div class="relative shrink-0 cursor-pointer group" onclick="document.getElementById('avatar-input').click()">
                <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full border-2" style="border-color:#fa4e1c;">
                    @if ($user->profile_photo_path)
                        <img id="avatar-preview" src="{{ asset('storage/'.$user->profile_photo_path) }}" class="h-20 w-20 rounded-full object-cover" alt="Profile photo">
                    @else
                        <span id="avatar-initial" class="text-2xl font-extrabold text-white select-none" style="background:#002b4d;width:100%;height:100%;display:flex;align-items:center;justify-content:center;">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                    @endif
                </div>
                <div class="absolute inset-0 flex items-center justify-center rounded-full opacity-0 group-hover:opacity-100 transition" style="background:rgba(0,0,0,.45);">
                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg>
                </div>
            </div>
            <div class="flex-1">
                <input id="avatar-input" type="file" name="avatar" accept="image/jpg,image/jpeg,image/png,image/webp" class="hidden" onchange="previewAvatar(this)">
                <label for="avatar-input" class="inline-flex cursor-pointer items-center gap-2 rounded-lg border px-4 py-2 text-xs font-semibold transition"
                       style="border-color:#fa4e1c;color:#fa4e1c;"
                       onmouseover="this.style.background='#fff1ee';" onmouseout="this.style.background='';">
                    Choose Photo
                </label>
                <p id="avatar-filename" class="mt-1.5 text-[11px]" style="color:#B0B0B0;">JPG, PNG or WebP · max 2 MB</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Full Name *</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" class="input mt-1" required>
            </div>
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Username</label>
                <input type="text" name="username" value="{{ old('username', $user->username) }}" class="input mt-1" placeholder="admin">
            </div>
            <div class="sm:col-span-2">
                <label class="text-xs font-semibold" style="color:#6b90aa;">Email Address *</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" class="input mt-1" required>
                <p class="mt-1 text-[11px]" style="color:#9db3c4;">Your email is masked on the profile view for privacy.</p>
            </div>
        </div>

        <div class="flex gap-3 pt-1">
            <button type="submit" class="rounded-xl px-8 py-2.5 text-sm font-bold text-white transition hover:opacity-90" style="background:#002b4d;">
                Save Changes
            </button>
            <button type="button" onclick="toggleEdit(false)"
                    class="rounded-xl border px-6 py-2.5 text-sm font-semibold transition"
                    style="border-color:#dce8f0;color:#6b90aa;"
                    onmouseover="this.style.background='#f5f8fb';" onmouseout="this.style.background='';">
                Cancel
            </button>
        </div>
    </form>

</div>

<script>
function toggleEdit(show) {
    document.getElementById('info-view').classList.toggle('hidden', show);
    document.getElementById('info-edit').classList.toggle('hidden', !show);
    if (show) document.getElementById('info-edit').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    document.getElementById('avatar-filename').textContent = file.name;
    var reader = new FileReader();
    reader.onload = function (e) {
        var initial = document.getElementById('avatar-initial');
        if (initial) initial.remove();
        var img = document.getElementById('avatar-preview');
        if (!img) {
            img = document.createElement('img');
            img.id = 'avatar-preview';
            img.className = 'h-20 w-20 rounded-full object-cover';
            img.alt = 'Profile photo';
            input.closest('form').querySelector('.rounded-full').appendChild(img);
        }
        img.src = e.target.result;
    };
    reader.readAsDataURL(file);
}
</script>
</x-admin-layout>
