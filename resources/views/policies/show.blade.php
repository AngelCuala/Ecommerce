<x-layout :title="$title . ' — ALVY'">
<div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 lg:px-8">

    <nav class="mb-6 text-xs" style="color:#6b90aa;">
        <a href="{{ route('home') }}" onmouseover="this.style.color='#fa4e1c';" onmouseout="this.style.color='#6b90aa';">Home</a>
        <span class="mx-1.5">›</span>
        <span>{{ $title }}</span>
    </nav>

    <h1 class="font-display text-3xl font-extrabold mb-2" style="color:#222222;">{{ $title }}</h1>

    @if ($policy)
        <p class="text-xs mb-8" style="color:#6b90aa;">
            Last updated {{ $policy->updated_at->format('F d, Y') }}
        </p>

        <div class="card p-8 prose prose-sm max-w-none text-sm leading-relaxed" style="color:#444444;">
            {!! $policy->content !!}
        </div>
    @else
        <div class="card flex flex-col items-center gap-3 py-16 text-center">
            <span class="text-4xl">📄</span>
            <p class="font-bold" style="color:#222222;">Policy not yet published</p>
            <p class="text-sm" style="color:#6b90aa;">This policy is being prepared. Check back soon.</p>
        </div>
    @endif

    {{-- Links to other policies --}}
    <div class="mt-10 pt-8 border-t" style="border-color:#dce8f0;">
        <p class="text-xs font-bold uppercase tracking-widest mb-4" style="color:#6b90aa;">Other Policies</p>
        <div class="flex flex-wrap gap-3">
            @foreach (\App\Models\PlatformPolicy::defaultPolicies() as $p)
                @if ($p['key'] !== $key)
                    <a href="{{ route('policies.show', $p['key']) }}"
                       class="rounded-full border px-4 py-1.5 text-xs font-semibold transition"
                       style="border-color:#E0E0E0;color:#555555;"
                       onmouseover="this.style.borderColor='#fa4e1c';this.style.color='#fa4e1c';"
                       onmouseout="this.style.borderColor='#E0E0E0';this.style.color='#555555';">
                        {{ $p['title'] }}
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</div>
</x-layout>
