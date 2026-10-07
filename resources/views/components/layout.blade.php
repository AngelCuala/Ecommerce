@props(['title' => 'ALVY — Online Shopping'])
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            fontFamily: {
              sans:    ['"Inter"', 'ui-sans-serif', 'system-ui'],
              display: ['"Nunito"', 'ui-sans-serif', 'system-ui'],
            },
            colors: {
              // ── Shopee Orange Palette ──────────────
              latte:     '#fa4e1c',   // main accent (was warm brown)
              'latte-light': '#fb7048',
              'latte-dark':  '#d93d0e',

              mocha:     '#fa4e1c',   // secondary accent / hover-red
              'mocha-light': '#fa4e1c',

              espresso:  '#002b4d',   // darkest text

              parchment: '#F5F5F5',   // page background
              'parchment-dim': '#EFEFEF',
              'parchment-deep':'#E0E0E0',

              sand:      '#fff1ee',   // light orange tint (badges, highlights)
              'sand-light':'#fff5f3',

              rose:      '#fa4e1c',
              'rose-light':'#fa4e1c',

              // text scale
              ink:       '#002b4d',
              'ink-muted':'#4a7a94',
              'ink-faint':'#6b90aa',

              border:  '#EFEFEF',
            },
          },
        },
      }
    </script>
    <style>
      *, *::before, *::after { box-sizing: border-box; }

      body {
        background-color: #F5F5F5;
        color: #002b4d;
        font-family: 'Inter', ui-sans-serif, system-ui;
        -webkit-font-smoothing: antialiased;
        font-weight: 400;
        line-height: 1.55;
      }

      h1,h2,h3,h4,.font-display {
        font-family: 'Nunito', ui-sans-serif, system-ui;
        color: #002b4d;
        font-weight: 800;
        line-height: 1.2;
      }

      p, li, td, th, label, span, a { color: inherit; }

      /* text scale */
      .text-primary   { color: #002b4d; }
      .text-secondary { color: #4a7a94; }
      .text-muted     { color: #6b90aa; }

      /* ── Top accent bar ─────────────────────────────── */
      .top-bar {
        height: 3px;
        background: #fa4e1c;
      }

      /* ── Primary button ─────────────────────────────── */
      .btn-primary, .btn-gold {
        display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
        border-radius: .375rem;
        background: #fa4e1c;
        padding: .75rem 1.75rem;
        font-family: 'Inter', sans-serif;
        font-weight: 700; font-size: .875rem; letter-spacing: .01em;
        color: #FFFFFF;
        border: 1px solid transparent;
        box-shadow: 0 2px 8px rgba(250,78,28,.28);
        transition: background .18s, transform .15s, box-shadow .15s;
        text-decoration: none;
      }
      .btn-primary:hover, .btn-gold:hover {
        background: #d93d0e;
        transform: translateY(-1px);
        box-shadow: 0 6px 16px rgba(250,78,28,.35);
      }
      .btn-primary:active, .btn-gold:active { transform: translateY(0); }
      .btn-primary:disabled, .btn-gold:disabled { opacity: .5; cursor: not-allowed; transform: none; }

      /* ── Outline button ─────────────────────────────── */
      .btn-outline, .btn-navy-outline {
        display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
        border-radius: .375rem;
        border: 1.5px solid #fa4e1c;
        padding: .75rem 1.75rem;
        font-family: 'Inter', sans-serif;
        font-weight: 700; font-size: .875rem; letter-spacing: .01em;
        color: #fa4e1c;
        background: transparent;
        transition: background .18s, color .18s, border-color .18s;
        text-decoration: none;
      }
      .btn-outline:hover, .btn-navy-outline:hover {
        background: #fa4e1c;
        color: #FFFFFF;
        border-color: #fa4e1c;
      }

      /* ── Cards ──────────────────────────────────────── */
      .card {
        border-radius: .5rem;
        background: #FFFFFF;
        border: 1px solid #EFEFEF;
        box-shadow: 0 1px 4px rgba(0,0,0,.04);
        transition: box-shadow .2s ease, transform .2s ease, border-color .2s ease;
      }
      .card:hover {
        box-shadow: 0 6px 20px rgba(0,0,0,.08);
        border-color: #fdb49e;
        transform: translateY(-2px);
      }

      /* ── Inputs ─────────────────────────────────────── */
      .input {
        width: 100%;
        border-radius: .375rem;
        border: 1px solid #E0E0E0;
        background: #FFFFFF;
        padding: .65rem 1rem;
        font-family: 'Inter', sans-serif;
        font-size: .875rem;
        color: #002b4d;
        outline: none;
        transition: border-color .15s, box-shadow .15s;
      }
      .input:focus {
        border-color: #fa4e1c;
        box-shadow: 0 0 0 3px rgba(250,78,28,.15);
        background: #fff;
      }
      .input::placeholder { color: #6b90aa; }

      /* ── Section eyebrow ────────────────────────────── */
      .section-eyebrow {
        display: block;
        font-family: 'Inter', sans-serif;
        font-size: .6875rem;
        font-weight: 700;
        letter-spacing: .16em;
        text-transform: uppercase;
        color: #fa4e1c;
      }

      /* ── Utilities ──────────────────────────────────── */
      .line-clamp-2 { display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden; }
      .accent-latte  { accent-color: #fa4e1c; }

      /* legacy aliases still used in views */
      .text-navy        { color: #002b4d; }
      .text-gold        { color: #fa4e1c; }
      .text-gold-dark   { color: #d93d0e; }
      .text-ink\/50     { color: #6b90aa; }
      .text-ink\/60     { color: #6b90aa; }
      .text-ink\/70     { color: #4a7a94; }
      .bg-cream         { background-color: #F5F5F5; }
      .bg-cream-dim     { background-color: #EFEFEF; }
      .text-oxblood     { color: #D0021B; }
      .border-navy\/8   { border-color: rgba(34,34,34,.08); }
      .divide-navy\/8 > * + * { border-color: rgba(34,34,34,.08); }

      /* ── Scrollbar ──────────────────────────────────── */
      ::-webkit-scrollbar { width: 6px; height: 6px; }
      ::-webkit-scrollbar-track { background: #F5F5F5; }
      ::-webkit-scrollbar-thumb { background: #fc8e6e; border-radius: 999px; }

      /* ── Animations ─────────────────────────────────── */
      @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.45} }
      .animate-pulse { animation: pulse 2s cubic-bezier(.4,0,.6,1) infinite; }

      @keyframes fadeUp { from{opacity:0;transform:translateY(12px)} to{opacity:1;transform:translateY(0)} }
      .fade-up { animation: fadeUp .45s ease forwards; }

      @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { transition-duration:.001ms!important; animation-duration:.001ms!important; }
      }
    </style>
    <link rel="stylesheet" href="{{ asset('css/hover-effects.css') }}">
</head>
<body class="min-h-screen flex flex-col" style="background:#F5F5F5;color:#002b4d;">

<div class="top-bar"></div>

@include('partials.nav')

{{-- Toast notifications (pop-up, auto-dismiss) --}}
@if (session('success') || $errors->any())
    <div id="toast-stack" class="fixed right-4 top-20 z-[100] flex w-full max-w-sm flex-col gap-2 px-2 sm:px-0">
        @if (session('success'))
            <div class="toast flex items-start gap-3 rounded-xl border p-4 shadow-lg"
                 style="background:#fff;border-color:rgba(5,150,105,.25);">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="#059669" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 6 9 17l-5-5"/></svg>
                <p class="flex-1 text-sm font-semibold" style="color:#065F46;">{{ session('success') }}</p>
                <button type="button" data-toast-close class="shrink-0 text-lg leading-none btn-hover-danger" style="color:#9CA3AF;" aria-label="Dismiss">&times;</button>
            </div>
        @endif
        @if ($errors->any())
            <div class="toast flex items-start gap-3 rounded-xl border p-4 shadow-lg"
                 style="background:#fff;border-color:rgba(208,2,27,.25);">
                <svg class="mt-0.5 h-5 w-5 shrink-0" fill="none" stroke="#D0021B" stroke-width="2.2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 8v5M12 16h.01"/></svg>
                <p class="flex-1 text-sm font-semibold" style="color:#B91C1C;">{{ $errors->first() }}</p>
                <button type="button" data-toast-close class="shrink-0 text-lg leading-none btn-hover-danger" style="color:#9CA3AF;" aria-label="Dismiss">&times;</button>
            </div>
        @endif
    </div>
    <script src="{{ asset('js/toast.js') }}"></script>
@endif

<script src="{{ asset('js/Buyer.js') }}"></script>

<main class="flex-1">{{ $slot }}</main>

@include('partials.footer')
@include('partials.chat-widget')
<script src="{{ asset('js/prevent-back.js') }}"></script>
</body>
</html>
