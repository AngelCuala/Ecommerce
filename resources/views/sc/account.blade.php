@extends('sc.layout')
@section('title', 'Account Management')
@section('icon', 'user')

@php
    $me = auth()->user();
    // Same initials as the sidebar ("ALVY" in a hub's name is skipped: ALVY Calamba Hub → CH).
    $initials = collect(preg_split('/\s+/', trim($me->name)))->filter()->reject(fn ($w) => strtoupper($w) === 'ALVY')->take(2)
        ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: mb_strtoupper(mb_substr($me->name, 0, 1));
@endphp

@section('content')
<style>
    .ac-head { display: flex; flex-wrap: wrap; align-items: center; gap: 16px; padding: 20px 22px; margin-bottom: 18px; border-radius: 14px; color: #fff;
               background: linear-gradient(115deg, #002b4d 0%, #0b4a7a 60%, #fa4e1c 160%); }
    .ac-avatar { display: flex; width: 58px; height: 58px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 16px; background: #fa4e1c; font-size: 22px; font-weight: 800; box-shadow: 0 8px 18px -8px rgba(0,0,0,.5); }
    .ac-head h1 { margin: 0; font-size: 20px; font-weight: 800; color: #fff; }
    .ac-tag { display: inline-flex; align-items: center; gap: 5px; border-radius: 999px; background: rgba(255,255,255,.15); padding: 3px 10px; font-size: 12px; font-weight: 600; }
    .ac-grid { display: grid; gap: 18px; grid-template-columns: minmax(0, 1fr) 360px; align-items: start; }
    @media (max-width: 980px) { .ac-grid { grid-template-columns: 1fr; } }
    .ac-dl { display: grid; gap: 16px 24px; grid-template-columns: repeat(2, minmax(0, 1fr)); padding: 16px 18px; }
    .ac-dt { font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--text-muted); }
    .ac-dd { margin-top: 3px; font-size: 14px; font-weight: 600; color: var(--text); }
    .ac-stats { display: grid; gap: 12px; grid-template-columns: repeat(4, minmax(0, 1fr)); margin-top: 18px; }
    @media (max-width: 700px) { .ac-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    .ac-stat { display: flex; align-items: center; gap: 11px; padding: 14px; text-decoration: none; transition: border-color .15s; }
    .ac-stat:hover { border-color: var(--accent-blue); }
    .ac-stat-ic { display: flex; width: 36px; height: 36px; flex-shrink: 0; align-items: center; justify-content: center; border-radius: 10px; }
    .ac-stat-n { font-size: 20px; font-weight: 800; line-height: 1; color: var(--text); }
    .ac-stat-l { margin-top: 3px; font-size: 11.5px; color: var(--text-muted); }
    .ac-form { display: grid; gap: 12px; padding: 16px 18px; }
    .ac-label { display: block; margin-bottom: 5px; font-size: 12px; font-weight: 600; color: var(--text-sub); }
    .ac-pw { position: relative; }
    .ac-pw .form-input { padding-right: 2.6rem; }
    .ac-eye { position: absolute; right: .7rem; top: 50%; transform: translateY(-50%); border: 0; background: none; cursor: pointer; line-height: 1; color: var(--text-muted); }
</style>

<div class="page-body" style="padding-top:22px;">

    {{-- Who's signed in --}}
    <div class="ac-head" data-account-head>
        <span class="ac-avatar">{{ $initials }}</span>
        <div style="min-width:0;flex:1;">
            <h1>{{ $me->name }}</h1>
            <div style="font-size:13px;opacity:.85;">{{ $me->email }}</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">
                <span class="ac-tag">Sorting center</span>
                <span class="ac-tag">@include('sc.partials.icon', ['name' => 'location', 'size' => 13, 'sw' => 2]) {{ $sc->assigned_municipality ?? 'No municipality' }}{{ $sc->assigned_province ? ', ' . $sc->assigned_province : '' }}</span>
                <span class="ac-tag" style="background:rgba(22,163,74,.85);">Active</span>
            </div>
        </div>
        <form method="POST" action="{{ route('sc.logout') }}">
            @csrf
            <button class="btn btn-red btn-sm" style="gap:6px;">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                Logout
            </button>
        </form>
    </div>

    <div class="ac-grid">
        {{-- Center details and numbers --}}
        <div>
            <div class="card">
                <div class="card-header"><h2>Sorting center details</h2></div>
                <dl class="ac-dl">
                    @foreach ([
                        'Center name' => $sc->name,
                        'Assigned municipality' => $sc->assigned_municipality ?? 'Not assigned',
                        'Province' => $sc->assigned_province ?? '—',
                        'Status' => 'Active',
                        'Member since' => $sc->created_at->format('M j, Y'),
                        'Last login' => $sc->last_login_at?->format('M j, Y g:i A') ?? '—',
                    ] as $k => $v)
                        <div><dt class="ac-dt">{{ $k }}</dt><dd class="ac-dd">
                            @if ($k === 'Status')<span class="badge badge-green">Active</span>@else{{ $v }}@endif
                        </dd></div>
                    @endforeach
                </dl>
                <p class="text-muted" style="font-size:12px;padding:0 18px 16px;">The assigned municipality is set by the ALVY administrator. Contact them if it needs to change.</p>
            </div>

            <div class="ac-stats">
                @foreach ([
                    ['Coverage barangays', $summary['areas'], route('sc.areas'), 'location', '#F3F0FB', '#6D4AA5'],
                    ['Riders', $summary['riders'], route('sc.riders'), 'rider', '#EAF2FB', 'var(--accent-blue)'],
                    ['Parcels handled', $summary['handled'], route('sc.incoming-parcels'), 'parcel', '#FFF4E5', 'var(--accent-orange)'],
                    ['Delivered', $summary['delivered'], route('sc.delivery-monitoring', ['status' => 'delivered']), 'check', '#E9F8EF', 'var(--accent-green)'],
                ] as [$lbl, $val, $href, $ic, $bg, $fg])
                    <a href="{{ $href }}" class="card ac-stat">
                        <span class="ac-stat-ic" style="background:{{ $bg }};color:{{ $fg }};">@include('sc.partials.icon', ['name' => $ic, 'size' => 17, 'sw' => 2])</span>
                        <div><div class="ac-stat-n">{{ $val }}</div><div class="ac-stat-l">{{ $lbl }}</div></div>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Profile and password --}}
        <div style="display:grid;gap:18px;">
            <div class="card" data-account-profile>
                <div class="card-header"><h2>Profile</h2></div>
                <form method="POST" action="{{ route('sc.account.update') }}" class="ac-form">
                    @csrf @method('PUT')
                    <div>
                        <label class="ac-label" for="ac-name">Full name</label>
                        <input id="ac-name" type="text" name="name" class="form-input" style="width:100%;" value="{{ old('name', $me->name) }}" required autocomplete="name">
                    </div>
                    <div>
                        <label class="ac-label" for="ac-email">Email address</label>
                        <input id="ac-email" type="email" name="email" class="form-input" style="width:100%;" value="{{ old('email', $me->email) }}" required autocomplete="email">
                    </div>
                    <button type="submit" class="btn btn-blue" style="justify-content:center;">Save profile</button>
                </form>
            </div>

            <a href="{{ route('sc.account.security') }}" class="card" data-account-security style="display:flex;align-items:center;gap:12px;padding:16px 18px;text-decoration:none;">
                <span style="display:flex;width:40px;height:40px;flex-shrink:0;align-items:center;justify-content:center;border-radius:11px;background:#EAF2FB;color:var(--accent-blue);">
                    <svg width="19" height="19" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                </span>
                <div style="min-width:0;flex:1;">
                    <div style="font-size:14px;font-weight:700;color:var(--text);">Security</div>
                    <div style="font-size:12px;color:var(--text-muted);">Change your password. We email you a code to confirm it.</div>
                </div>
                <span style="color:var(--text-muted);">&rarr;</span>
            </a>
        </div>
    </div>
</div>

@endsection
