<x-layout title="All Categories — ALVY">

<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">

    {{-- 12-category grid --}}
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
        @foreach ($catalog as $cat)
            <a href="{{ route('categories.show', $cat['slug']) }}"
               class="card group flex flex-col items-center gap-3 p-5 text-center transition hover:-translate-y-1"
               style="border-color:#EFEFEF;">

                {{-- Icon circle --}}
                <span class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-2xl text-3xl transition"
                      style="background:{{ $cat['color'] }}18;"
                      onmouseover="this.style.background='{{ $cat['color'] }}30';"
                      onmouseout="this.style.background='{{ $cat['color'] }}18';">
                    {{ $cat['icon'] }}
                </span>

                {{-- Name --}}
                <span class="text-xs font-bold leading-snug" style="color:#333333;">{{ $cat['name'] }}</span>

                {{-- Sub count --}}
                <span class="text-[11px]" style="color:#999999;">
                    @if(count($cat['subs']) > 0) {{ count($cat['subs']) }} subcategories @endif
                </span>
            </a>
        @endforeach
    </div>

</div>

</x-layout>
