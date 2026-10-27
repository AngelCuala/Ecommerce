<x-layout title="Accounts & Security — ALVY">
<div class="pf-page">
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">

    @include('profile._page-header', ['title' => 'Accounts & Security', 'subtitle' => 'Manage your login details and keep your account secure.'])

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">

        @include('profile._sidebar')

        <div class="min-w-0 flex-1 space-y-5">

            {{-- Success and error messages show as the pop-up (components/layout) --}}
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
                    <form action="{{ route('profile.email.change') }}" method="POST" id="account-email" style="scroll-margin-top:90px;">
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
                            <input type="tel" name="phone" value="{{ old('phone') }}" class="pf-input" placeholder="09XXXXXXXXX" required maxlength="13" inputmode="tel" autocomplete="tel" data-mobile oninput="this.value=this.value.startsWith('+')?('+'+this.value.slice(1).replace(/\D/g,'')).slice(0,13):this.value.replace(/\D/g,'').slice(0,11)">
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

            {{-- ═══════════ CHANGE PASSWORD (confirmed with an emailed code) ═══════════ --}}
            @php
                $pwCodes = app(\App\Services\PasswordChangeCode::class);
                $pwPending = $pwCodes->pending();
                $pwWait = $pwPending ? $pwCodes->cooldown() : 0;
                $pwLeft = $pwPending ? max(0, $pwPending['expires_at'] - now()->timestamp) : 0;
            @endphp
            <div class="pf-card p-6 sm:p-7" id="change-password" style="scroll-margin-top:90px;">
                <div class="mb-6 flex items-center gap-3">
                    <span class="pf-icon">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 class="pf-card-title">Change Password</h3>
                        <p class="text-xs pf-muted">{{ $pwPending ? 'Step 2 of 2: confirm it\'s you.' : 'Step 1 of 2: choose a new password. We\'ll email you a code to confirm it.' }}</p>
                    </div>
                    <div class="hidden items-center gap-1.5 sm:flex" aria-hidden="true">
                        <span class="h-1.5 w-8 rounded-full" style="background:#fa4e1c;"></span>
                        <span class="h-1.5 w-8 rounded-full" style="background:{{ $pwPending ? '#fa4e1c' : '#F0E4DC' }};"></span>
                    </div>
                </div>

                @if ($pwPending)
                    {{-- Step 2: the code from the email --}}
                    <div data-password-code-step>
                        <div class="flex items-start gap-3 rounded-xl p-4" style="background:#FFF7F3;border:1px solid #FBD5C5;">
                            <svg class="mt-0.5 h-5 w-5 shrink-0" style="color:#fa4e1c;" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 6 9-6"/></svg>
                            <div class="text-sm" style="color:#57534E;">
                                <p class="font-semibold" style="color:#222;">Check your email</p>
                                <p>We sent a 6-digit code to <strong>{{ \App\Services\LoginOtp::maskEmail($user->email) }}</strong>. Your password stays the same until you enter it.
                                    @if ($pwLeft > 0) The code expires in <span data-pw-expiry="{{ $pwLeft }}">{{ ceil($pwLeft / 60) }} {{ \Illuminate\Support\Str::plural('minute', (int) ceil($pwLeft / 60)) }}</span>.@else <strong style="color:#B91C1C;">The code has expired. Send a new one.</strong>@endif
                                </p>
                            </div>
                        </div>

                        <form action="{{ route('profile.password.confirm') }}" method="POST" class="mt-5" data-code-form>
                            @csrf
                            <label class="pf-label" for="pw-code">Verification code</label>
                            <div class="flex flex-wrap items-center gap-3">
                                <input type="text" id="pw-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" required autofocus
                                       class="pf-input text-center font-mono text-2xl font-bold tracking-[.5em]" style="max-width:15rem;" placeholder="••••••"
                                       oninput="this.value=this.value.replace(/\D/g,'').slice(0,6)" @error('code') aria-invalid="true" @enderror>
                                <button type="submit" class="pf-btn px-7">Confirm new password</button>
                            </div>
                            @error('code')<p class="mt-2 text-xs font-medium" style="color:#B91C1C;">{{ $message }}</p>@enderror
                        </form>

                        <div class="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 border-t pt-4 text-sm" style="border-color:#F0E4DC;">
                            <form action="{{ route('profile.password.resend') }}" method="POST">@csrf
                                <button class="font-semibold disabled:cursor-not-allowed disabled:opacity-50" style="color:#fa4e1c;" data-resend data-wait="{{ $pwWait }}" @disabled($pwWait > 0)>
                                    Send a new code<span data-resend-timer>{{ $pwWait > 0 ? ' (' . $pwWait . 's)' : '' }}</span>
                                </button>
                            </form>
                            <span class="pf-muted" aria-hidden="true">·</span>
                            <form action="{{ route('profile.password.cancel') }}" method="POST">@csrf<button class="font-semibold pf-muted hover:underline">Cancel the change</button></form>
                            <p class="w-full text-xs pf-muted">Didn't get it? Check your spam folder. Wrong email? <a href="#account-email" class="font-semibold" style="color:#fa4e1c;">Update your email</a> first.</p>
                        </div>
                    </div>
                @else
                    {{-- Step 1: current and new password --}}
                    <form action="{{ route('profile.change-password') }}" method="POST" class="grid gap-4 sm:grid-cols-2" id="pw-form">
                        @csrf @method('PATCH')

                        <div class="sm:col-span-2">
                            <label class="pf-label" for="current_password">Current Password</label>
                            <div class="relative">
                                <input type="password" id="current_password" name="current_password" data-password autocomplete="current-password"
                                       class="pf-input pr-11" required @error('current_password') aria-invalid="true" @enderror>
                                <button type="button" data-toggle-password aria-label="Show password"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-lg leading-none" style="color:#8a7a70;">👁️</button>
                            </div>
                            @error('current_password')<p class="mt-1 text-xs font-medium" style="color:#B91C1C;">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label class="pf-label" for="new_password">New Password</label>
                            <div class="relative">
                                <input type="password" id="new_password" name="password" data-password minlength="8" autocomplete="new-password"
                                       class="pf-input pr-11" required @error('password') aria-invalid="true" @enderror>
                                <button type="button" data-toggle-password aria-label="Show password"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-lg leading-none" style="color:#8a7a70;">👁️</button>
                            </div>
                        </div>

                        <div>
                            <label class="pf-label" for="password_confirmation">Confirm New Password</label>
                            <div class="relative">
                                <input type="password" id="password_confirmation" name="password_confirmation" data-password autocomplete="new-password"
                                       class="pf-input pr-11" required>
                                <button type="button" data-toggle-password aria-label="Show password"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-lg leading-none" style="color:#8a7a70;">👁️</button>
                            </div>
                            <p class="mt-1 hidden text-xs font-medium" style="color:#B91C1C;" data-pw-mismatch>The passwords do not match.</p>
                        </div>
                        @error('password')<p class="-mt-2 text-xs font-medium sm:col-span-2" style="color:#B91C1C;">{{ $message }}</p>@enderror

                        <div class="rounded-xl p-3.5 sm:col-span-2" style="background:#FDF8F5;border:1px solid #F5EBE4;">
                            <p class="text-xs font-semibold pf-text">Your new password needs:</p>
                            <ul class="mt-2 grid gap-1.5 text-xs sm:grid-cols-2" data-pw-rules>
                                @foreach (['len' => 'At least 8 characters', 'upper' => 'An uppercase letter', 'lower' => 'A lowercase letter', 'num' => 'A number', 'sym' => 'A symbol (e.g. ! @ #)'] as $key => $rule)
                                    <li class="flex items-center gap-2 pf-muted" data-rule="{{ $key }}">
                                        <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border" style="border-color:#E6D9CF;" data-rule-dot></span>{{ $rule }}
                                    </li>
                                @endforeach
                            </ul>
                            <p class="mt-2 text-xs pf-muted">Example: Mamimili@2027</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3 border-t pt-5 sm:col-span-2" style="border-color:#F0E4DC;">
                            <button type="submit" class="pf-btn px-8">Continue</button>
                            <p class="flex items-center gap-1.5 text-xs pf-muted">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 6 9-6"/></svg>
                                We'll email a verification code to {{ \App\Services\LoginOtp::maskEmail($user->email) }}.
                            </p>
                        </div>
                    </form>
                @endif
            </div>
            <script>
            (function () {
                // Live password rules and confirmation match (the server checks the same rules).
                var form = document.getElementById('pw-form');
                if (form) {
                    var pw = form.querySelector('[name="password"]'), cf = form.querySelector('[name="password_confirmation"]');
                    var tests = { len: /.{8,}/, upper: /[A-Z]/, lower: /[a-z]/, num: /\d/, sym: /[^A-Za-z0-9]/ };
                    var check = function () {
                        Object.keys(tests).forEach(function (k) {
                            var li = form.querySelector('[data-rule="' + k + '"]'), dot = li.querySelector('[data-rule-dot]'), ok = tests[k].test(pw.value);
                            li.style.color = ok ? '#047857' : '';
                            dot.style.background = ok ? '#10B981' : ''; dot.style.borderColor = ok ? '#10B981' : '#E6D9CF';
                            dot.innerHTML = ok ? '<svg width="10" height="10" fill="none" stroke="#fff" stroke-width="3.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>' : '';
                        });
                        var bad = cf.value && cf.value !== pw.value;
                        form.querySelector('[data-pw-mismatch]').classList.toggle('hidden', !bad);
                        cf.setCustomValidity(bad ? 'The passwords do not match.' : '');
                    };
                    pw.addEventListener('input', check); cf.addEventListener('input', check);
                }
                // Resend countdown.
                var btn = document.querySelector('[data-resend]');
                if (btn) {
                    var left = parseInt(btn.dataset.wait, 10) || 0, timer = btn.querySelector('[data-resend-timer]');
                    var tick = setInterval(function () {
                        left--;
                        if (left <= 0) { clearInterval(tick); btn.disabled = false; timer.textContent = ''; return; }
                        timer.textContent = ' (' + left + 's)';
                    }, 1000);
                    if (left <= 0) clearInterval(tick);
                }
            })();
            </script>
        </div>
    </div>
</div>
</div>

@include('partials.password-toggle')
</x-layout>
