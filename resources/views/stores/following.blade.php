<x-layout title="Following — ALVY">
<div class="pf-page">
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
    @include('profile._page-header', ['title' => 'Following', 'subtitle' => 'Stores you follow.'])

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        @include('profile._sidebar')

        <div class="min-w-0 flex-1">
            @if (session('success'))<div class="pf-alert pf-alert-success mb-4">{{ session('success') }}</div>@endif
            @forelse ($follows as $f)
                <div class="pf-card mb-3 flex items-center gap-3 p-4">
                    <span class="flex h-11 w-11 items-center justify-center rounded-full font-bold text-white" style="background:#002b4d;">{{ strtoupper(substr($f->seller->storeName(), 0, 1)) }}</span>
                    <div class="min-w-0 flex-1">
                        <a href="{{ route('stores.show', $f->seller_id) }}" class="font-semibold hover:underline" style="color:#222;">{{ $f->seller->storeName() }}</a>
                        <p class="text-xs pf-muted">Following since {{ $f->created_at->format('M j, Y') }}</p>
                    </div>
                    <form action="{{ route('stores.follow', $f->seller_id) }}" method="POST">@csrf
                        <button class="pf-btn-ghost text-sm">Unfollow</button>
                    </form>
                </div>
            @empty
                <div class="pf-card p-10 text-center">
                    <p class="font-semibold" style="color:#222;">You don't follow any store yet</p>
                    <p class="mt-1 text-sm pf-muted">Open a store and tap Follow to see it here.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>
</div>
</x-layout>
