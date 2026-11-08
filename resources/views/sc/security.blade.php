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
    $ico = fn (string $d, int $px = 18) => '<svg width="' . $px . '" height="' . $px . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="' . $d . '"/></svg>';
    $lock   = 'M6 11h12a1 1 0 0 1 1 1v8a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1v-8a1 1 0 0 1 1-1zM8 11V7a4 4 0 0 1 8 0v4';
    $keyIco = 'M15 7a4 4 0 1 1-3.9 4.9L4 19v2h3v-2h2v-2h2l1.1-1.1A4 4 0 0 1 15 7zM16 10h.01';
    $shield = 'M12 3l7 3v6c0 4.5-3 7.5-7 9-4-1.5-7-4.5-7-9V6l7-3zM9 12l2 2 4-4';
    $mail   = 'M4 6h16a1 1 0 0 1 1 1v10a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1zM3.5 7l8.5 6 8.5-6';
    $bulb   = 'M9 18h6M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.6.5 1 1.2 1 2.1h5c0-.9.4-1.6 1-2.1A6 6 0 0 0 12 3z';
    $eye = '<button type="button" data-toggle-password aria-label="Show password" class="se-eye">' . view('sc.partials.icon', ['name' => 'eye', 'size' => 18])->render() . '</button>';
@endphp

@section('content')
<style>
    /* Fills the page: the form on the left, sign-in details and tips on the right. */
    .se-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 18px; align-items: start; }
    @media (max-width: 1050px) { .se-grid { grid-template-columns: 1fr; } }
    .se-side { display: grid; gap: 16px; }
    .se-head { display: flex; flex-wrap: wrap; align-items: center; gap: 14px; padding: 18px 20px; border-bottom: 1px solid var(--border-dim); }
    .se-head h2 { margin: 0; font-size: 17px; font-weight: 700; color: var(--text); }
    .se-head p { margin: 3px 0 0; font-size: 13px; color: var(--text-muted); }
    .se-head-text { min-width: 0; flex: 1; }
    .se-badge { display: flex; flex: none; width: 46px; height: 46px; align-items: center; justify-content: center; border-radius: 14px; background: #fff1eb; color: var(--accent-blue-d); }
    .se-steps { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: var(--text-muted); }
    .se-step { display: flex; align-items: center; gap: 7px; }
    .se-step b { display: flex; width: 24px; height: 24px; align-items: center; justify-content: center; border-radius: 999px; border: 1.5px solid #cbd5e1; font-size: 12px; color: var(--text-muted); background: #fff; }
    .se-step.on { color: var(--text); }
    .se-step.on b { border-color: var(--accent-blue); background: var(--accent-blue); color: #fff; }
    .se-step.done b { border-color: #10b981; background: #10b981; color: #fff; }
    .se-steps .line { width: 28px; height: 2px; border-radius: 2px; background: #e2e8f0; }
    .se-steps .line.on { background: var(--accent-blue); }
    .se-form { display: grid; gap: 18px; padding: 20px; }
    .se-row { display: grid; gap: 16px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    @media (max-width: 680px) { .se-row { grid-template-columns: 1fr; } }
    .se-label { display: block; margin-bottom: 6px; font-size: 12.5px; font-weight: 600; color: var(--text-sub); }
    .se-pw { position: relative; }
    .se-pw .form-input { width: 100%; height: 44px; padding-left: 2.5rem; padding-right: 2.6rem; }
    .se-pw .se-ico { position: absolute; left: .8rem; top: 50%; transform: translateY(-50%); display: flex; color: var(--text-muted); pointer-events: none; }
    .se-eye { position: absolute; right: .7rem; top: 50%; transform: translateY(-50%); border: 0; background: none; cursor: pointer; line-height: 1; color: var(--text-muted); }
    .se-err { margin: 5px 0 0; font-size: 12px; color: #B91C1C; }
    .se-meter { display: flex; align-items: center; gap: 10px; margin-top: 8px; }
    .se-meter-bars { display: grid; flex: 1; grid-template-columns: repeat(4, 1fr); gap: 4px; }
    .se-meter-bars i { height: 5px; border-radius: 999px; background: #e2e8f0; transition: background .2s; }
    .se-meter-text { min-width: 56px; text-align: right; font-size: 12px; font-weight: 600; color: var(--text-muted); }
    .se-rules { border-radius: 12px; border: 1px solid var(--border); background: #F8FAFC; padding: 14px 16px; }
    .se-rules-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 6px; }
    .se-rules ul { display: grid; gap: 9px 14px; grid-template-columns: repeat(3, minmax(0, 1fr)); margin: 12px 0 0; padding: 0; list-style: none; }
    @media (max-width: 900px) { .se-rules ul { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (max-width: 560px) { .se-rules ul { grid-template-columns: 1fr; } }
    .se-rules li { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--text-muted); }
    .se-dot { display: flex; width: 18px; height: 18px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 999px; border: 1.5px solid #CBD5E1; }
    .se-example { font-size: 12px; color: var(--text-muted); }
    .se-example code { padding: 1px 6px; border-radius: 6px; background: #fff; border: 1px solid var(--border); font-family: 'SF Mono', Consolas, monospace; color: var(--text); }
    .se-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; border-top: 1px solid var(--border-dim); padding-top: 16px; }
    .se-foot-note { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--text-muted); }
    .se-foot-note svg { flex: none; }
    .se-foot .btn { min-width: 150px; justify-content: center; }
    .se-note { display: flex; gap: 10px; border-radius: 10px; border: 1px solid #FBD5C5; background: #FFF7F3; padding: 13px 14px; font-size: 13px; color: #57534E; }
    .se-code { max-width: 15rem; text-align: center; font-family: 'SF Mono', Consolas, monospace; font-size: 22px; font-weight: 700; letter-spacing: .45em; }
    .se-card-title { display: flex; align-items: center; gap: 8px; padding: 14px 16px; border-bottom: 1px solid var(--border-dim); font-size: 14px; font-weight: 700; color: var(--text); }
    .se-card-title span { display: flex; color: var(--accent-blue); }
    .se-list { display: grid; gap: 12px; margin: 0; padding: 14px 16px 16px; list-style: none; }
    .se-list li { display: flex; gap: 10px; font-size: 12.5px; line-height: 1.45; color: var(--text-sub); }
    .se-list b { color: var(--text); }
    .se-num { display: flex; flex: none; width: 22px; height: 22px; align-items: center; justify-content: center; border-radius: 999px; background: #fff1eb; font-size: 11.5px; font-weight: 700; color: var(--accent-blue-d); }
    .se-kv { display: grid; gap: 2px; }
    .se-kv small { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: var(--text-muted); }
    .se-kv span { font-size: 13px; font-weight: 600; color: var(--text); word-break: break-all; }
</style>

<div class="page-header flex-between">
    <div>
        <a href="{{ route('sc.account') }}" style="font-size:12.5px;color:var(--text-muted);">&larr; Account Management</a>
        <h1 style="margin-top:2px;">Security</h1>
    </div>
</div>
<div class="page-body">
    <div class="se-grid">
        <div class="card" data-password-card>
            <div class="se-head">
                <span class="se-badge">{!! $ico($lock, 22) !!}</span>
                <div class="se-head-text">
                    <h2>Change password</h2>
                    <p>{{ $pwPending ? 'Step 2 of 2: enter the code we emailed you.' : 'Step 1 of 2: choose a new password. We\'ll email you a code to confirm it.' }}</p>
                </div>
                <div class="se-steps" aria-hidden="true">
                    <span class="se-step {{ $pwPending ? 'done' : 'on' }}"><b>{!! $pwPending ? '&#10003;' : '1' !!}</b>New password</span>
                    <span class="line {{ $pwPending ? 'on' : '' }}"></span>
                    <span class="se-step {{ $pwPending ? 'on' : '' }}"><b>2</b>Email code</span>
                </div>
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
                        <div class="se-pw"><span class="se-ico">{!! $ico($keyIco) !!}</span><input type="password" id="current_password" name="current_password" data-password class="form-input" required autocomplete="current-password" placeholder="Enter your current password">{!! $eye !!}</div>
                        @error('current_password')<p class="se-err">{{ $message }}</p>@enderror
                    </div>
                    <div class="se-row">
                        <div>
                            <label class="se-label" for="password">New password</label>
                            <div class="se-pw"><span class="se-ico">{!! $ico($lock) !!}</span><input type="password" id="password" name="password" data-password class="form-input" minlength="8" required autocomplete="new-password" placeholder="Choose a new password">{!! $eye !!}</div>
                            <div class="se-meter" aria-live="polite"><div class="se-meter-bars" data-pw-meter><i></i><i></i><i></i><i></i></div><span class="se-meter-text" data-pw-meter-text></span></div>
                            @error('password')<p class="se-err">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="se-label" for="password_confirmation">Confirm new password</label>
                            <div class="se-pw"><span class="se-ico">{!! $ico($lock) !!}</span><input type="password" id="password_confirmation" name="password_confirmation" data-password class="form-input" minlength="8" required autocomplete="new-password" placeholder="Type it again">{!! $eye !!}</div>
                            <p class="se-err" data-pw-mismatch hidden>The passwords do not match.</p>
                        </div>
                    </div>

                    {{-- The rules the server checks (Concerns\ConfirmsPasswordChange::newPasswordRule); ticked live while typing --}}
                    <div class="se-rules">
                        <div class="se-rules-head">
                            <b style="font-size:13px;color:var(--text);">Your new password needs:</b>
                            <span class="se-example">Example: <code>LogiSort@2027</code></span>
                        </div>
                        <ul data-pw-rules>
                            @foreach ($rules as $key => $rule)
                                <li data-rule="{{ $key }}"><span class="se-dot" data-rule-dot></span>{{ $rule }}</li>
                            @endforeach
                        </ul>
                    </div>

                    <div class="se-foot">
                        <span class="se-foot-note">{!! $ico($mail, 16) !!}<span>We'll email a verification code to <b style="color:var(--text);">{{ $maskedEmail }}</b>.</span></span>
                        <button type="submit" class="btn btn-blue">Continue</button>
                    </div>
                </form>
            @endif
        </div>

        <aside class="se-side">
            <div class="card">
                <div class="se-card-title"><span>{!! $ico($shield) !!}</span>Sign-in details</div>
                <div class="se-list" style="gap:14px;">
                    <div class="se-kv"><small>Email</small><span>{{ $maskedEmail }}</span></div>
                    <div class="se-kv"><small>Last signed in</small><span>{{ $user->last_login_at ? $user->last_login_at->format('M j, Y · g:i A') : 'Not recorded yet' }}</span></div>
                </div>
            </div>
            <div class="card">
                <div class="se-card-title"><span>{!! $ico($mail) !!}</span>How it works</div>
                <ol class="se-list">
                    <li><span class="se-num">1</span><span>Enter your <b>current password</b> and pick a new one that meets every rule.</span></li>
                    <li><span class="se-num">2</span><span>We email you a <b>6-digit code</b>. Your old password still works until you enter it.</span></li>
                    <li><span class="se-num">3</span><span>Enter the code and your <b>new password</b> is saved.</span></li>
                </ol>
            </div>
            <div class="card">
                <div class="se-card-title"><span>{!! $ico($bulb) !!}</span>Tips</div>
                <ul class="se-list">
                    <li><span class="se-num">&bull;</span><span>Don't reuse a password from another site.</span></li>
                    <li><span class="se-num">&bull;</span><span>Never share your password or code with riders or staff. ALVY will never ask for it.</span></li>
                    <li><span class="se-num">&bull;</span><span>A short phrase with a number and a symbol is easy to remember and hard to guess.</span></li>
                </ul>
            </div>
        </aside>
    </div>
</div>

<script>
(function () {
    // Live password rules, strength bar and confirmation match (the server checks the same rules).
    var form = document.getElementById('se-form');
    if (form) {
        var cur = form.querySelector('[name="current_password"]'), pw = form.querySelector('[name="password"]'), cf = form.querySelector('[name="password_confirmation"]');
        var tests = {
            len: function (v) { return v.length >= 8; }, upper: function (v) { return /[A-Z]/.test(v); }, lower: function (v) { return /[a-z]/.test(v); },
            num: function (v) { return /\d/.test(v); }, sym: function (v) { return /[^A-Za-z0-9]/.test(v); },
            diff: function (v) { return v !== '' && v !== cur.value; }
        };
        var colors = ['', '#e5484d', '#d99a00', '#3b82f6', '#10b981'], names = ['', 'Weak', 'Fair', 'Good', 'Strong'];
        var check = function () {
            Object.keys(tests).forEach(function (k) {
                var li = form.querySelector('[data-rule="' + k + '"]'), dot = li.querySelector('[data-rule-dot]'), ok = tests[k](pw.value);
                li.style.color = ok ? '#047857' : '';
                dot.style.background = ok ? '#10B981' : ''; dot.style.borderColor = ok ? '#10B981' : '#CBD5E1';
                dot.innerHTML = ok ? '<svg width="10" height="10" fill="none" stroke="#fff" stroke-width="3.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>' : '';
            });
            var met = ['len', 'upper', 'lower', 'num', 'sym'].filter(function (k) { return tests[k](pw.value); }).length;
            var level = !pw.value ? 0 : met <= 2 ? 1 : met === 3 ? 2 : met === 4 ? 3 : 4;
            form.querySelectorAll('[data-pw-meter] i').forEach(function (bar, i) { bar.style.background = i < level ? colors[level] : ''; });
            var txt = form.querySelector('[data-pw-meter-text]'); txt.textContent = names[level]; txt.style.color = colors[level];
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
