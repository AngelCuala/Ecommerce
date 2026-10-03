{{--
    Reusable inline-SVG icon set for the Courier portal.
    Usage: @include('courier.partials.icon', ['name' => 'dashboard', 'size' => 18])
    Icons inherit currentColor, so wrap them in a coloured element to tint.
--}}
@php
    $size = $size ?? 18;
    $sw   = $sw ?? 1.8;
@endphp
@switch($name ?? '')
    @case('dashboard')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        @break
    @case('history')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 106 5.3L3 8"/><path d="M12 7v5l4 2"/></svg>
        @break
    @case('earnings')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9 8h5a2.5 2.5 0 010 5H9m0 0h6M9 8v8"/></svg>
        @break
    @case('user')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        @break
    @case('bell')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/></svg>
        @break
    @case('chat')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
        @break
    @case('store')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l1.5-5h15L21 9M4 9h16v10a1 1 0 01-1 1H5a1 1 0 01-1-1V9zM3 9a2.5 2.5 0 005 0 2.5 2.5 0 005 0 2.5 2.5 0 005 0 2.5 2.5 0 004 0"/></svg>
        @break
    @case('truck')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1.5"/><path d="M16 8h4l3 5v3h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        @break
    @case('parcel')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
        @break
    @case('check')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
        @break
    @case('check-plain')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
        @break
    @case('cross')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
        @break
    @case('ban')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><line x1="5.6" y1="5.6" x2="18.4" y2="18.4"/></svg>
        @break
    @case('clock')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
        @break
    @case('eye')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        @break
    @case('back')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M15 19l-7-7 7-7"/></svg>
        @break
    @case('camera')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/><circle cx="12" cy="13" r="3"/></svg>
        @break
    @case('lock')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        @break
    @case('logout')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
        @break
    @case('menu')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
        @break
    @default
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/></svg>
@endswitch
