{{-- ALVY Assistant's face: a little round robot in ALVY orange ($size in px; eyes blink via .ai-eye). --}}
@php $size = $size ?? 56; $uid = 'aiav' . ($id ?? ''); @endphp
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 64 64" role="img" aria-label="ALVY Assistant" class="ai-avatar">
    <defs>
        <linearGradient id="{{ $uid }}-head" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#ff8a57"/>
            <stop offset="1" stop-color="#fa4e1c"/>
        </linearGradient>
    </defs>
    {{-- antenna --}}
    <line x1="32" y1="9" x2="32" y2="16" stroke="#002b4d" stroke-width="2.4" stroke-linecap="round"/>
    <circle cx="32" cy="7.5" r="3.6" fill="#FFD166" class="ai-bulb"/>
    {{-- ears --}}
    <rect x="5" y="29" width="6" height="12" rx="3" fill="#002b4d"/>
    <rect x="53" y="29" width="6" height="12" rx="3" fill="#002b4d"/>
    {{-- head --}}
    <rect x="9" y="15" width="46" height="40" rx="18" fill="url(#{{ $uid }}-head)"/>
    {{-- face plate --}}
    <rect x="15" y="22" width="34" height="26" rx="12" fill="#FFF7F2"/>
    {{-- eyes --}}
    <g class="ai-eye">
        <ellipse cx="25" cy="33" rx="3.4" ry="4.4" fill="#1d2733"/>
        <ellipse cx="39" cy="33" rx="3.4" ry="4.4" fill="#1d2733"/>
        <circle cx="26.2" cy="31.4" r="1.2" fill="#fff"/>
        <circle cx="40.2" cy="31.4" r="1.2" fill="#fff"/>
    </g>
    {{-- cheeks --}}
    <ellipse cx="20" cy="40" rx="3" ry="1.8" fill="#FF9AA2" opacity=".75"/>
    <ellipse cx="44" cy="40" rx="3" ry="1.8" fill="#FF9AA2" opacity=".75"/>
    {{-- smile --}}
    <path d="M27.5 40.5 Q32 45 36.5 40.5" fill="none" stroke="#1d2733" stroke-width="2.2" stroke-linecap="round"/>
</svg>
