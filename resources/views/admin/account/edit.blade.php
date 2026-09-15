<x-admin-layout title="Account" active="account">

<div class="mx-auto max-w-2xl space-y-6">

    @if ($errors->any())
        <div class="rounded-xl border p-4 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    {{-- System Accounts --}}
    <div class="card p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-display text-base font-bold" style="color:#222222;">System Accounts</h2>
        </div>
        <table class="w-full text-sm">
            <thead style="background:#e8f0f6;">
                <tr>
                    <th class="px-4 py-2.5 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Name / Email</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Role</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Status</th>
                    <th class="px-4 py-2.5 text-left text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Created</th>
                    <th class="px-4 py-2.5 text-right text-[11px] font-bold uppercase tracking-widest" style="color:#6b90aa;">Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach(\App\Models\User::whereIn('role', ['admin','sorting_center'])->latest()->get() as $u)
                    @php
                        $roleColor = match($u->role) {
                            'admin'          => '#DC2626',
                            'sorting_center' => '#2563EB',
                            default          => '#fa4e1c',
                        };
                        $isActive = !in_array($u->role, ['suspended','deactivated']);
                    @endphp
                    <tr style="border-top:1px solid #dce8f0;"
                        onmouseover="this.style.background='#e8f0f6';" onmouseout="this.style.background='';">
                        <td class="px-4 py-3">
                            <div class="font-semibold" style="color:#222222;">{{ $u->name }}</div>
                            <div class="text-xs" style="color:#6b90aa;">{{ $u->email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold"
                                  style="background:{{ $roleColor }}22;color:{{ $roleColor }};">
                                {{ ucfirst(str_replace('_', ' ', $u->role)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($isActive)
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                      style="background:#ECFDF5;color:#059669;">
                                    <span class="h-1.5 w-1.5 rounded-full" style="background:#059669;"></span>Active
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold"
                                      style="background:#FEF2F2;color:#DC2626;">
                                    <span class="h-1.5 w-1.5 rounded-full" style="background:#DC2626;"></span>Inactive
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs" style="color:#6b90aa;">{{ $u->created_at->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($u->id !== auth()->id())
                                @if ($isActive)
                                    <form method="POST" action="{{ route('admin.users.suspend', $u->id) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <button class="rounded-full border px-3 py-1 text-xs font-semibold transition"
                                                style="border-color:#DC2626;color:#DC2626;"
                                                onmouseover="this.style.background='#FEF2F2';"
                                                onmouseout="this.style.background='';">
                                            Suspend
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('admin.users.activate', $u->id) }}" class="inline">
                                        @csrf @method('PATCH')
                                        <button class="rounded-full border px-3 py-1 text-xs font-semibold transition"
                                                style="border-color:#059669;color:#059669;"
                                                onmouseover="this.style.background='#ECFDF5';"
                                                onmouseout="this.style.background='';">
                                            Activate
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- My Profile --}}
    <div class="card p-6">
        <h2 class="font-display text-base font-bold mb-5" style="color:#222222;">My Profile</h2>

        {{-- Avatar --}}
        <div class="flex items-center gap-4 mb-6">
            <div class="flex h-12 w-12 items-center justify-center rounded-full font-bold text-lg"
                 style="background:#fa4e1c;color:#fff;">
                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
            </div>
            <div>
                <p class="font-semibold" style="color:#222222;">{{ auth()->user()->name }}</p>
                <p class="text-sm" style="color:#6b90aa;">{{ auth()->user()->email }}</p>
                <span class="mt-1 inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold"
                      style="background:rgba(220,38,38,.12);color:#DC2626;">
                    {{ ucfirst(auth()->user()->role) }}
                </span>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.account.update') }}" class="space-y-4">
            @csrf @method('PUT')
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Full Name</label>
                <input type="text" name="name" value="{{ old('name', auth()->user()->name) }}"
                       class="input mt-1" required>
            </div>
            <div>
                <label class="text-xs font-semibold" style="color:#6b90aa;">Email Address</label>
                <input type="email" name="email" value="{{ old('email', auth()->user()->email) }}"
                       class="input mt-1" required>
            </div>

            <div class="border-t pt-4" style="border-color:#dce8f0;">
                <p class="text-xs font-bold uppercase tracking-widest mb-3" style="color:#6b90aa;">Change Password</p>
                <div class="space-y-4">
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Current Password</label>
                        <input type="password" name="current_password" class="input mt-1" placeholder="••••••••">
                    </div>
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">New Password</label>
                        <input type="password" name="password" class="input mt-1" placeholder="••••••••">
                    </div>
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Confirm New Password</label>
                        <input type="password" name="password_confirmation" class="input mt-1" placeholder="••••••••">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-gold w-full"
                    style="background:#fa4e1c;border-color:#fa4e1c;color:#FFFFFF;">
                Save Changes
            </button>
        </form>
    </div>

</div>

</x-admin-layout>
