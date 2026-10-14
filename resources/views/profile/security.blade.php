<x-layout title="Accounts & Security — ALVY">
<div class="pf-page">
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">

    @include('profile._page-header', ['title' => 'Accounts & Security', 'subtitle' => 'Manage your login details and keep your account secure.'])

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
            @if (session('error'))
                <div class="pf-alert pf-alert-error">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="pf-alert pf-alert-error">
                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <ul class="list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                    </ul>
                </div>
            @endif

            {{-- ═══════════ ACCOUNT DETAILS ═══════════ --}}
            <div class="pf-card p-6 sm:p-7">
                <div class="mb-6 flex items-center gap-3">
                    <span class="pf-icon">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                    </span>
                    <div>
                        <h3 class="pf-card-title">Account Details</h3>
                        <p class="text-xs pf-muted">Your username, and your email and phone (changed with a verification code).</p>
                    </div>
                </div>

                <form action="{{ route('profile.account.update') }}" method="POST" class="flex flex-wrap items-end gap-3">
                    @csrf @method('PATCH')
                    <div class="min-w-[14rem] flex-1">
                        <label class="pf-label">Username</label>
                        <input type="text" name="username" value="{{ old('username', $user->username) }}" class="pf-input" placeholder="your_username">
                        <p class="mt-1.5 text-[11px] pf-muted">Letters, numbers, dashes and underscores only.</p>
                    </div>
                    <button type="submit" class="pf-btn mb-6 px-6">Save</button>
                </form>

                <div class="mt-2 grid gap-4 border-t pt-5 sm:grid-cols-2" style="border-color:#F0E4DC;">
                    {{-- Email --}}
                    <form action="{{ route('profile.email.change') }}" method="POST">
                        @csrf
                        <label class="pf-label">Email address</label>
                        <p class="mb-2 text-sm" style="color:#222;">{{ $user->email }}</p>
                        <div class="flex gap-2">
                            <input type="email" name="email" value="{{ old('email') }}" class="pf-input" placeholder="New email address" required>
                            <button class="pf-btn shrink-0 px-4">Change</button>
                        </div>
                        <p class="mt-1.5 text-[11px] pf-muted">We send a code to the new address. Your current email keeps working until you enter it.</p>
                    </form>

                    {{-- Phone --}}
                    <form action="{{ route('profile.phone.change') }}" method="POST">
                        @csrf
                        <label class="pf-label">Phone number</label>
                        <p class="mb-2 text-sm" style="color:#222;">{{ $user->phone ?: 'Not set' }}</p>
                        <div class="flex gap-2">
                            <input type="tel" name="phone" value="{{ old('phone') }}" class="pf-input" placeholder="09XXXXXXXXX" required>
                            <button class="pf-btn shrink-0 px-4">Change</button>
                        </div>
                        <p class="mt-1.5 text-[11px] pf-muted">We send a code to your email to confirm the new number.</p>
                    </form>
                </div>

                {{-- Waiting for the code --}}
                @if ($user->pending_email || $user->pending_phone)
                    <div class="mt-5 rounded-xl p-4" style="background:#FFF8F4;border:1px solid #FDD5C8;">
                        <p class="text-sm" style="color:#9A3412;">
                            Confirm your new {{ $user->pending_email ? 'email address' : 'phone number' }}:
                            <strong>{{ $user->pending_email ?: $user->pending_phone }}</strong>.
                            Enter the 6-digit code we sent to {{ $user->pending_email ?: $user->email }}.
                        </p>
                        <form action="{{ route('profile.contact.verify') }}" method="POST" class="mt-3 flex flex-wrap gap-2">
                            @csrf
                            <input type="text" name="code" inputmode="numeric" maxlength="6" pattern="\d{6}" class="pf-input w-40 text-center tracking-[.4em]" placeholder="••••••" required autocomplete="one-time-code">
                            <button class="pf-btn px-5">Confirm</button>
                        </form>
                        <div class="mt-2 flex gap-4 text-xs">
                            <form action="{{ route('profile.contact.resend') }}" method="POST">@csrf<button class="font-semibold" style="color:#fa4e1c;">Send a new code</button></form>
                            <form action="{{ route('profile.contact.cancel') }}" method="POST">@csrf<button class="font-semibold pf-muted">Cancel the change</button></form>
                        </div>
                    </div>
                @endif
            </div>

            {{-- ═══════════ CHANGE PASSWORD ═══════════ --}}
            <div class="pf-card p-6 sm:p-7">
                <div class="mb-6 flex items-center gap-3">
                    <span class="pf-icon">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <div>
                        <h3 class="pf-card-title">Change Password</h3>
                        <p class="text-xs pf-muted">Keep your account secure with a strong password.</p>
                    </div>
                </div>

                <form action="{{ route('profile.change-password') }}" method="POST" class="grid gap-4 sm:grid-cols-2">
                    @csrf @method('PATCH')

                    <div class="sm:col-span-2">
                        <label class="pf-label">Current Password</label>
                        <div class="relative">
                            <input type="password" name="current_password" data-password
                                   class="pf-input pr-11" required>
                            <button type="button" data-toggle-password aria-label="Show password"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-lg leading-none" style="color:#8a7a70;">👁️</button>
                        </div>
                    </div>

                    <div>
                        <label class="pf-label">New Password</label>
                        <div class="relative">
                            <input type="password" name="password" data-password minlength="8"
                                   class="pf-input pr-11" required>
                            <button type="button" data-toggle-password aria-label="Show password"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-lg leading-none" style="color:#8a7a70;">👁️</button>
                        </div>
                    </div>

                    <div>
                        <label class="pf-label">Confirm New Password</label>
                        <div class="relative">
                            <input type="password" name="password_confirmation" data-password
                                   class="pf-input pr-11" required>
                            <button type="button" data-toggle-password aria-label="Show password"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-lg leading-none" style="color:#8a7a70;">👁️</button>
                        </div>
                    </div>

                    <div class="flex items-start gap-2.5 rounded-xl p-3.5 sm:col-span-2" style="background:#FDF8F5;border:1px solid #F5EBE4;">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" style="color:#fa4e1c;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <p class="text-xs pf-text">At least 8 characters with an uppercase letter, a lowercase letter, a number and a symbol (e.g. Mamimili@2027).</p>
                    </div>

                    <div class="flex flex-wrap gap-3 border-t pt-5 sm:col-span-2" style="border-color:#F0E4DC;">
                        <button type="submit" class="pf-btn px-8">Update Password</button>
                    </div>
                </form>
            </div>

        </div>
    </div>
</div>
</div>

@include('partials.password-toggle')
</x-layout>
