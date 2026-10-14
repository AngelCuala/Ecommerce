<x-layout title="Wishlist — ALVY">
<div class="pf-page">
<div class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8 lg:py-10">
    @include('profile._page-header', ['title' => 'Wishlist', 'subtitle' => 'Items you saved for later.'])

    <div class="flex flex-col gap-6 lg:flex-row lg:items-start">
        @include('profile._sidebar')

        <div class="min-w-0 flex-1">
            @if (session('success'))<div class="pf-alert pf-alert-success mb-4">{{ session('success') }}</div>@endif

            @if ($items->isEmpty())
                <div class="pf-card p-10 text-center">
                    <p class="font-semibold" style="color:#222;">Your wishlist is empty</p>
                    <p class="mt-1 text-sm pf-muted">Tap the heart on any item to save it here.</p>
                    <a href="{{ route('shop.index') }}" class="pf-btn mt-4 inline-flex px-6">Browse items</a>
                </div>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                    @foreach ($items as $item)
                        @php
                            $p = $item->product;
                            $gone = ! $p || $p->trashed() || ! $p->isPublished();
                        @endphp
                        @if ($gone)
                            <div class="pf-card relative flex flex-col overflow-hidden">
                                <div class="flex aspect-square items-center justify-center text-sm font-semibold" style="background:#F5F2EF;color:#a39890;">Unavailable</div>
                                <div class="flex flex-1 flex-col p-3">
                                    <p class="line-clamp-2 text-sm" style="color:#78716C;">{{ $p->title ?? 'Removed item' }}</p>
                                    <p class="mt-1 text-xs" style="color:#B91C1C;">No longer sold</p>
                                    <form action="{{ route('wishlist.toggle', $item->product_id) }}" method="POST" class="mt-auto pt-2">@csrf
                                        <input type="hidden" name="state" value="off">
                                        <button class="text-xs font-semibold hover:underline" style="color:#DC2626;">Remove</button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <div class="relative">
                                <x-product-card :product="$p" />
                                @if ($p->stock <= 0)
                                    <span class="pointer-events-none absolute left-2 top-2 z-10 rounded px-2 py-0.5 text-[11px] font-bold text-white" style="background:#57534E;">Sold out</span>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
</div>
</x-layout>
