@extends('sc.layout')
@section('title', 'Security')
@section('icon', 'user')

@php
    // Same flow and rules as buyers and sellers (Concerns\ConfirmsPasswordChange): step 1 new password, step 2 emailed code.
    $pwCodes     = app(\App\Services\PasswordChangeCode::class);
    $pwPending   = $pwCodes->pending();
    $pwWait      = $pwPending ? $pwCodes->cooldown() : 0;
    $pwLeft      = $pwPending ? max(0, $pwPending['expires_at'] - now()->timestamp) : 0;
    $maskedEmail = \App\Services\LoginOtp::maskEmail($user->email);
    $rules = ['len' => 'At least 8 characters', 'upper' => 'An uppercase letter', 'lower' => 'A lowercase letter',
              'num' => 'A number', 'sym' => 'A symbol (e.g. ! @ #)', 'diff' => 'Different from your current password'];
    $eye = '<button type="button" data-toggle-password aria-label="Show password" class="se-eye">' . view('sc.partials.icon', ['name' => 'eye', 'size' => 18])->render() . '</button>';
@endphp

@section('content')
<style>
    .se-wrap { max-width: 640px; }
    .se-steps { display: flex; gap: 6px; }
    .se-steps i { display: block; width: 34px; height: 5px; border-radius: 999px; background: #E2E8F0; }
    .se-steps i.on { background: var(--accent-blue); }
    .se-form { display: grid; gap: 14px; padding: 18px; }
    .se-label { display: block; margin-bottom: 5px; font-size: 12px; font-weight: 600; color: var(--text-sub); }
    .se-pw { position: relative; }
    .se-pw .form-input { width: 100%; padding-right: 2.6rem; }
    .se-eye { position: absolute; right: .7rem; top: 50%; transform: translateY(-50%); border: 0; background: none; cursor: pointer; line-height: 1; color: var(--text-muted); }
    .se-err { margin-top: 4px; font-size: 12px; color: #B91C1C; }
    .se-rules { border-radius: 10px; border: 1px solid var(--border); background: #F8FAFC; padding: 12px 14px; }
    .se-rules ul { display: grid; gap: 6px; grid-template-columns: repeat(2, minmax(0, 1fr)); margin: 8px 0 0; padding: 0; list-style: none; }
    @media (max-width: 560px) { .se-rules ul { grid-template-columns: 1fr; } }
    .se-rules li { display: flex; align-items: center; gap: 7px; font-size: 12px; color: var(--text-muted); }
    .se-dot { display: flex; width: 16px; height: 16px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 999px; border: 1px solid #CBD5E1; }
    .se-note { display: flex; gap: 10px; border-radius: 10px; border: 1px solid #FBD5C5; background: #FFF7F3; padding: 13px 14px; font-size: 13px; color: #57534E; }
    .se-code { max-width: 15rem; text-align: center; font-family: 'SF Mono', Consolas, monospace; font-size: 22px; font-weight: 700; letter-spacing: .45em; }
</style>

<div class="page-header flex-between">
    <div>
        <a href="{{ route('sc.account') }}" style="font-size:12.5px;color:var(--text-muted);">&larr; Account Management</a>
        <h1 style="margin-top:2px;">Security</h1>
    </div>
</div>
<div class="page-body">
    <div class="se-wrap">
        <div class="card" data-password-card>
            <div class="card-header">
                <div>
                    <h2>Change password</h2>
                    <div class="card-meta" style="margin-top:2px;">
                        {{ $pwPending ? 'Step 2 of 2: enter the code we emailed you.' : 'Step 1 of 2: choose a new password. We\'ll email you a code to confirm it.' }}
                    </div>
                </div>
                <div class="se-steps" aria-hidden="true"><i class="on"></i><i class="{{ $pwPending ? 'on' : '' }}"></i></div>
            </div>

            @if ($pwPending)
                {{-- Step 2: the code from the email --}}
                <div class="se-form" data-password-code-step>
                    <div class="se-note">
                        <span style="color:var(--accent-orange);">@include('sc.partials.icon', ['name' => 'inbox', 'size' => 20, 'sw' => 2])</span>
                        <div>
                            <b style="color:var(--text);">Check your email</b>
                            <div>We sent a 6-digit code to <b>{{ $maskedEmail }}</b>. Your password stays the same until you enter it.
                                @if ($pwLeft > 0) The code expires in {{ ceil($pwLeft / 60) }} {{ \Illuminate\Support\Str::plural('minute', (int) ceil($pwLeft / 60)) }}.@else <b style="color:#B91C1C;">The code has expired. Send a new one.</b>@endif
                            </div>
                        </div>
                    </div>

                    <form action="{{ route('sc.account.password.confirm') }}" method="POST">
                        @csrf
                        <label class="se-label" for="se-code">Verification code</label>
                        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
                            <input type="text" id="se-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" required autofocus
                                   class="form-input se-code" placeholder="••••••" oninput="this.value=this.value.replace(/\D/g,'').slice(0,6)">
                            <button type="submit" class="btn btn-blue">Confirm new password</button>
                        </div>
                        @error('code')<p class="se-err">{{ $message }}</p>@enderror
                    </form>

                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px 16px;border-top:1px solid var(--border);padding-top:12px;font-size:13px;">
                        <form action="{{ route('sc.account.password.resend') }}" method="POST">@csrf
                            <button class="btn btn-ghost btn-sm" data-resend data-wait="{{ $pwWait }}" @disabled($pwWait > 0)>
                                Send a new code<span data-resend-timer>{{ $pwWait > 0 ? ' (' . $pwWait . 's)' : '' }}</span>
                            </button>
                        </form>
                        <form action="{{ route('sc.account.password.cancel') }}" method="POST">@csrf
                            <button class="btn btn-ghost btn-sm">Cancel the change</button>
                        </form>
                        <span class="text-muted" style="font-size:12px;">Didn't get it? Check your spam folder.</span>
                    </div>
                </div>
            @else
                {{-- Step 1: current and new password --}}
                <form action="{{ route('sc.account.password') }}" method="POST" class="se-form" id="se-form" autocomplete="off">
                    @csrf @method('PUT')
                    <div>
                        <label class="se-label" for="current_password">Current password</label>
                        <div class="se-pw"><input type="password" id="current_password" name="current_password" data-password class="form-input" required autocomplete="current-password">{!! $eye !!}</div>
                        @error('current_password')<p class="se-err">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="se-label" for="password">New password</label>
                        <div class="se-pw"><input type="password" id="password" name="password" data-password class="form-input" minlength="8" required autocomplete="new-password">{!! $eye !!}</div>
                        @error('password')<p class="se-err">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="se-label" for="password_confirmation">Confirm new password</label>
                        <div class="se-pw"><input type="password" id="password_confirmation" name="password_confirmation" data-password class="form-input" minlength="8" required autocomplete="new-password">{!! $eye !!}</div>
                        <p class="se-err" data-pw-mismatch hidden>The passwords do not match.</p>
                    </div>

                    {{-- The rules the server checks (Concerns\ConfirmsPasswordChange::newPasswordRule); ticked live while typing --}}
                    <div class="se-rules">
                        <b style="font-size:12px;color:var(--text);">Your new password needs:</b>
                        <ul data-pw-rules>
                            @foreach ($rules as $key => $rule)
                                <li data-rule="{{ $key }}"><span class="se-dot" data-rule-dot></span>{{ $rule }}</li>
                            @endforeach
                        </ul>
                        <div class="text-muted" style="margin-top:8px;font-size:12px;">Example: LogiSort@2027</div>
                    </div>

                    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;border-top:1px solid var(--border);padding-top:14px;">
                        <span class="text-muted" style="font-size:12px;">We'll email a verification code to {{ $maskedEmail }}.</span>
                        <button type="submit" class="btn btn-blue">Continue</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>

<script>
(function () {
    // Live password rules and confirmation match (the server checks the same rules).
    var form = document.getElementById('se-form');
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
                dot.style.background = ok ? '#10B981' : ''; dot.style.borderColor = ok ? '#10B981' : '#CBD5E1';
                dot.innerHTML = ok ? '<svg width="10" height="10" fill="none" stroke="#fff" stroke-width="3.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>' : '';
            });
            var bad = cf.value && cf.value !== pw.value;
            form.querySelector('[data-pw-mismatch]').hidden = !bad;
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
@endsection
