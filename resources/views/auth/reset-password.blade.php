<x-auth-shell title="Reset Password — ALVY" :heading="$valid ? 'Set a New Password' : 'Link Expired'"
              :subtitle="$valid ? 'Choose a strong password you don\'t use anywhere else.' : 'This password reset link can no longer be used.'">
    <x-slot:head>
        <style>
            .fld { width:100%;border-radius:.75rem;border:1px solid #E6D9CF;background:#fff;padding:.8rem 1rem;font-size:.9rem;color:#222;outline:none;transition:border-color .15s,box-shadow .15s; }
            .fld::placeholder { color:#b5a79d; }
            .fld:focus { border-color:#fa4e1c;box-shadow:0 0 0 4px rgba(250,78,28,.12); }
            .fld[readonly] { background:#FAF7F5;color:#5a4d45; }
            .fld-label { display:block;margin-bottom:.4rem;font-size:.8rem;font-weight:600;color:#3a2f29; }
            .btn-primary { display:flex;width:100%;align-items:center;justify-content:center;gap:.5rem;border-radius:.75rem;padding:.85rem 1rem;font-size:.9rem;font-weight:700;color:#fff;
                           background:linear-gradient(135deg,#fa4e1c,#ff7a45);box-shadow:0 12px 24px -12px rgba(250,78,28,.8);transition:transform .15s,box-shadow .15s,filter .15s,opacity .15s; }
            .btn-primary:hover:not(:disabled) { transform:translateY(-1px);filter:brightness(1.03); }
            .btn-primary:disabled { opacity:.7;cursor:wait; }
            .spin { width:1rem;height:1rem;border-radius:999px;border:2px solid rgba(255,255,255,.45);border-top-color:#fff;animation:spin .7s linear infinite; }
            @keyframes spin { to { transform:rotate(360deg); } }
            .rule { display:flex;align-items:center;gap:.4rem;font-size:.75rem;color:#8a7a70; }
            .rule::before { content:"";width:.45rem;height:.45rem;border-radius:999px;background:#E6D9CF; }
            .rule.ok { color:#047857; }
            .rule.ok::before { background:#10B981; }
            .meter { height:.35rem;border-radius:999px;background:#F0E4DC;overflow:hidden; }
            .meter > span { display:block;height:100%;width:0;border-radius:999px;transition:width .2s,background .2s; }
        </style>
    </x-slot:head>

    <div class="px-6 pb-8 pt-6 sm:px-10 sm:pb-10">
        @if (! $valid)
            <div class="rounded-xl border p-4 text-sm" role="alert" style="background:#FFFBEB;border-color:#FDE68A;color:#92400E;" data-reset-invalid>
                <p class="font-semibold">This password reset link is invalid or has expired.</p>
                <p class="mt-1">Reset links work once, for {{ config('auth.passwords.users.expire', 60) }} minutes. If you asked more than once, only the newest link works.</p>
            </div>
            <a href="{{ route('password.request') }}" class="btn-primary mt-5">Request a New Link</a>
            <p class="mt-5 text-center text-sm"><a href="{{ route('login') }}" class="font-semibold hover:underline" style="color:#fa4e1c;">Back to Login</a></p>
        @else
            @if ($errors->any())
                <div class="mb-5 flex items-start gap-2 rounded-xl border p-3 text-sm" role="alert" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
                    <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <div class="flex-1">
                        @foreach (array_unique($errors->all()) as $message)<p>{{ $message }}</p>@endforeach
                        @if ($errors->has('email'))<a href="{{ route('password.request') }}" class="mt-1 inline-block font-semibold underline">Request a new link</a>@endif
                    </div>
                </div>
            @endif

            <form action="{{ route('password.store') }}" method="POST" class="space-y-5" id="resetForm">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label class="fld-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $email) }}" class="fld" readonly autocomplete="username">
                </div>

                <div>
                    <label class="fld-label" for="password">New Password</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" data-password class="fld pr-11" required minlength="8"
                               autocomplete="new-password" placeholder="At least 8 characters" aria-describedby="pw-rules">
                        <button type="button" data-toggle-password aria-label="Show password" class="absolute right-3 top-1/2 -translate-y-1/2"></button>
                    </div>
                    <div class="mt-2 meter" aria-hidden="true"><span id="pw-meter"></span></div>
                    <p class="mt-1 text-xs font-semibold" id="pw-strength" style="color:#8a7a70;" aria-live="polite"></p>
                    <div id="pw-rules" class="mt-2 grid grid-cols-2 gap-1">
                        <span class="rule" data-rule="len">8 or more characters</span>
                        <span class="rule" data-rule="case">Upper &amp; lowercase</span>
                        <span class="rule" data-rule="num">A number</span>
                        <span class="rule" data-rule="sym">A symbol (e.g. ! @ #)</span>
                    </div>
                </div>

                <div>
                    <label class="fld-label" for="password_confirmation">Confirm New Password</label>
                    <div class="relative">
                        <input type="password" id="password_confirmation" name="password_confirmation" data-password class="fld pr-11" required
                               autocomplete="new-password" placeholder="Type it again">
                        <button type="button" data-toggle-password aria-label="Show password" class="absolute right-3 top-1/2 -translate-y-1/2"></button>
                    </div>
                    <p class="mt-1 hidden text-xs font-medium" id="pw-match" style="color:#DC2626;" role="alert">The passwords do not match.</p>
                </div>

                <button type="submit" class="btn-primary" id="resetBtn">
                    <span data-label>Reset Password</span>
                    <span class="spin hidden" data-spinner aria-hidden="true"></span>
                </button>
            </form>

            <p class="mt-6 text-center text-sm"><a href="{{ route('login') }}" class="font-semibold hover:underline" style="color:#fa4e1c;">Back to Login</a></p>
        @endif
    </div>

    <x-slot:scripts>
        @include('partials.password-toggle')
        @if ($valid)
        <script>
        (function () {
            var form = document.getElementById('resetForm'), pw = document.getElementById('password'), confirm = document.getElementById('password_confirmation');
            var meter = document.getElementById('pw-meter'), label = document.getElementById('pw-strength'), match = document.getElementById('pw-match'), btn = document.getElementById('resetBtn');
            var tests = {
                len:  function (v) { return v.length >= 8; },
                case: function (v) { return /[a-z]/.test(v) && /[A-Z]/.test(v); },
                num:  function (v) { return /\d/.test(v); },
                sym:  function (v) { return /[^A-Za-z0-9]/.test(v); },
            };
            function check() {
                var v = pw.value, score = 0;
                Object.keys(tests).forEach(function (k) {
                    var ok = tests[k](v); if (ok) score++;
                    document.querySelector('[data-rule="' + k + '"]').classList.toggle('ok', ok);
                });
                if (v.length >= 12 && score === 4) score = 5;
                var levels = [['', '#E6D9CF', 0], ['Weak', '#DC2626', 25], ['Weak', '#DC2626', 35], ['Fair', '#F59E0B', 55], ['Good', '#10B981', 80], ['Strong', '#047857', 100]];
                var l = v ? levels[score] : levels[0];
                meter.style.width = l[2] + '%'; meter.style.background = l[1];
                label.textContent = v ? 'Strength: ' + l[0] : ''; label.style.color = l[1];
                var bad = confirm.value && confirm.value !== v;
                match.classList.toggle('hidden', !bad);
                confirm.setCustomValidity(bad ? 'The passwords do not match.' : '');
                return score >= 4;
            }
            pw.addEventListener('input', check);
            confirm.addEventListener('input', check);
            form.addEventListener('submit', function (e) {
                if (!check()) { e.preventDefault(); pw.focus(); label.textContent = 'Please meet all the password requirements.'; label.style.color = '#DC2626'; return; }
                if (form.dataset.sending) { e.preventDefault(); return; }
                form.dataset.sending = '1';
                btn.disabled = true;
                btn.querySelector('[data-label]').textContent = 'Saving…';
                btn.querySelector('[data-spinner]').classList.remove('hidden');
            });
        })();
        </script>
        @endif
    </x-slot:scripts>
</x-auth-shell>
