<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>LogiSort Login — ALVY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="{{ asset('js/vendor/tailwindcss.js') }}"></script>
    <style>
        body { font-family: 'Inter', ui-sans-serif, system-ui; }
        .font-display { font-family: 'Nunito', ui-sans-serif, system-ui; }
        .fld { width: 100%; border-radius: .65rem; border: 1px solid #E2E8F0; background: #fff; padding: .7rem .9rem .7rem 2.5rem; font-size: .9rem; color: #1f2937; outline: none; transition: border-color .15s, box-shadow .15s; }
        .fld:focus { border-color: #fa4e1c; box-shadow: 0 0 0 3px rgba(250,78,28,.12); }
        .fld-ic { position: absolute; left: .8rem; top: 50%; transform: translateY(-50%); color: #94A3B8; pointer-events: none; }
        .brand-panel { background:
            radial-gradient(120% 90% at 100% 0%, rgba(250,78,28,.55) 0%, rgba(250,78,28,0) 55%),
            linear-gradient(160deg, #002b4d 0%, #0b4a7a 65%, #12395c 100%); }
        .brand-panel::after { content: ""; position: absolute; right: -70px; bottom: -70px; width: 260px; height: 260px; border-radius: 999px; border: 34px solid rgba(255,255,255,.06); }
        .feat-ic { display: flex; width: 36px; height: 36px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 10px; background: rgba(255,255,255,.12); color: #FFB59A; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/password-field.css') }}">
</head>
<body style="background:#F1F5F9;">

<div class="flex min-h-screen items-center justify-center p-4 sm:p-6">
    <div class="grid w-full max-w-5xl overflow-hidden rounded-2xl bg-white shadow-2xl md:grid-cols-[1.05fr_1fr]">

        {{-- ══════════ LEFT: LogiSort brand ══════════ --}}
        <div class="brand-panel relative hidden flex-col justify-between overflow-hidden p-10 text-white md:flex">
            <a href="{{ route('home') }}" class="relative z-[1] flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-white p-1.5 shadow-lg"><img src="{{ asset('images/logo-mark.png') }}" alt="" class="h-full w-full object-contain"></span>
                <span>
                    <span class="font-display block text-2xl font-extrabold leading-none">LogiSort</span>
                    <span class="text-xs font-semibold tracking-wide text-white/70">by ALVY</span>
                </span>
            </a>

            <div class="relative z-[1] my-10">
                <h2 class="font-display text-3xl font-extrabold leading-tight">Run your hub,<br>parcel to doorstep.</h2>
                <p class="mt-3 max-w-sm text-sm text-white/75">The sorting center panel for ALVY partners: receive, sort, dispatch and track every parcel in your area.</p>

                <ul class="mt-8 space-y-4">
                    @foreach ([
                        ['Scan & receive', 'Open any parcel from its waybill QR code.', '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2"/><rect x="7" y="7" width="10" height="10" rx="1"/>'],
                        ['Sort by barangay', 'Parcels are matched to your coverage areas.', '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>'],
                        ['Dispatch riders', 'Assign deliveries and follow them live.', '<circle cx="5.5" cy="17.5" r="3"/><circle cx="18.5" cy="17.5" r="3"/><path d="M15 6h3l2.5 6M5.5 17.5 9 9h6l3.5 8.5M9 9 7 6H4"/>'],
                        ['Reports & COD', 'Daily numbers and cash collected, as PDF.', '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/>'],
                    ] as [$title, $text, $icon])
                        <li class="flex items-start gap-3">
                            <span class="feat-ic"><svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">{!! $icon !!}</svg></span>
                            <span><span class="block text-sm font-bold">{{ $title }}</span><span class="block text-xs text-white/65">{{ $text }}</span></span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <p class="relative z-[1] text-xs text-white/50">© {{ date('Y') }} ALVY · For approved sorting center partners</p>
        </div>

        {{-- ══════════ RIGHT: form ══════════ --}}
        <div class="flex flex-col justify-center px-6 py-10 sm:px-12">
            <div class="mx-auto w-full max-w-sm">
                {{-- Small screens: the brand on top --}}
                <div class="mb-6 flex items-center gap-2 md:hidden">
                    <img src="{{ asset('images/logo-mark.png') }}" alt="" class="h-9 w-9 object-contain">
                    <span class="font-display text-xl font-extrabold" style="color:#002b4d;">LogiSort</span>
                </div>

                <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold" style="background:#FFF1EB;color:#d93d0e;">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 9l1.5-5h15L21 9M3 9h18M5 12v8h14v-8"/></svg>
                    Sorting center
                </span>
                <h1 class="font-display mt-3 text-3xl font-extrabold" style="color:#0f172a;">Welcome back</h1>
                <p class="mt-1 text-sm" style="color:#64748b;">Sign in to manage your sorting center.</p>

                @if ($errors->any())
                    <div class="mt-5 flex items-start gap-2 rounded-lg border p-3 text-sm" role="alert" style="background:#FEF2F2;border-color:rgba(220,38,38,.2);color:#B91C1C;">
                        <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 8v4m0 4h.01"/></svg>
                        {{ $errors->first() }}
                    </div>
                @endif
                @if (session('success'))
                    <div class="mt-5 rounded-lg border p-3 text-sm" role="status" style="background:#ECFDF5;border-color:rgba(5,150,105,.25);color:#047857;">{{ session('success') }}</div>
                @endif

                <form action="{{ route('sc.login.store') }}" method="POST" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label for="email" class="mb-1.5 block text-sm font-semibold" style="color:#334155;">Email address</label>
                        <div class="relative">
                            <svg class="fld-ic" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" class="fld" required autofocus autocomplete="username" placeholder="hub@example.com">
                        </div>
                    </div>

                    <div>
                        <div class="mb-1.5 flex items-center justify-between">
                            <label for="password" class="text-sm font-semibold" style="color:#334155;">Password</label>
                            <a href="{{ route('password.request') }}" class="text-xs font-semibold hover:underline" style="color:#fa4e1c;">Forgot password?</a>
                        </div>
                        <div class="relative">
                            <svg class="fld-ic" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                            <input id="password" type="password" name="password" data-password class="fld pr-11" required autocomplete="current-password" placeholder="Your password">
                            <button type="button" data-toggle-password aria-label="Show password" class="absolute right-3 top-1/2 -translate-y-1/2" style="color:#94A3B8;"></button>
                        </div>
                    </div>

                    <label class="flex w-fit cursor-pointer items-center gap-2 text-sm" style="color:#475569;">
                        <input type="checkbox" name="remember" class="h-4 w-4" style="accent-color:#fa4e1c;" @checked(old('remember'))> Keep me signed in
                    </label>

                    <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-lg py-3 text-sm font-bold text-white shadow-lg transition hover:brightness-105"
                            style="background:linear-gradient(90deg,#fa4e1c,#e2400f);box-shadow:0 10px 20px -10px rgba(250,78,28,.8);">
                        Sign in to LogiSort
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14M13 6l6 6-6 6"/></svg>
                    </button>
                </form>

                <p class="mt-5 text-center text-sm">
                    <a href="{{ route('login') }}" class="font-semibold hover:underline" style="color:#64748b;">&larr; Back to main login</a>
                </p>
            </div>
        </div>
    </div>
</div>

@include('partials.password-toggle')
<script src="{{ asset('js/prevent-back.js') }}"></script>
</body>
</html>
