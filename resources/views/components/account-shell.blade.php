@props([
    'title' => 'My Account',
    'subtitle' => null,
    'portal' => 'Account',      // breadcrumb start, e.g. "Seller Centre"
    'nav' => [],                // [['label' => ..., 'href' => ..., 'icon' => ..., 'active' => bool, 'hint' => optional], ...]
    'badges' => [],             // [['text' => ..., 'tone' => 'orange|green|blue|gray|red'], ...]
    'name' => null,
    'email' => null,
    'signOut' => false,         // the red Sign Out button (shown on the main account page, like the buyer's My Profile)
    'logoutUrl' => null,        // a portal's own sign-out address (e.g. the sorting center's), else this portal's logout
])
{{--
    The account pages of every portal (seller, sorting center, rider) share the buyer's account layout:
    a title with the red Sign Out button at the top right, a profile card with the photo and the account menu on the left,
    and the page's cards on the right. Self-contained styles (acs-*), so it looks the same in every portal's layout.
    Cards inside use: .acs-card, .acs-card-head (.acs-icon + h2 + p), .acs-tiles/.acs-tile, .acs-label, .acs-input,
    .acs-btn, .acs-btn-ghost, .acs-badge(-green|-blue|-gray|-red), .acs-alert(-error|-success), .acs-note.
--}}
@php
    $u = auth()->user();
    $name ??= $u->name;
    $email ??= $u->email;
    $icons = [
        'user'  => '<circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>',
        'lock'  => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'bell'  => '<path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 01-3.4 0"/>',
        'store' => '<path d="M3 9l1.5-5h15L21 9M3 9h18M3 9a3 3 0 006 0 3 3 0 006 0 3 3 0 006 0M5 12v8h14v-8"/>',
        'pin'   => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'truck' => '<path d="M3 7h11v9H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17" cy="17.5" r="1.8"/>',
        'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6M16 4.5a3.5 3.5 0 010 7M21.5 20c0-2.8-1.7-4.9-4-5.7"/>',
        'pay'   => '<rect x="2" y="6" width="20" height="13" rx="2"/><path d="M2 10h20M6 15h4"/>',
        'box'   => '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="M3.3 7L12 12l8.7-5M12 22V12"/>',
        'doc'   => '<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8l-5-5zM14 3v5h5M9 13h6M9 17h4"/>',
        'chart' => '<path d="M3 3v18h18M7 14l4-4 3 3 5-6"/>',
    ];
@endphp
<style>
    .acs { --acs-line: #F0E4DC; --acs-muted: #8a7a70; --acs-text: #222; --acs-soft: #FDF8F5; --acs-orange: #fa4e1c;
           font-family: inherit; color: var(--acs-text); }
    .acs-top { display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 12px; margin-bottom: 22px; }
    .acs-crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin: 0 0 4px; font-size: 12px; color: var(--acs-muted); }
    .acs-crumbs a { color: inherit; text-decoration: none; }
    .acs-crumbs a:hover { text-decoration: underline; }
    .acs-crumbs b { font-weight: 500; color: #3a2f29; }
    .acs-top h1 { margin: 0; font-family: 'Nunito', 'Inter', ui-sans-serif, system-ui, sans-serif; font-size: 28px; font-weight: 800; line-height: 1.15; color: var(--acs-text); }
    .acs-top p { margin: 4px 0 0; font-size: 14px; color: var(--acs-muted); }
    .acs-signout { display: inline-flex; align-items: center; gap: 8px; border: 0; border-radius: 8px; padding: 9px 16px; background: #DC2626; color: #fff;
                   font: inherit; font-size: 14px; font-weight: 700; cursor: pointer; box-shadow: 0 8px 18px -10px rgba(220,38,38,.8); transition: opacity .15s; }
    .acs-signout:hover { opacity: .9; }
    .acs-grid { display: grid; gap: 24px; grid-template-columns: 288px minmax(0, 1fr); align-items: start; }
    @media (max-width: 960px) { .acs-grid { grid-template-columns: 1fr; } }
    .acs-main { display: grid; gap: 20px; min-width: 0; }

    .acs-card { background: #fff; border: 1px solid var(--acs-line); border-radius: 20px; box-shadow: 0 1px 2px rgba(58,47,41,.04), 0 10px 30px -18px rgba(58,47,41,.18); }
    .acs-pad { padding: 24px 26px; }
    @media (max-width: 560px) { .acs-pad { padding: 18px; } }
    .acs-card-head { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; }
    .acs-card-head > div { display: flex; align-items: center; gap: 12px; min-width: 0; }
    .acs-card-head h2 { margin: 0; font-family: 'Nunito', 'Inter', ui-sans-serif, system-ui, sans-serif; font-size: 17px; font-weight: 800; color: var(--acs-text); }
    .acs-card-head p { margin: 2px 0 0; font-size: 12.5px; color: var(--acs-muted); }
    .acs-icon { display: inline-flex; flex: none; width: 40px; height: 40px; align-items: center; justify-content: center; border-radius: 12px; background: #fff1ee; color: var(--acs-orange); }
    .acs-tiles { display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); margin: 0; }
    .acs-tile { min-width: 0; border-radius: 12px; padding: 13px 14px; background: var(--acs-soft); border: 1px solid #F5EBE4; text-decoration: none; }
    .acs-tile.wide { grid-column: 1 / -1; }
    .acs-tile dt, .acs-tile .k { font-size: 11px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--acs-muted); }
    .acs-tile dd, .acs-tile .v { margin: 4px 0 0; font-size: 14px; font-weight: 700; color: var(--acs-text); overflow-wrap: anywhere; }
    .acs-tile small { display: block; margin-top: 2px; font-size: 12px; font-weight: 500; color: var(--acs-muted); }
    a.acs-tile:hover { border-color: #FAD7C8; background: #FFF5F1; }
    .acs-label { display: block; margin-bottom: 6px; font-size: 12px; font-weight: 600; color: #3a2f29; }
    .acs-input { box-sizing: border-box; width: 100%; height: 42px; border-radius: 10px; border: 1px solid #E6D9CF; background: #fff; padding: 0 14px; font: inherit; font-size: 14px; color: var(--acs-text); outline: none; transition: border-color .15s, box-shadow .15s; }
    textarea.acs-input { height: auto; padding: 10px 14px; }
    select.acs-input { padding-right: 32px; }
    .acs-input::placeholder { color: #b5a79d; }
    .acs-input:focus { border-color: var(--acs-orange); box-shadow: 0 0 0 3px rgba(250,78,28,.12); }
    .acs-input:disabled { background: #f7f1ed; color: var(--acs-muted); cursor: not-allowed; }
    .acs-fields { display: grid; gap: 16px; grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .acs-fields .wide { grid-column: 1 / -1; }
    @media (max-width: 640px) { .acs-fields { grid-template-columns: 1fr; } }
    .acs-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: 0; border-radius: 10px; padding: 10px 22px; background: var(--acs-orange); color: #fff;
               font: inherit; font-size: 14px; font-weight: 700; cursor: pointer; text-decoration: none; box-shadow: 0 8px 18px -10px rgba(250,78,28,.7); transition: background .15s; }
    .acs-btn:hover { background: #E14F00; }
    .acs-btn:disabled { background: #F5F2EF; color: #a39890; box-shadow: none; cursor: default; }
    .acs-btn-ghost { display: inline-flex; align-items: center; justify-content: center; gap: 6px; border: 1px solid #E6D9CF; border-radius: 10px; padding: 9px 18px; background: #fff; color: #5a4d45;
                     font: inherit; font-size: 13.5px; font-weight: 600; cursor: pointer; text-decoration: none; transition: all .15s; }
    .acs-btn-ghost:hover { border-color: var(--acs-orange); color: var(--acs-orange); background: #fff5f3; }
    .acs-badge { display: inline-flex; align-items: center; gap: 4px; border-radius: 999px; padding: 3px 10px; font-size: 11px; font-weight: 700; letter-spacing: .02em; background: #fff1ee; color: var(--acs-orange); white-space: nowrap; }
    .acs-badge-green { background: #ECFDF5; color: #059669; }
    .acs-badge-blue { background: #EFF6FF; color: #2563EB; }
    .acs-badge-gray { background: #F3EEEA; color: #6b5d54; }
    .acs-badge-red { background: #FEF2F2; color: #DC2626; }
    .acs-alert { display: flex; align-items: flex-start; gap: 8px; border-radius: 14px; border: 1px solid; padding: 12px 16px; font-size: 13.5px; }
    .acs-alert-error { background: #FEF2F2; border-color: rgba(220,38,38,.2); color: #B91C1C; }
    .acs-alert-warn { background: #FFFBEB; border-color: #FDE68A; color: #92400E; }
    .acs-alert-success { background: #ECFDF5; border-color: rgba(5,150,105,.25); color: #047857; }
    .acs-note { margin: 6px 0 0; font-size: 12px; color: var(--acs-muted); }
    .acs-error { margin: 5px 0 0; font-size: 12px; font-weight: 600; color: #B91C1C; }
    .acs-foot { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; margin-top: 20px; padding-top: 18px; border-top: 1px solid var(--acs-line); }

    /* The profile card and menu (same as the buyer's account sidebar) */
    .acs-side { overflow: hidden; }
    @media (min-width: 961px) { .acs-side { position: sticky; top: 84px; } }
    .acs-cover { height: 80px; background: linear-gradient(120deg, #fa4e1c 0%, #ff8a5b 45%, #002b4d 130%); }
    .acs-avatar { position: relative; display: flex; width: 80px; height: 80px; margin: -40px 0 0 20px; align-items: center; justify-content: center; overflow: hidden; border-radius: 18px;
                  background: #002b4d; border: 4px solid #fff; box-shadow: 0 8px 20px -10px rgba(0,0,0,.4); cursor: pointer; color: #fff; font-family: 'Nunito', 'Inter', sans-serif; font-size: 26px; font-weight: 800; }
    .acs-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .acs-avatar .acs-cam { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; background: rgba(0,0,0,.45); opacity: 0; transition: opacity .15s; }
    .acs-avatar:hover .acs-cam, .acs-avatar:focus-within .acs-cam { opacity: 1; }
    .acs-who { padding: 10px 20px 16px; }
    .acs-who .n { margin: 0; font-family: 'Nunito', 'Inter', sans-serif; font-size: 18px; font-weight: 800; color: var(--acs-text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .acs-who .e { margin: 2px 0 0; font-size: 12px; color: var(--acs-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .acs-who .b { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; }
    .acs-photo-status { margin: 0; padding: 4px 20px 0; font-size: 11px; color: var(--acs-muted); }
    .acs-nav { display: flex; flex-direction: column; gap: 2px; padding: 8px; border-top: 1px solid var(--acs-line); }
    .acs-nav a { display: flex; align-items: center; gap: 12px; border-radius: 12px; padding: 8px 10px; font-size: 14px; font-weight: 500; color: #5a4d45; text-decoration: none; transition: background .15s; }
    .acs-nav a:hover:not(.is-active) { background: #fff5f3; color: #222; }
    .acs-nav a .i { display: inline-flex; flex: none; width: 32px; height: 32px; align-items: center; justify-content: center; border-radius: 10px; background: #F7F1ED; color: #8a7a70; transition: all .15s; }
    .acs-nav a:hover:not(.is-active) .i { background: #fff1ee; color: var(--acs-orange); }
    .acs-nav a.is-active { background: #fff1ee; color: var(--acs-orange); font-weight: 700; }
    .acs-nav a.is-active .i { background: var(--acs-orange); color: #fff; box-shadow: 0 6px 14px -6px rgba(250,78,28,.7); }
    .acs-nav a span.l { flex: 1; min-width: 0; }
    .acs-nav a .chev { opacity: .4; }
    @media (max-width: 960px) {
        .acs-nav { flex-direction: row; overflow-x: auto; scrollbar-width: none; }
        .acs-nav a { flex: none; white-space: nowrap; }
        .acs-nav a .chev { display: none; }
    }
</style>

<div class="acs" data-account-shell>
    <div class="acs-top">
        <div>
            <p class="acs-crumbs">{{ $portal }} <span aria-hidden="true">›</span> <b>{{ $title === 'My Account' ? 'My Account' : 'My Account › ' . $title }}</b></p>
            <h1>{{ $title }}</h1>
            @if ($subtitle)<p>{{ $subtitle }}</p>@endif
        </div>
        @if ($signOut)
            {{-- The one place to sign out: the account page, top right (same as the buyer's My Profile). --}}
            <form action="{{ $logoutUrl ?? \App\Support\Portal::route('logout') }}" method="POST" data-account-signout>
                @csrf
                <button type="submit" class="acs-signout" data-keep-colors>
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"/></svg>
                    Sign Out
                </button>
            </form>
        @endif
    </div>

    <div class="acs-grid">
        <aside class="acs-card acs-side">
            <div class="acs-cover" aria-hidden="true"></div>
            {{-- Tap the photo to change it; it is saved right away (js/photo-upload.js). --}}
            <label class="acs-avatar" title="Change profile photo">
                <img data-photo-preview src="{{ $u->profile_photo_path ? asset('storage/' . $u->profile_photo_path) : '' }}" alt="Profile photo" @unless ($u->profile_photo_path) style="display:none;" @endunless>
                @unless ($u->profile_photo_path)<span data-photo-initial>{{ mb_strtoupper(mb_substr($name, 0, 1)) }}</span>@endunless
                <span class="acs-cam"><svg width="20" height="20" fill="none" stroke="#fff" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg></span>
                <input type="file" accept="image/jpeg,image/png,image/webp" style="display:none;" data-photo-upload data-photo-url="{{ route('account.photo') }}">
            </label>
            <p class="acs-photo-status" data-photo-status></p>
            <div class="acs-who">
                <p class="n" data-account-name>{{ $name }}</p>
                <p class="e">{{ $email }}</p>
                @if ($badges)
                    <div class="b">@foreach ($badges as $b)<span class="acs-badge {{ ($b['tone'] ?? 'orange') !== 'orange' ? 'acs-badge-' . $b['tone'] : '' }}">{{ $b['text'] }}</span>@endforeach</div>
                @endif
            </div>
            <nav class="acs-nav" aria-label="Account">
                @foreach ($nav as $item)
                    <a href="{{ $item['href'] }}" class="{{ ! empty($item['active']) ? 'is-active' : '' }}" @if (! empty($item['active'])) aria-current="page" @endif>
                        <span class="i"><svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">{!! $icons[$item['icon'] ?? 'user'] ?? $icons['user'] !!}</svg></span>
                        <span class="l">{{ $item['label'] }}</span>
                        <svg class="chev" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                    </a>
                @endforeach
            </nav>
        </aside>

        <div class="acs-main">
            {{ $slot }}
        </div>
    </div>
</div>
@once
    <script src="{{ asset('js/photo-upload.js') }}" defer></script>
@endonce
