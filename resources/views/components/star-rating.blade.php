@props(['rating' => 0, 'count' => null])

@php
    $rating = max(0, min(5, (float) $rating));
    $uid = 'star-fade-' . uniqid();
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-1.5']) }}
     role="img"
     aria-label="{{ $rating > 0 ? 'Rated ' . number_format($rating, 1) . ' out of 5' . ($count ? ' from ' . $count . ' ' . str('review')->plural($count) : '') : 'No reviews yet' }}">

    <div class="flex" style="color:#fa4e1c;">
        <svg width="0" height="0" class="absolute" aria-hidden="true">
            <defs>
                @for ($i = 1; $i <= 5; $i++)
                    @php $fill = max(0, min(1, $rating - ($i - 1))) * 100; @endphp
                    <linearGradient id="{{ $uid }}-{{ $i }}">
                        <stop offset="{{ $fill }}%" stop-color="currentColor"/>
                        <stop offset="{{ $fill }}%" stop-color="#e0e0e0"/>
                    </linearGradient>
                @endfor
            </defs>
        </svg>

        @for ($i = 1; $i <= 5; $i++)
            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" viewBox="0 0 20 20"
                 fill="url(#{{ $uid }}-{{ $i }})" aria-hidden="true">
                <path d="M10 1.5l2.6 5.27 5.82.85-4.21 4.1 1 5.8L10 14.85l-5.21 2.67 1-5.8-4.21-4.1 5.82-.85L10 1.5z"/>
            </svg>
        @endfor
    </div>

    @if ($rating > 0)
        <span class="text-xs text-gray-500">
            {{ number_format($rating, 1) }}{{ $count ? " ({$count})" : '' }}
        </span>
    @else
        <span class="text-xs text-gray-400">No reviews yet</span>
    @endif
</div>