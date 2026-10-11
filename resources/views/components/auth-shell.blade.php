{{--
    Shared shell for the login / sign-up / verification pages:
    soft gradient background, one centred white card, ALVY header, optional
    Login | Sign up tabs, and a scroll-progress bar for the long sign-up forms.

    <x-auth-shell title="Sign In — ALVY" heading="Welcome back" subtitle="…" tab="login" width="narrow">
        <x-slot:head> extra <style> </x-slot:head>
        …card content…
        <x-slot:scripts> <script>… </x-slot:scripts>
    </x-auth-shell>
--}}
@props([
    'title'    => 'ALVY',
    'heading'  => '',
    'subtitle' => '',
    'tab'      => null,      // 'login' | 'signup' | null (no tabs)
    'width'    => 'narrow',  // 'narrow' | 'wide'
    'progress' => false,     // thin scroll-progress bar at the top of the page
    'shopLink' => true,      // "Back to shop" button (off for seller/admin-only pages)
])
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="{{ asset('js/vendor/tailwindcss.js') }}"></script>
    <style>
        body { font-family:'Inter',ui-sans-serif,system-ui; color:#222; }
        .font-display { font-family:'Nunito',ui-sans-serif,system-ui; }

        .auth-bg {
            background:
                radial-gradient(900px 520px at 0% -10%, #ffd6c2 0%, rgba(255,214,194,0) 60%),
                radial-gradient(800px 480px at 100% 0%, #ffe8d6 0%, rgba(255,232,214,0) 55%),
                radial-gradient(700px 500px at 50% 110%, #ffe2d3 0%, rgba(255,226,211,0) 60%),
                linear-gradient(180deg, #fff8f4 0%, #fbeee8 100%);
        }
        .auth-blob { position:fixed; border-radius:9999px; filter:blur(60px); opacity:.55; pointer-events:none; z-index:0; }

        .auth-card {
            background:#fff;
            border:1px solid #F3E6DD;
            border-radius:1.75rem;
            box-shadow: 0 1px 2px rgba(58,47,41,.04), 0 30px 80px -32px rgba(58,47,41,.35);
        }
        .auth-logo {
            display:inline-flex; align-items:center; justify-content:center;
            width:3.5rem; height:3.5rem; border-radius:1.1rem; overflow:hidden; background:#fff;
            box-shadow: 0 0 0 4px #fff1ee, 0 12px 24px -12px rgba(250,78,28,.6);
        }

        /* Log In / Sign Up: plain text tabs with an underline on the current page */
        .auth-tabs { display:flex; justify-content:center; gap:2rem; border-bottom:1px solid #F0E4DC; }
        .auth-tab { position:relative; display:block; cursor:default; user-select:none; padding:.6rem .25rem; font-size:.9rem; font-weight:600; color:#a39890; }
        .auth-tab.is-active { color:#222; }
        .auth-tab.is-active::after { content:""; position:absolute; left:0; right:0; bottom:-1px; height:2px; border-radius:2px; background:#fa4e1c; }

        .auth-progress { position:fixed; top:0; left:0; height:3px; width:0; z-index:60;
                         background:linear-gradient(90deg,#fa4e1c,#ff8a5b); transition:width .1s linear; }
    </style>
    {{ $head ?? '' }}
</head>
<body class="auth-bg min-h-screen">

@if ($progress)
    <div class="auth-progress" id="authProgress"></div>
@endif

<span class="auth-blob" style="width:340px;height:340px;left:-120px;top:30%;background:#ffc3a8;"></span>
<span class="auth-blob" style="width:300px;height:300px;right:-100px;bottom:5%;background:#ffd9a8;"></span>

<div class="relative z-10 flex min-h-screen flex-col">

    {{-- Top bar --}}
    <header class="mx-auto flex w-full max-w-6xl items-center justify-between px-4 py-5 sm:px-6">
        <a href="{{ $shopLink ? route('home') : '#' }}" class="flex items-center gap-2.5">
            <span class="inline-flex h-9 w-9 items-center justify-center overflow-hidden rounded-xl bg-white shadow-sm">
                <img src="{{ asset('images/logo-mark.png') }}" alt="" class="h-full w-full object-contain" style="padding:5px;">
            </span>
            <span class="font-display text-xl font-extrabold tracking-tight" style="color:#222;">ALVY</span>
        </a>
        @if ($shopLink)
            <a href="{{ route('home') }}" class="inline-flex items-center gap-1.5 rounded-full bg-white/70 px-3.5 py-1.5 text-sm font-semibold shadow-sm transition hover:bg-white" style="color:#5a4d45;">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 12H5m6 6-6-6 6-6"/></svg>
                Back to shop
            </a>
        @else
            {{ $topRight ?? '' }}
        @endif
    </header>

    {{-- Card --}}
    <main class="flex flex-1 items-start justify-center px-4 pb-10 pt-2 sm:items-center sm:px-6">
        <div class="w-full {{ $width === 'wide' ? 'max-w-3xl' : 'max-w-md' }}">
            <div class="auth-card">
                <div class="px-6 pt-8 sm:px-10 sm:pt-10 {{ $width === 'wide' ? '' : 'pb-2' }}">
                    <div class="text-center">
                        <a href="{{ $shopLink ? route('home') : '#' }}" class="auth-logo">
                            <img src="{{ asset('images/logo-mark.png') }}" alt="ALVY" class="h-full w-full object-contain" style="padding:8px;">
                        </a>
                        @if ($heading)
                            <h1 class="mt-5 font-display text-[1.75rem] font-extrabold leading-tight sm:text-3xl" style="color:#222;">{{ $heading }}</h1>
                        @endif
                        @if ($subtitle)
                            <p class="mt-1.5 text-sm" style="color:#8a7a70;">{{ $subtitle }}</p>
                        @endif
                    </div>

                    @if ($tab)
                        {{-- Labels only; each page links to the other in its own footer. --}}
                        <div class="auth-tabs mx-auto mt-6 max-w-xs" aria-hidden="true">
                            <span class="auth-tab {{ $tab === 'login' ? 'is-active' : '' }}">Log In</span>
                            <span class="auth-tab {{ $tab === 'signup' ? 'is-active' : '' }}">Sign Up</span>
                        </div>
                    @endif
                </div>

                {{ $slot }}
            </div>

            <p class="mt-6 text-center text-xs" style="color:#a3948a;">
                &copy; {{ date('Y') }} ALVY ·
                <a href="{{ route('policies.show', 'terms') }}" class="hover:underline">Terms</a> ·
                <a href="{{ route('policies.show', 'privacy') }}" class="hover:underline">Privacy</a>
            </p>
        </div>
    </main>
</div>

@if ($progress)
<script>
(function () {
    var bar = document.getElementById('authProgress');
    function update() {
        var max = document.documentElement.scrollHeight - window.innerHeight;
        bar.style.width = (max > 0 ? Math.min(100, (window.scrollY / max) * 100) : 0) + '%';
    }
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
})();
</script>
@endif
{{ $scripts ?? '' }}
<script src="{{ asset('js/prevent-back.js') }}"></script>
</body>
</html>
