<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In — ALVY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family:'Inter',ui-sans-serif,system-ui; }
        .font-display { font-family:'Nunito',ui-sans-serif,system-ui; }
        .fld { width:100%;border-radius:.5rem;border:1px solid #E6D9CF;background:#fff;padding:.7rem 1rem;font-size:.9rem;color:#222;outline:none;transition:border-color .15s,box-shadow .15s; }
        .fld:focus { border-color:#fa4e1c;box-shadow:0 0 0 3px rgba(250,78,28,.12); }
    </style>
</head>
<body style="background:#fbeee8;">

<div class="min-h-screen flex items-center justify-center p-4 sm:p-8">
    <div class="grid w-full max-w-5xl overflow-hidden rounded-2xl shadow-2xl md:grid-cols-2"
         style="background:#fff;min-height:560px;">

        {{-- ══════════ LEFT: image ══════════ --}}
        <div class="relative hidden md:block">
            <img src="{{ asset('images/photo1.png') }}" alt="ALVY"
                 class="absolute inset-0 h-full w-full object-cover">
            <div class="absolute inset-0" style="background:linear-gradient(135deg,rgba(250,78,28,.15),rgba(0,43,77,.25));"></div>
        </div>

        {{-- ══════════ RIGHT: form ══════════ --}}
        <div class="flex flex-col justify-center px-8 py-12 sm:px-12" style="background:#FDF8F5;">

            <div class="mx-auto w-full max-w-sm">
                <div class="text-center">
                    <a href="{{ route('home') }}" class="mx-auto mb-4 inline-flex h-12 w-12 items-center justify-center overflow-hidden rounded-xl">
                        <img src="{{ asset('images/logo.png') }}" alt="ALVY" class="h-full w-full object-cover" style="transform:scale(1.4);">
                    </a>
                    <h1 class="font-display text-3xl font-extrabold" style="color:#222;">Login</h1>
                    <p class="mt-1 text-sm" style="color:#8a7a70;">Enter your details to login</p>
                </div>

                @if ($errors->any())
                    <div class="mt-6 rounded-lg border p-3 text-sm" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#DC2626;">
                        {{ $errors->first() }}
                    </div>
                @endif
                @if (session('success'))
                    <div class="mt-6 rounded-lg border p-3 text-sm" style="background:#ECFDF5;border-color:rgba(5,150,105,.25);color:#059669;">
                        {{ session('success') }}
                    </div>
                @endif

                <form action="{{ route('login') }}" method="POST" class="mt-8 space-y-5">
                    @csrf

                    <div>
                        <label class="mb-1.5 block text-sm font-semibold" style="color:#3a2f29;">Email Address</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="fld" required autofocus
                               placeholder="example@gmail.com">
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-semibold" style="color:#3a2f29;">Password</label>
                        <div class="relative">
                            <input type="password" name="password" data-password class="fld pr-11" required placeholder="••••••••">
                            <button type="button" data-toggle-password aria-label="Show password"
                                    class="absolute right-3 top-1/2 -translate-y-1/2" style="color:#8a7a70;"></button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between text-sm">
                        <label class="flex items-center gap-2" style="color:#5a4d45;">
                            <input type="checkbox" name="remember" style="accent-color:#fa4e1c;"> Remember Me
                        </label>
                        <a href="#" class="font-semibold" style="color:#fa4e1c;"
                           onmouseover="this.style.textDecoration='underline';" onmouseout="this.style.textDecoration='none';">Forgot Your Password?</a>
                    </div>

                    <button type="submit"
                            class="flex w-full items-center justify-center gap-2 rounded-lg py-3 text-sm font-bold text-white transition"
                            style="background:#fa4e1c;"
                            onmouseover="this.style.background='#E14F00';" onmouseout="this.style.background='#fa4e1c';">
                        Log In
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </button>
                </form>

                <p class="mt-8 text-center text-sm" style="color:#8a7a70;">
                    Don't have an account?
                    <a href="{{ route('register') }}" class="font-bold" style="color:#222;"
                       onmouseover="this.style.color='#fa4e1c';" onmouseout="this.style.color='#222';">Create an Account</a>
                </p>
            </div>
        </div>
    </div>
</div>

@include('partials.password-toggle')
</body>
</html>
