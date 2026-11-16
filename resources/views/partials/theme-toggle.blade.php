{{-- The light / dark switch (see partials/theme). variant "icon": a sun or moon button with a menu (top bars);
     variant "row": "Appearance" with three small buttons (sidebars). --}}
@php
    $variant = $variant ?? 'icon';
    $icons = [
        'light'  => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2m0 16v2M4.9 4.9l1.4 1.4m11.4 11.4 1.4 1.4M2 12h2m16 0h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        'dark'   => '<path d="M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5a8.5 8.5 0 1 0 10.7 10.7Z"/>',
        'system' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/>',
    ];
    $labels = ['light' => 'Light', 'dark' => 'Dark', 'system' => 'Same as device'];
    $svg = fn (string $name, string $cls = 'h-4 w-4', int $px = 16) => '<svg class="' . $cls . '" width="' . $px . '" height="' . $px . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">' . $icons[$name] . '</svg>';
@endphp
@if ($variant === 'row')
    <div class="theme-row" role="group" aria-label="Appearance">
        <span class="theme-row-label">Appearance</span>
        <span class="theme-seg">
            @foreach ($labels as $key => $label)
                <button type="button" data-theme-set="{{ $key }}" aria-pressed="false" title="{{ $label }}" aria-label="{{ $label }} mode">{!! $svg($key) !!}</button>
            @endforeach
        </span>
    </div>
@else
    <div class="theme-pick" data-theme-wrap>
        <button type="button" class="theme-btn" data-theme-menu aria-haspopup="menu" aria-expanded="false" title="Light or dark mode" aria-label="Light or dark mode">
            <span class="ti-light">{!! $svg('light', 'h-5 w-5', 20) !!}</span>
            <span class="ti-dark">{!! $svg('dark', 'h-5 w-5', 20) !!}</span>
        </button>
        <div class="theme-menu" role="menu" aria-label="Appearance">
            @foreach ($labels as $key => $label)
                <button type="button" class="theme-opt" role="menuitemradio" aria-checked="false" data-theme-set="{{ $key }}">
                    {!! $svg($key) !!} {{ $label }}
                    <svg class="tick h-4 w-4" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                </button>
            @endforeach
        </div>
    </div>
@endif
