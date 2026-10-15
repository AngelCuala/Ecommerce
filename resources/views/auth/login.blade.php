<x-auth-shell title="Sign In — ALVY" heading="Welcome back 👋" subtitle="Log in to continue shopping on ALVY" tab="login">
    <x-slot:head>
        <style>
            .fld { width:100%;border-radius:.75rem;border:1px solid #E6D9CF;background:#fff;padding:.8rem 1rem;font-size:.9rem;color:#222;outline:none;transition:border-color .15s,box-shadow .15s; }
            .fld::placeholder { color:#b5a79d; }
            .fld:focus { border-color:#fa4e1c;box-shadow:0 0 0 4px rgba(250,78,28,.12); }
            .fld-label { display:block;margin-bottom:.4rem;font-size:.8rem;font-weight:600;color:#3a2f29; }
            .btn-primary { display:flex;width:100%;align-items:center;justify-content:center;gap:.5rem;border-radius:.75rem;padding:.85rem 1rem;font-size:.9rem;font-weight:700;color:#fff;
                           background:linear-gradient(135deg,#fa4e1c,#ff7a45);box-shadow:0 12px 24px -12px rgba(250,78,28,.8);transition:transform .15s,box-shadow .15s,filter .15s; }
            .btn-primary:hover { transform:translateY(-1px);filter:brightness(1.03);box-shadow:0 16px 28px -12px rgba(250,78,28,.85); }
            .btn-google { display:flex;width:100%;align-items:center;justify-content:center;gap:.65rem;border-radius:.75rem;border:1px solid #E6D9CF;background:#fff;padding:.8rem 1rem;font-size:.9rem;font-weight:600;color:#3a2f29;transition:border-color .15s,background .15s,box-shadow .15s; }
            .btn-google:hover { border-color:#d6c6bb;background:#FCFAF8;box-shadow:0 4px 12px -6px rgba(58,47,41,.25); }
        </style>
    </x-slot:head>

    <div class="px-6 pb-8 pt-6 sm:px-10 sm:pb-10">
        @if ($errors->any() || session('error'))
            <div class="mb-5 flex items-start gap-2 rounded-xl border p-3 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <p class="flex-1">{{ $errors->first() ?: session('error') }}</p>
            </div>
        @endif
        @if (session('success'))
            <div class="mb-5 flex items-start gap-2 rounded-xl border p-3 text-sm" style="background:#ECFDF5;border-color:rgba(5,150,105,.25);color:#059669;">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
                <p class="flex-1">{{ session('success') }}</p>
            </div>
        @endif

        <a href="{{ route('auth.google') }}" class="btn-google">
            <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true">
                <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.1 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/>
                <path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.1 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
                <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-7.9l-6.5 5C9.5 39.6 16.2 44 24 44z"/>
                <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/>
            </svg>
            Continue with Google
        </a>

        <div class="my-5 flex items-center gap-3 text-xs" style="color:#b5a79d;">
            <span class="h-px flex-1" style="background:#F0E4DC;"></span> or log in with email <span class="h-px flex-1" style="background:#F0E4DC;"></span>
        </div>

        <form action="{{ route('login') }}" method="POST" class="space-y-5">
            @csrf

            <div>
                <label class="fld-label" for="email">Email Address</label>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2" style="color:#b5a79d;" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 6 9-6"/></svg>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" class="fld pl-11" required autofocus
                           autocomplete="email" placeholder="example@gmail.com">
                </div>
            </div>

            <div>
                <div class="mb-1.5 flex items-center justify-between">
                    <label class="fld-label !mb-0" for="password">Password</label>
                    <a href="#" class="text-xs font-semibold hover:underline" style="color:#fa4e1c;">Forgot password?</a>
                </div>
                <div class="relative">
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 h-[18px] w-[18px] -translate-y-1/2" style="color:#b5a79d;" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><rect x="4" y="11" width="16" height="10" rx="2"/><path stroke-linecap="round" d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    <input type="password" id="password" name="password" data-password class="fld pl-11 pr-11" required
                           autocomplete="current-password" placeholder="••••••••">
                    <button type="button" data-toggle-password aria-label="Show password"
                            class="absolute right-3 top-1/2 -translate-y-1/2" style="color:#8a7a70;"></button>
                </div>
            </div>

            <label class="flex cursor-pointer items-center gap-2 text-sm" style="color:#5a4d45;">
                <input type="checkbox" name="remember" class="h-4 w-4 rounded" style="accent-color:#fa4e1c;"> Keep me logged in
            </label>

            <button type="submit" class="btn-primary">
                Log In
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
        </form>

        <div class="my-6 flex items-center gap-3 text-xs" style="color:#b5a79d;">
            <span class="h-px flex-1" style="background:#F0E4DC;"></span> New to ALVY? <span class="h-px flex-1" style="background:#F0E4DC;"></span>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <a href="{{ route('register') }}" class="flex items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-semibold transition hover:border-[#fa4e1c] hover:bg-[#fff5f3]" style="border-color:#E6D9CF;color:#3a2f29;">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                Create account
            </a>
            <a href="{{ route('seller.register') }}" class="flex items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-semibold transition hover:border-[#fa4e1c] hover:bg-[#fff5f3]" style="border-color:#E6D9CF;color:#3a2f29;">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9l1-5h16l1 5M4 9h16v10a1 1 0 01-1 1H5a1 1 0 01-1-1V9z"/></svg>
                Sell on ALVY
            </a>
        </div>
    </div>

    <x-slot:scripts>
        @include('partials.password-toggle')
    </x-slot:scripts>
</x-auth-shell>
