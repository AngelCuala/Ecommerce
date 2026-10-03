<x-seller-layout title="Security" active="account">
<div>

    {{-- Header + back to account --}}
    <div class="mb-2 flex items-center gap-3">
        <a href="{{ route('seller.account') }}"
           class="inline-flex items-center gap-1.5 text-sm font-semibold transition" style="color:#fa4e1c;"
           onmouseover="this.style.color='#d93d0e';" onmouseout="this.style.color='#fa4e1c';">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back to Account
        </a>
    </div>
    <div class="mb-6">
        <span class="section-eyebrow" style="color:#fa4e1c;">Seller Panel</span>
        <h1 class="mt-1 font-display text-2xl font-bold" style="color:#222222;">Security Settings</h1>
        <p class="mt-1 text-sm" style="color:#6b90aa;">Manage your password and authentication settings.</p>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-xl border p-4 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="space-y-6">

        {{-- ── Change password ─────────────────────────────── --}}
        <div class="card p-6">
            <h2 class="font-display text-base font-bold" style="color:#222222;">Change Password</h2>
            <p class="mt-1 text-xs" style="color:#6b90aa;">
                Choose a strong password you don't use elsewhere. Minimum 8 characters.
            </p>

            <form action="{{ route('seller.account.password') }}" method="POST" class="mt-5 space-y-4">
                @csrf @method('PUT')

                <div>
                    <label class="text-xs font-semibold" style="color:#6b90aa;">Current Password</label>
                    <div class="relative mt-1">
                        <input type="password" name="current_password" data-password class="input pr-11" placeholder="••••••••" required autocomplete="current-password">
                        <button type="button" data-toggle-password aria-label="Show password"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-lg leading-none" style="color:#6b90aa;">👁️</button>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">New Password</label>
                        <div class="relative mt-1">
                            <input type="password" name="password" data-password class="input pr-11" placeholder="Minimum 8 characters" minlength="8" required autocomplete="new-password">
                            <button type="button" data-toggle-password aria-label="Show password"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-lg leading-none" style="color:#6b90aa;">👁️</button>
                        </div>
                    </div>
                    <div>
                        <label class="text-xs font-semibold" style="color:#6b90aa;">Confirm New Password</label>
                        <div class="relative mt-1">
                            <input type="password" name="password_confirmation" data-password class="input pr-11" placeholder="••••••••" minlength="8" required autocomplete="new-password">
                            <button type="button" data-toggle-password aria-label="Show password"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-lg leading-none" style="color:#6b90aa;">👁️</button>
                        </div>
                    </div>
                </div>

                <div class="pt-1">
                    <button type="submit" class="rounded-xl px-8 py-2.5 text-sm font-bold text-white transition hover:opacity-90" style="background:#002b4d;">
                        Update Password
                    </button>
                </div>
            </form>
        </div>

        {{-- ── Two-factor authentication (not yet available) ── --}}
        <div class="card p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="font-display text-base font-bold" style="color:#222222;">Two-Factor Authentication</h2>
                    <p class="mt-1 text-sm" style="color:#6b90aa;">
                        Add an extra layer of security by requiring a verification code at sign-in.
                    </p>
                </div>
                <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" style="background:#F3F4F6;color:#6b7280;">
                    Coming soon
                </span>
            </div>
            <button type="button" disabled
                    class="mt-4 cursor-not-allowed rounded-xl border px-5 py-2.5 text-sm font-semibold"
                    style="border-color:#E5E7EB;color:#B0B0B0;background:#F9FAFB;">
                Enable 2FA
            </button>
        </div>

        {{-- ── Active sessions (not yet available) ─────────── --}}
        <div class="card p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <h2 class="font-display text-base font-bold" style="color:#222222;">Active Sessions</h2>
                    <p class="mt-1 text-sm" style="color:#6b90aa;">
                        Review and sign out of devices where your account is currently logged in.
                    </p>
                </div>
                <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" style="background:#F3F4F6;color:#6b7280;">
                    Coming soon
                </span>
            </div>
            <div class="mt-4 flex items-center gap-3 rounded-xl border p-4" style="border-color:#E5E7EB;background:#F9FAFB;">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg" style="background:#F3F4F6;">
                    <svg class="h-4 w-4" fill="none" stroke="#6b7280" stroke-width="1.8" viewBox="0 0 24 24"><rect x="2" y="4" width="20" height="14" rx="2"/><path d="M8 20h8M12 18v2"/></svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold" style="color:#222222;">This device</p>
                    <p class="text-xs" style="color:#6b90aa;">Current session · signed in now</p>
                </div>
            </div>
        </div>

    </div>
</div>

@include('partials.password-toggle')
</x-seller-layout>
