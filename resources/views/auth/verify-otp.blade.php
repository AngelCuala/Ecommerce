<x-auth-shell title="Verify Your Account — ALVY" heading="Verify Your Account" subtitle="Enter the 6-digit code we emailed you">
    <x-slot:head>
        <style>
            .otp-input { width:100%;border-radius:1rem;border:1.5px solid #E6D9CF;background:#FDF8F5;padding:1rem;font-size:1.9rem;font-weight:800;letter-spacing:.6em;text-indent:.6em;text-align:center;color:#222;outline:none;transition:border-color .15s,box-shadow .15s,background .15s; }
            .otp-input::placeholder { color:#e3d6cc; }
            .otp-input:focus { border-color:#fa4e1c;background:#fff;box-shadow:0 0 0 4px rgba(250,78,28,.12); }
            .otp-input.has-error { border-color:#DC2626; }
            .btn-primary { display:flex;width:100%;align-items:center;justify-content:center;gap:.5rem;border-radius:.75rem;padding:.85rem 1rem;font-size:.9rem;font-weight:700;color:#fff;
                           background:linear-gradient(135deg,#fa4e1c,#ff7a45);box-shadow:0 12px 24px -12px rgba(250,78,28,.8);transition:transform .15s,filter .15s; }
            .btn-primary:hover { transform:translateY(-1px);filter:brightness(1.03); }
        </style>
    </x-slot:head>

    <div class="px-6 pb-8 pt-6 sm:px-10 sm:pb-10">
        @if (session('success'))
            <div class="mb-5 flex items-start gap-2 rounded-xl border p-3 text-sm" style="background:#ECFDF5;border-color:rgba(5,150,105,.25);color:#059669;">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
                <p class="flex-1">{{ session('success') }}</p>
            </div>
        @endif

        <div class="mb-5 flex items-center gap-3 rounded-xl p-3.5" style="background:#fff5f3;">
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl" style="background:#fff;color:#fa4e1c;">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.9" viewBox="0 0 24 24"><rect x="3" y="5" width="18" height="14" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="m3 7 9 6 9-6"/></svg>
            </span>
            <p class="text-sm" style="color:#5a4d45;">
                Sent to <strong style="color:#222;">{{ $maskedEmail }}</strong>. The code expires in {{ $minutes }} minutes.
            </p>
        </div>

        <form action="{{ route('otp.verify') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="otp" class="sr-only">Verification Code</label>
                <input type="text" id="otp" name="otp" value="{{ old('otp') }}"
                       class="otp-input @error('otp') has-error @enderror"
                       inputmode="numeric" autocomplete="one-time-code" placeholder="••••••" autofocus required>
                @error('otp')
                    <p class="mt-2 flex items-center justify-center gap-1.5 text-sm" style="color:#DC2626;">
                        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        {{ $message }}
                    </p>
                @enderror
            </div>

            <button type="submit" class="btn-primary">
                Verify
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
            </button>
        </form>

        <form action="{{ route('otp.resend') }}" method="POST" class="mt-5 text-center text-sm" style="color:#8a7a70;">
            @csrf
            Didn't get the code?
            <button type="submit" id="resendBtn" data-cooldown="{{ $cooldown }}"
                    class="font-bold hover:underline disabled:cursor-not-allowed disabled:no-underline disabled:opacity-50" style="color:#fa4e1c;">
                Resend code
            </button>
            <span id="resendTimer"></span>
        </form>

        <p class="mt-6 border-t pt-5 text-center text-sm" style="border-color:#F0E4DC;color:#8a7a70;">
            Wrong account? <a href="{{ route('login') }}" class="font-bold hover:underline" style="color:#222;">Back to Login</a>
        </p>
    </div>

    <x-slot:scripts>
        <script>
        // Resend cooldown countdown
        (function () {
            var btn = document.getElementById('resendBtn');
            var timer = document.getElementById('resendTimer');
            var left = parseInt(btn.dataset.cooldown, 10) || 0;
            function tick() {
                if (left > 0) {
                    btn.disabled = true;
                    timer.textContent = '(in ' + left + 's)';
                    left--;
                    setTimeout(tick, 1000);
                } else {
                    btn.disabled = false;
                    timer.textContent = '';
                }
            }
            tick();
        })();
        </script>
    </x-slot:scripts>
</x-auth-shell>
