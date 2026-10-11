{{-- Account page title. Usage: @include('profile._page-header', ['title' => '...', 'subtitle' => '...']) --}}
<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <nav class="mb-1 flex items-center gap-1.5 text-xs pf-muted">
            <a href="{{ route('home') }}" class="hover:underline">Home</a>
            <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
            <a href="{{ route('profile.show') }}" class="hover:underline">My Account</a>
            @if (($title ?? '') !== 'My Profile')
                <svg class="h-3 w-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/></svg>
                <span style="color:#3a2f29;">{{ $title }}</span>
            @endif
        </nav>
        <h1 class="font-display text-2xl font-extrabold sm:text-3xl" style="color:#222;">{{ $title }}</h1>
        @if (!empty($subtitle))
            <p class="mt-1 text-sm pf-muted">{{ $subtitle }}</p>
        @endif
    </div>
</div>
