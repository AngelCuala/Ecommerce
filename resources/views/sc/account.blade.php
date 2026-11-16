@extends('sc.layout')
@section('title', 'My Account')
@section('icon', 'user')

@php
    $me = auth()->user();
    $badges = [
        ['text' => 'Sorting center'],
        ['text' => 'Active', 'tone' => 'green'],
        ['text' => 'Since ' . $sc->created_at->format('M Y'), 'tone' => 'gray'],
    ];
@endphp

@section('content')
<div class="page-body" style="padding-top:22px;">
<x-account-shell title="My Account" subtitle="Manage your sorting center's profile and sign-in." portal="LogiSort"
                 :nav="\App\Support\AccountNav::sortingCenter('account')" :badges="$badges" :sign-out="true" :logout-url="route('sc.logout')">

    {{-- Personal information (who's signed in) --}}
    <section class="acs-card acs-pad" data-account-head data-account-profile>
        <div class="acs-card-head">
            <div>
                <span class="acs-icon"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/></svg></span>
                <div><h2>Personal Information</h2><p>The name and email you sign in with.</p></div>
            </div>
        </div>
        <form method="POST" action="{{ route('sc.account.update') }}">
            @csrf @method('PUT')
            <div class="acs-fields">
                <div>
                    <label class="acs-label" for="ac-name">Full name</label>
                    <input id="ac-name" type="text" name="name" class="acs-input" value="{{ old('name', $me->name) }}" required autocomplete="name">
                    @error('name')<p class="acs-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="acs-label" for="ac-email">Email address</label>
                    <input id="ac-email" type="email" name="email" class="acs-input" value="{{ old('email', $me->email) }}" required autocomplete="email">
                    @error('email')<p class="acs-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="acs-foot">
                <span class="acs-note" style="margin:0;">Your password is changed under <a href="{{ route('sc.account.security') }}" style="color:#fa4e1c;font-weight:700;text-decoration:none;">Accounts &amp; Security</a>.</span>
                <button type="submit" class="acs-btn">Save changes</button>
            </div>
        </form>
    </section>

    {{-- The sorting center --}}
    <section class="acs-card acs-pad">
        <div class="acs-card-head">
            <div>
                <span class="acs-icon" style="background:#EEF3F7;color:#002b4d;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></span>
                <div><h2>Sorting center details</h2><p>The assigned municipality is set by the ALVY administrator. Contact them if it needs to change.</p></div>
            </div>
            <span class="acs-badge acs-badge-green">Active</span>
        </div>
        <dl class="acs-tiles">
            @foreach ([
                'Center name' => $sc->name,
                'Assigned municipality' => $sc->assigned_municipality ?? 'Not assigned',
                'Province' => $sc->assigned_province ?? '—',
                'Member since' => $sc->created_at->format('M j, Y'),
                'Last login' => $sc->last_login_at?->format('M j, Y g:i A') ?? '—',
            ] as $k => $v)
                <div class="acs-tile"><dt>{{ $k }}</dt><dd>{{ $v }}</dd></div>
            @endforeach
        </dl>
    </section>

    {{-- Numbers at a glance --}}
    <section class="acs-card acs-pad">
        <div class="acs-card-head">
            <div>
                <span class="acs-icon" style="background:#E9F8EF;color:#059669;"><svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3v18h18M7 14l4-4 3 3 5-6"/></svg></span>
                <div><h2>At a glance</h2><p>Open any of them for the details.</p></div>
            </div>
        </div>
        <div class="acs-tiles">
            @foreach ([
                ['Coverage barangays', $summary['areas'], route('sc.areas')],
                ['Riders', $summary['riders'], route('sc.riders')],
                ['Parcels handled', $summary['handled'], route('sc.tracking')],
                ['Delivered', $summary['delivered'], route('sc.delivery-monitoring', ['status' => 'delivered'])],
            ] as [$lbl, $val, $href])
                <a href="{{ $href }}" class="acs-tile"><span class="k">{{ $lbl }}</span><div class="v" style="font-size:22px;">{{ $val }}</div></a>
            @endforeach
        </div>
    </section>
</x-account-shell>
</div>
@endsection
