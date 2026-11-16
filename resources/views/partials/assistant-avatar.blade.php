{{-- ALLIE's face (ALVY's assistant): a chubby round helper in ALVY orange with a heart antenna and a little bow
     ($size in px). The animations live in assistant-widget: .ai-eye blinks, .ai-look moves the eyes, .ai-bulb is the
     heart, .ai-bow wiggles, .ai-body tilts and hops, .is-happy swaps in the squinty eyes and the big smile, and
     .is-sad shows the worried brows, the frown and a tear. --}}
@php $size = $size ?? 56; $uid = 'aiav' . ($id ?? ''); @endphp
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 64 64" role="img" aria-label="ALLIE" class="ai-avatar">
    <defs>
        <linearGradient id="{{ $uid }}-head" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0" stop-color="#ff9a6b"/>
            <stop offset="1" stop-color="#fa4e1c"/>
        </linearGradient>
        <radialGradient id="{{ $uid }}-shine" cx=".32" cy=".22" r=".6">
            <stop offset="0" stop-color="#fff" stop-opacity=".45"/>
            <stop offset="1" stop-color="#fff" stop-opacity="0"/>
        </radialGradient>
    </defs>

    <g class="ai-body">
        {{-- heart antenna --}}
        <path d="M32 17 V11.5" stroke="#002b4d" stroke-width="2.2" stroke-linecap="round"/>
        <path class="ai-bulb" d="M32 11.8 C27.4 8.6 26.2 5.2 28.5 3.6 C30.1 2.5 31.5 3.4 32 4.6 C32.5 3.4 33.9 2.5 35.5 3.6 C37.8 5.2 36.6 8.6 32 11.8 Z" fill="#FF5C8A"/>

        {{-- headphones --}}
        <rect x="4.5" y="30" width="7" height="13" rx="3.5" fill="#002b4d"/>
        <rect x="52.5" y="30" width="7" height="13" rx="3.5" fill="#002b4d"/>

        {{-- head --}}
        <rect x="9" y="15" width="46" height="44" rx="21" fill="url(#{{ $uid }}-head)"/>
        <rect x="9" y="15" width="46" height="44" rx="21" fill="url(#{{ $uid }}-shine)"/>

        {{-- little bow --}}
        <g transform="translate(46 17) rotate(18)">
            <g class="ai-bow">
                <path d="M0 0 L-6 -3.6 Q-7.2 0 -6 3.6 Z" fill="#FF7AA8"/>
                <path d="M0 0 L6 -3.6 Q7.2 0 6 3.6 Z" fill="#FF7AA8"/>
                <circle r="1.9" fill="#FF4F86"/>
            </g>
        </g>

        {{-- face --}}
        <rect x="14.5" y="23" width="35" height="29" rx="14" fill="#FFF7F2"/>

        {{-- eyes: big, sparkly, with a few lashes --}}
        <g class="ai-eye">
            <g class="ai-look">
                <ellipse cx="25" cy="34" rx="4.1" ry="5" fill="#1d2733"/>
                <ellipse cx="39" cy="34" rx="4.1" ry="5" fill="#1d2733"/>
                <circle cx="26.5" cy="32.1" r="1.55" fill="#fff"/>
                <circle cx="40.5" cy="32.1" r="1.55" fill="#fff"/>
                <circle cx="23.9" cy="36" r=".75" fill="#fff"/>
                <circle cx="37.9" cy="36" r=".75" fill="#fff"/>
            </g>
            <path d="M21.4 30.6 L19.8 29.4 M22.4 29.5 L21.4 27.9" stroke="#1d2733" stroke-width="1.1" stroke-linecap="round"/>
            <path d="M42.6 30.6 L44.2 29.4 M41.6 29.5 L42.6 27.9" stroke="#1d2733" stroke-width="1.1" stroke-linecap="round"/>
        </g>
        {{-- happy squinty eyes ^ ^ (shown instead of the eyes when she is happy) --}}
        <g class="ai-happy-eyes" fill="none" stroke="#1d2733" stroke-width="2.4" stroke-linecap="round">
            <path d="M20.8 35.6 Q25 29.6 29.2 35.6"/>
            <path d="M34.8 35.6 Q39 29.6 43.2 35.6"/>
        </g>

        {{-- sad: worried eyebrows and a tear (shown when someone is about to hide her) --}}
        <g class="ai-brows" fill="none" stroke="#1d2733" stroke-width="1.7" stroke-linecap="round">
            <path d="M20.6 27.6 Q24.4 27.6 28 25.4"/>
            <path d="M43.4 27.6 Q39.6 27.6 36 25.4"/>
        </g>
        <path class="ai-tear" d="M21.4 39.4 Q19.6 42.6 21.4 43.8 Q23.2 42.6 21.4 39.4 Z" fill="#7CC4FF" stroke="#4BA3F0" stroke-width=".5"/>

        {{-- rosy cheeks --}}
        <g class="ai-cheeks">
            <ellipse cx="19.6" cy="41.6" rx="3.3" ry="2.1" fill="#FF8FA3" opacity=".8"/>
            <ellipse cx="44.4" cy="41.6" rx="3.3" ry="2.1" fill="#FF8FA3" opacity=".8"/>
        </g>

        {{-- happy little mouth, and the big open smile for happy moments --}}
        <path class="ai-mouth" d="M28.6 42 Q30.3 44.6 32 42 Q33.7 44.6 35.4 42" fill="none" stroke="#1d2733" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/>
        <path class="ai-grin" d="M27.6 41.2 Q32 41.8 36.4 41.2 Q36 46.6 32 46.8 Q28 46.6 27.6 41.2 Z" fill="#1d2733"/>
        <path class="ai-grin" d="M29.6 45.2 Q32 43.9 34.4 45.2 Q33.4 46.6 32 46.6 Q30.6 46.6 29.6 45.2 Z" fill="#FF7A90"/>
        <path class="ai-frown" d="M28.4 45.4 Q32 41.8 35.6 45.4" fill="none" stroke="#1d2733" stroke-width="1.9" stroke-linecap="round"/>
    </g>
</svg>
