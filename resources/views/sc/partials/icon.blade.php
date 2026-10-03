{{--
    Reusable inline-SVG icon set for the Sorting Center.
    Usage: @include('sc.partials.icon', ['name' => 'dashboard', 'size' => 18])
    Icons inherit currentColor, so wrap in a coloured element to tint them.
--}}
@php
    $size = $size ?? 18;
    $sw   = $sw ?? 1.8;
@endphp
@switch($name ?? '')
    @case('dashboard')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
        @break
    @case('parcel')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
        @break
    @case('truck')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="1.5"/><path d="M16 8h4l3 5v3h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
        @break
    @case('check')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
        @break
    @case('cross')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
        @break
    @case('rider')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="5" cy="17" r="3"/><circle cx="19" cy="17" r="3"/><path d="M8 17h6l3-6h-3l-2-3H8"/><path d="M14 8h3"/></svg>
        @break
    @case('user')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
        @break
    @case('clock')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
        @break
    @case('sort')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 3 21 3 21 8"/><line x1="4" y1="20" x2="21" y2="3"/><polyline points="21 16 21 21 16 21"/><line x1="15" y1="15" x2="21" y2="21"/><line x1="4" y1="4" x2="9" y2="9"/></svg>
        @break
    @case('bins')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="4" rx="1"/><path d="M5 8v11a1 1 0 001 1h12a1 1 0 001-1V8"/><line x1="10" y1="12" x2="14" y2="12"/></svg>
        @break
    @case('inbox')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 002 2h16a2 2 0 002-2v-6l-3.45-6.89A2 2 0 0016.76 4H7.24a2 2 0 00-1.79 1.11z"/></svg>
        @break
    @case('upload')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        @break
    @case('location')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
        @break
    @case('chat')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
        @break
    @case('transfer')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        @break
    @case('return')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 00-4-4H4"/></svg>
        @break
    @case('report')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="20" x2="4" y2="12"/><line x1="10" y1="20" x2="10" y2="4"/><line x1="16" y1="20" x2="16" y2="9"/><line x1="20" y1="20" x2="20" y2="14"/></svg>
        @break
    @case('list')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="3" width="8" height="4" rx="1"/><path d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><line x1="8" y1="11" x2="16" y2="11"/><line x1="8" y1="15" x2="14" y2="15"/></svg>
        @break
    @case('confetti')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 12.5l2.5 2.5 4.5-5"/></svg>
        @break
    @case('eye')
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        @break
    @default
        <svg width="{{ $size }}" height="{{ $size }}" fill="none" stroke="currentColor" stroke-width="{{ $sw }}" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/></svg>
@endswitch
