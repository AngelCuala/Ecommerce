<x-auth-shell title="Forgot Password — ALVY" heading="Forgot Your Password?"
              subtitle="Enter the email address associated with your account, and we'll send you a link to reset your password.">
    <x-slot:head>
        <style>
            .fld { width:100%;border-radius:.75rem;border:1px solid #E6D9CF;background:#fff;padding:.8rem 1rem;font-size:.9rem;color:#222;outline:none;transition:border-color .15s,box-shadow .15s; }
            .fld::placeholder { color:#b5a79d; }
            .fld:focus { border-color:#fa4e1c;box-shadow:0 0 0 4px rgba(250,78,28,.12); }
            .fld[aria-invalid="true"] { border-color:#F87171; }
            .fld-label { display:block;margin-bottom:.4rem;font-size:.8rem;font-weight:600;color:#3a2f29; }
            .btn-primary { display:flex;width:100%;align-items:center;justify-content:center;gap:.5rem;border-radius:.75rem;padding:.85rem 1rem;font-size:.9rem;font-weight:700;color:#fff;
                           background:linear-gradient(135deg,#fa4e1c,#ff7a45);box-shadow:0 12px 24px -12px rgba(250,78,28,.8);transition:transform .15s,box-shadow .15s,filter .15s,opacity .15s; }
            .btn-primary:hover:not(:disabled) { transform:translateY(-1px);filter:brightness(1.03);box-shadow:0 16px 28px -12px rgba(250,78,28,.85); }
            .btn-primary:disabled { opacity:.7;cursor:wait; }
            .spin { width:1rem;height:1rem;border-radius:999px;border:2px solid rgba(255,255,255,.45);border-top-color:#fff;animation:spin .7s linear infinite; }
            @keyframes spin { to { transform:rotate(360deg); } }
        </style>
    </x-slot:head>

    <div class="px-6 pb-8 pt-6 sm:px-10 sm:pb-10">
        @if (session('status'))
            <div class="mb-5 flex items-start gap-2 rounded-xl border p-3 text-sm" role="status"
                 style="background:#ECFDF5;border-color:rgba(5,150,105,.25);color:#047857;" data-reset-sent>
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
                <div class="flex-1">
                    <p>{{ session('status') }}</p>
                    <p class="mt-1 text-xs" style="color:#059669;">The link works for {{ config('auth.passwords.users.expire', 60) }} minutes. Can't find it? Check your spam or promotions folder.</p>
                </div>
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-5 flex items-start gap-2 rounded-xl border p-3 text-sm" role="alert"
                 style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="flex-1">{{ $errors->first() }}</p>
            </div>
        @endif

        <form action="{{ route('password.email') }}" method="POST" class="space-y-5" id="forgotForm" novalidate>
            @csrf
            <div>
                <label class="fld-label" for="email">Email Address</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2" style="color:#b5a79d;" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 6 9-6"/></svg>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" class="fld pl-11" required autofocus maxlength="255"
                           autocomplete="email" placeholder="example@gmail.com" aria-describedby="email-help"
                           @error('email') aria-invalid="true" @enderror>
                </div>
                <p id="email-help" class="mt-1.5 text-xs" style="color:#8a7a70;">Use the email you signed up with.</p>
                <p id="email-error" class="mt-1 hidden text-xs font-medium" style="color:#DC2626;" role="alert"></p>
            </div>

            <button type="submit" class="btn-primary" id="sendBtn">
                <span data-label>Send Reset Link</span>
                <span class="spin hidden" data-spinner aria-hidden="true"></span>
            </button>
        </form>

        <p class="mt-6 text-center text-sm">
            <a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 font-semibold hover:underline" style="color:#fa4e1c;">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5M11 18l-6-6 6-6"/></svg>
                Back to Login
            </a>
        </p>
    </div>

    <x-slot:scripts>
        <script>
        (function () {
            var form = document.getElementById('forgotForm'), email = document.getElementById('email'), err = document.getElementById('email-error'), btn = document.getElementById('sendBtn');
            form.addEventListener('submit', function (e) {
                var v = email.value.trim();
                var msg = !v ? 'Please enter your email address.' : (!/^[^@\s]+@[^@\s]+\.[A-Za-z]{2,}$/.test(v) ? 'Please enter a valid email address (e.g. juan@gmail.com).' : '');
                if (msg) {
                    e.preventDefault();
                    err.textContent = msg; err.classList.remove('hidden'); email.setAttribute('aria-invalid', 'true'); email.focus();
                    return;
                }
                if (form.dataset.sending) { e.preventDefault(); return; }   // no double submit
                form.dataset.sending = '1';
                btn.disabled = true;
                btn.querySelector('[data-label]').textContent = 'Sending…';
                btn.querySelector('[data-spinner]').classList.remove('hidden');
            });
            email.addEventListener('input', function () { err.classList.add('hidden'); email.removeAttribute('aria-invalid'); });
            window.addEventListener('pageshow', function () { delete form.dataset.sending; btn.disabled = false; btn.querySelector('[data-label]').textContent = 'Send Reset Link'; btn.querySelector('[data-spinner]').classList.add('hidden'); });
        })();
        </script>
    </x-slot:scripts>
</x-auth-shell>
