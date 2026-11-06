<x-admin-layout title="Home Banners" active="banners">

<div class="mb-5 flex flex-wrap items-start justify-between gap-3">
    <p class="max-w-2xl text-sm" style="color:#6b90aa;">
        The big banner at the top of the home page, on the <strong style="color:#002b4d;">website and the app</strong>.
        Add a slide for an event or sale (11.11, Christmas sale…) with its own photo; set when it starts and ends and it
        shows and hides by itself. Your live slides come first, then ALVY's own slides.
    </p>
    <a href="{{ route('admin.banners.create') }}" class="btn-gold text-sm" style="background:#fa4e1c;border-color:#fa4e1c;color:#fff;">+ New banner</a>
</div>

@if (session('success'))
    <div class="mb-4 rounded-xl border p-3 text-sm" style="background:rgba(250,78,28,.10);border-color:rgba(250,78,28,.35);color:#d93d0e;">✓ {{ session('success') }}</div>
@endif

<form method="POST" action="{{ route('admin.banners.settings') }}" class="card mb-5 flex flex-wrap items-center justify-between gap-3 p-4">
    @csrf
    <label class="flex items-center gap-3 text-sm" style="color:#002b4d;">
        <input type="checkbox" name="hide_defaults" value="1" @checked($hideDefaults) onchange="this.form.submit()" class="h-4 w-4 accent-[#fa4e1c]">
        <span><strong>Show only my banners</strong> while one is live <span style="color:#6b90aa;">(hide ALVY's own slides during the event)</span></span>
    </label>
    <noscript><button class="btn-outline !py-1.5 text-sm">Save</button></noscript>
</form>

@if ($banners->isEmpty())
    <div class="card px-6 py-14 text-center">
        <p class="font-semibold" style="color:#002b4d;">No banners yet</p>
        <p class="mt-1 text-sm" style="color:#6b90aa;">The home page shows ALVY's own slides. Add one for your next event.</p>
    </div>
@else
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($banners as $b)
            @php
                $state = $b->state();
                $chip = ['Live' => ['#ECFDF3', '#15803D'], 'Scheduled' => ['#FFFBEB', '#B45309'], 'Ended' => ['#F5F5F4', '#78716C'], 'Off' => ['#F5F5F4', '#78716C']][$state];
            @endphp
            <div class="card overflow-hidden">
                <div class="relative h-40" style="background:#1E1B17;">
                    <img src="{{ $b->imageUrl() }}" alt="" class="h-full w-full object-cover" style="object-position:{{ \App\Models\HomeBanner::FOCUS[$b->focus] ?? 'center' }};">
                    <div class="absolute inset-0" style="background:linear-gradient(90deg,rgba(24,16,10,.7),rgba(24,16,10,0) 75%);"></div>
                    <div class="absolute bottom-3 left-4 right-4">
                        @if ($b->eyebrow)<p class="text-[11px] font-semibold uppercase tracking-wider" style="color:#ffcbb8;">{{ $b->eyebrow }}</p>@endif
                        <p class="font-display text-lg font-extrabold leading-tight" style="color:#fff;">{{ $b->title }} @if ($b->highlight)<span style="border-bottom:3px solid #fa4e1c;">{{ $b->highlight }}</span>@endif</p>
                    </div>
                    <span class="absolute right-3 top-3 rounded-full px-2.5 py-0.5 text-xs font-bold" style="background:{{ $chip[0] }};color:{{ $chip[1] }};">{{ $state }}</span>
                </div>
                <div class="space-y-1 p-4 text-sm" style="color:#6b90aa;">
                    <p>Button: <strong style="color:#002b4d;">{{ $b->button_label }}</strong> → {{ $b->category_slug ? ($categories[$b->category_slug] ?? $b->category_slug) : 'All categories' }}</p>
                    <p>
                        {{ $b->starts_at ? $b->starts_at->tz(config('app.business_timezone'))->format('M j, Y g:i A') : 'Now' }}
                        – {{ $b->ends_at ? $b->ends_at->tz(config('app.business_timezone'))->format('M j, Y g:i A') : 'no end' }}
                        · order {{ $b->sort_order }}
                    </p>
                    <div class="flex flex-wrap gap-3 pt-2">
                        <a href="{{ route('admin.banners.edit', $b) }}" class="text-xs font-semibold" style="color:#fa4e1c;">Edit</a>
                        <form method="POST" action="{{ route('admin.banners.toggle', $b) }}">
                            @csrf @method('PATCH')
                            <button class="text-xs font-semibold" style="color:#002b4d;">{{ $b->is_active ? 'Turn off' : 'Turn on' }}</button>
                        </form>
                        <form method="POST" action="{{ route('admin.banners.destroy', $b) }}" onsubmit="return confirm('Delete this banner? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button class="text-xs font-semibold" style="color:#DC2626;">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

</x-admin-layout>
