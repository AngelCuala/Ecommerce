<x-seller-layout title="Change password" active="account">
@include('partials.panel.plain')
@php
    $pwCodes   = app(\App\Services\PasswordChangeCode::class);
    $pwPending = $pwCodes->pending();
    $pwWait    = $pwPending ? $pwCodes->cooldown() : 0;
    $pwLeft    = $pwPending ? max(0, $pwPending['expires_at'] - now()->timestamp) : 0;
    $maskedEmail = \App\Services\LoginOtp::maskEmail($user->email);
@endphp

<x-account-shell title="Accounts & Security" portal="Seller Centre" :nav="\App\Support\AccountNav::seller('security')"
    :badges="[['text' => 'Seller'], ['text' => 'Since ' . $user->created_at->format('M Y'), 'tone' => 'gray']]"
    :subtitle="$pwPending ? 'Change password · Step 2 of 2: enter the code we emailed you.' : 'Change password · Step 1 of 2: choose a new password. We\'ll email you a code to confirm it.'">

    @if ($pwPending)
        {{-- Step 2: the code from the email --}}
        <div class="acs-card acs-pad space-y-4">
            <div class="flex items-start gap-3 rounded-lg p-4" style="background:#FFF7F3;border:1px solid #FBD5C5;">
                <svg class="mt-0.5 h-5 w-5 shrink-0" style="color:#fa4e1c;" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 6 9-6"/></svg>
                <div class="text-sm" style="color:#57534E;">
                    <p class="font-semibold" style="color:#222;">Check your email</p>
                    <p>We sent a 6-digit code to <strong>{{ $maskedEmail }}</strong>. Your password stays the same until you enter it.
                        @if ($pwLeft > 0) The code expires in {{ ceil($pwLeft / 60) }} {{ \Illuminate\Support\Str::plural('minute', (int) ceil($pwLeft / 60)) }}.@else <strong style="color:#B91C1C;">The code has expired. Send a new one.</strong>@endif
                    </p>
                </div>
            </div>

            <form action="{{ route('seller.account.password.confirm') }}" method="POST">
                @csrf
                <label for="pw-code" class="pl-label">Verification code</label>
                <div class="flex flex-wrap items-center gap-3">
                    <input type="text" id="pw-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" required autofocus
                           class="input text-center font-mono text-2xl font-bold tracking-[.5em]" style="max-width:15rem;" placeholder="••••••"
                           oninput="this.value=this.value.replace(/\D/g,'').slice(0,6)">
                    <button type="submit" class="pl-btn">Confirm new password</button>
                </div>
                @error('code')<p class="pl-note !text-[#B91C1C]">{{ $message }}</p>@enderror
            </form>

            <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t pt-4 text-sm" style="border-color:#F2EEEA;">
                <form action="{{ route('seller.account.password.resend') }}" method="POST">@csrf
                    <button class="font-semibold disabled:cursor-not-allowed disabled:opacity-50" style="color:#fa4e1c;" data-resend data-wait="{{ $pwWait }}" @disabled($pwWait > 0)>
                        Send a new code<span data-resend-timer>{{ $pwWait > 0 ? ' (' . $pwWait . 's)' : '' }}</span>
                    </button>
                </form>
                <span class="pl-muted" aria-hidden="true">·</span>
                <form action="{{ route('seller.account.password.cancel') }}" method="POST">@csrf
                    <button class="font-semibold pl-muted hover:underline">Cancel the change</button>
                </form>
                <p class="w-full text-xs pl-muted">Didn't get it? Check your spam folder.</p>
            </div>
        </div>
    @else
        {{-- Step 1: current and new password --}}
        <form action="{{ route('seller.account.password') }}" method="POST" class="acs-card acs-pad space-y-4" id="pw-form">
            @csrf @method('PUT')

            <div>
                <label for="current_password" class="pl-label">Current password</label>
                <div class="relative">
                    <input type="password" id="current_password" name="current_password" data-password class="input pr-11" required autocomplete="current-password">
                    <button type="button" data-toggle-password aria-label="Show password" class="absolute right-3 top-1/2 -translate-y-1/2" style="color:#8a7f78;"></button>
                </div>
                @error('current_password')<p class="pl-note !text-[#B91C1C]">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password" class="pl-label">New password</label>
                <div class="relative">
                    <input type="password" id="password" name="password" data-password class="input pr-11" minlength="8" required autocomplete="new-password">
                    <button type="button" data-toggle-password aria-label="Show password" class="absolute right-3 top-1/2 -translate-y-1/2" style="color:#8a7f78;"></button>
                </div>
                @error('password')<p class="pl-note !text-[#B91C1C]">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="password_confirmation" class="pl-label">Confirm new password</label>
                <div class="relative">
                    <input type="password" id="password_confirmation" name="password_confirmation" data-password class="input pr-11" minlength="8" required autocomplete="new-password">
                    <button type="button" data-toggle-password aria-label="Show password" class="absolute right-3 top-1/2 -translate-y-1/2" style="color:#8a7f78;"></button>
                </div>
                <p class="pl-note hidden !text-[#B91C1C]" data-pw-mismatch>The passwords do not match.</p>
            </div>

            {{-- The rules the server checks (Concerns\ConfirmsPasswordChange::newPasswordRule); ticked live while typing --}}
            <div class="rounded-lg p-3.5" style="background:#FDF8F5;border:1px solid #F5EBE4;">
                <p class="text-xs font-semibold" style="color:#3a2f29;">Your new password needs:</p>
                <ul class="mt-2 grid gap-1.5 text-xs sm:grid-cols-2" data-pw-rules>
                    @foreach (['len' => 'At least 8 characters', 'upper' => 'An uppercase letter', 'lower' => 'A lowercase letter', 'num' => 'A number', 'sym' => 'A symbol (e.g. ! @ #)', 'diff' => 'Different from your current password'] as $key => $rule)
                        <li class="flex items-center gap-2 pl-muted" data-rule="{{ $key }}">
                            <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border" style="border-color:#E6D9CF;" data-rule-dot></span>{{ $rule }}
                        </li>
                    @endforeach
                </ul>
                <p class="mt-2 text-xs pl-muted">Example: Tindahan@2027</p>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t pt-4" style="border-color:#F2EEEA;">
                <p class="text-xs pl-muted">We'll email a verification code to {{ $maskedEmail }}.</p>
                <button type="submit" class="pl-btn">Continue</button>
            </div>
        </form>
    @endif
</x-account-shell>

<script>
(function () {
    // Live password rules and confirmation match (the server checks the same rules).
    var form = document.getElementById('pw-form');
    if (form) {
        var cur = form.querySelector('[name="current_password"]'), pw = form.querySelector('[name="password"]'), cf = form.querySelector('[name="password_confirmation"]');
        var tests = {
            len: function (v) { return v.length >= 8; }, upper: function (v) { return /[A-Z]/.test(v); }, lower: function (v) { return /[a-z]/.test(v); },
            num: function (v) { return /\d/.test(v); }, sym: function (v) { return /[^A-Za-z0-9]/.test(v); },
            diff: function (v) { return v !== '' && v !== cur.value; }
        };
        var check = function () {
            Object.keys(tests).forEach(function (k) {
                var li = form.querySelector('[data-rule="' + k + '"]'), dot = li.querySelector('[data-rule-dot]'), ok = tests[k](pw.value);
                li.style.color = ok ? '#047857' : '';
                dot.style.background = ok ? '#10B981' : ''; dot.style.borderColor = ok ? '#10B981' : '#E6D9CF';
                dot.innerHTML = ok ? '<svg width="10" height="10" fill="none" stroke="#fff" stroke-width="3.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>' : '';
            });
            var bad = cf.value && cf.value !== pw.value;
            form.querySelector('[data-pw-mismatch]').classList.toggle('hidden', !bad);
            cf.setCustomValidity(bad ? 'The passwords do not match.' : '');
        };
        [cur, pw, cf].forEach(function (el) { el.addEventListener('input', check); });
    }
    // Resend countdown.
    var btn = document.querySelector('[data-resend]');
    if (btn) {
        var left = parseInt(btn.dataset.wait, 10) || 0, timer = btn.querySelector('[data-resend-timer]');
        if (left > 0) {
            var tick = setInterval(function () {
                left--;
                if (left <= 0) { clearInterval(tick); btn.disabled = false; timer.textContent = ''; return; }
                timer.textContent = ' (' + left + 's)';
            }, 1000);
        }
    }
})();
</script>

@include('partials.password-toggle')
</x-seller-layout>
