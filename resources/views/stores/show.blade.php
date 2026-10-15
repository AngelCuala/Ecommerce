<x-layout :title="$seller->storeName() . ' — ALVY'">
@php $app = $seller->sellerApplication; @endphp
<div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">

    @if (session('success'))<div class="mb-4 rounded-lg px-4 py-2.5 text-sm" style="background:#ECFDF5;color:#047857;">{{ session('success') }}</div>@endif
    @if (session('error'))<div class="mb-4 rounded-lg px-4 py-2.5 text-sm" style="background:#FEF2F2;color:#B91C1C;">{{ session('error') }}</div>@endif

    {{-- Store header --}}
    @if ($app?->banner_path)
        <img src="{{ asset('storage/' . $app->banner_path) }}" alt="{{ $seller->storeName() }} banner" class="mb-3 h-32 w-full rounded-xl object-cover sm:h-44" data-store-banner>
    @endif
    <div class="flex flex-wrap items-center gap-4 rounded-xl p-5 text-white" style="background:linear-gradient(120deg,#002b4d,#0b4a7a);">
        @if ($app?->logo_path)
            <img src="{{ asset('storage/' . $app->logo_path) }}" alt="{{ $seller->storeName() }} logo" class="h-16 w-16 rounded-full bg-white object-cover" data-store-logo>
        @else
            <span class="flex h-16 w-16 items-center justify-center rounded-full text-2xl font-extrabold" style="background:#fa4e1c;">{{ strtoupper(substr($seller->storeName(), 0, 1)) }}</span>
        @endif
        <div class="min-w-0 flex-1">
            <h1 class="font-display text-2xl font-extrabold">{{ $seller->storeName() }}
                @if ($app?->is_on_vacation)<span class="ml-2 rounded px-2 py-0.5 align-middle text-xs font-semibold" style="background:#FFFBEB;color:#B45309;">On vacation</span>@endif
            </h1>
            <p class="text-sm opacity-80">
                <span data-followers>{{ number_format($followers) }}</span> {{ \Illuminate\Support\Str::plural('follower', $followers) }}
                · {{ $products->total() }} products
                @if ($app?->municipality) · {{ $app->municipality }}{{ $app->province ? ', ' . $app->province : '' }}@endif
            </p>
        </div>
        @auth
            @if (auth()->id() !== $seller->id)
                <form action="{{ route('stores.follow', $seller->id) }}" method="POST">@csrf
                    <button class="rounded-lg px-5 py-2 text-sm font-semibold {{ $following ? 'border border-white/60 text-white' : 'bg-white' }}" style="{{ $following ? '' : 'color:#002b4d;' }}">
                        {{ $following ? '✓ Following' : '+ Follow' }}
                    </button>
                </form>
            @endif
        @else
            <a href="{{ route('login') }}" class="rounded-lg bg-white px-5 py-2 text-sm font-semibold" style="color:#002b4d;">+ Follow</a>
        @endauth
    </div>

    @if ($app?->description)
        <p class="mt-3 whitespace-pre-line text-sm" style="color:#44403C;" data-store-description>{{ $app->description }}</p>
    @endif
    @if ($app?->is_on_vacation)
        <p class="mt-3 rounded-lg px-4 py-2.5 text-sm font-semibold" style="background:#FFFBEB;color:#B45309;">This store is on vacation. You can browse, but orders are paused for now.</p>
    @endif

    {{-- Vouchers --}}
    @if ($vouchers->isNotEmpty())
        <div class="mt-5 flex flex-wrap gap-3">
            @foreach ($vouchers as $v)
                <div class="flex items-center gap-3 rounded-lg border border-dashed px-4 py-2.5" style="border-color:#FDBA9B;background:#FFF8F4;">
                    <div>
                        <p class="text-sm font-bold" style="color:#C2410C;">{{ $v->discountLabel() }}</p>
                        <p class="text-xs" style="color:#8a7f78;">{{ $v->min_spend > 0 ? 'Min spend ₱' . number_format($v->min_spend, 0) : 'No minimum' }}@if ($v->ends_at) · until {{ $v->ends_at->format('M d') }}@endif</p>
                    </div>
                    @if (in_array($v->id, $claimed))
                        <span class="text-xs font-semibold" style="color:#059669;">Claimed</span>
                    @else
                        <form action="{{ route('vouchers.claim', $v) }}" method="POST">@csrf
                            <button class="rounded-md px-3 py-1 text-xs font-semibold text-white" style="background:#fa4e1c;">Claim</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-[220px_1fr]">
        {{-- Store categories (two levels) --}}
        <aside>
            <p class="mb-2 text-sm font-bold" style="color:#222;">Categories</p>
            <nav class="space-y-0.5 text-sm">
                @php $filtered = request('category') || request('scat'); @endphp
                <a href="{{ route('stores.show', $seller->id) }}" class="block rounded px-2 py-1.5 {{ $filtered ? '' : 'font-semibold' }}" style="{{ $filtered ? 'color:#57534E;' : 'color:#fa4e1c;background:#FFF1EB;' }}">All products</a>
                @foreach ($storeCategories as $sc)
                    @php $on = (string) request('scat') === (string) $sc->id; @endphp
                    <a href="{{ route('stores.show', [$seller->id, 'scat' => $sc->id]) }}" class="flex justify-between rounded px-2 py-1.5 {{ $on ? 'font-semibold' : '' }}" style="{{ $on ? 'color:#fa4e1c;background:#FFF1EB;' : 'color:#44403C;' }}" data-store-category="{{ $sc->name }}">
                        <span class="min-w-0 break-words">{{ $sc->name }}</span><span class="text-xs" style="color:#a39890;">{{ $sc->products_count }}</span>
                    </a>
                    @foreach ($sc->children as $child)
                        <a href="{{ route('stores.show', [$seller->id, 'scat' => $child->id]) }}" class="ml-3 flex justify-between rounded px-2 py-1 text-xs" style="{{ (string) request('scat') === (string) $child->id ? 'color:#fa4e1c;font-weight:600;' : 'color:#78716C;' }}" data-store-category="{{ $child->name }}">
                            <span class="min-w-0 break-words">{{ $child->name }}</span><span>{{ $child->products_count }}</span>
                        </a>
                    @endforeach
                @endforeach
                @if ($storeCategories->isNotEmpty() && $uncategorized > 0)
                    <a href="{{ route('stores.show', [$seller->id, 'scat' => 'none']) }}" class="flex justify-between rounded px-2 py-1.5" style="{{ request('scat') === 'none' ? 'color:#fa4e1c;background:#FFF1EB;font-weight:600;' : 'color:#44403C;' }}" data-store-category="Uncategorized">
                        <span>Uncategorized</span><span class="text-xs" style="color:#a39890;">{{ $uncategorized }}</span>
                    </a>
                @endif
                @foreach ($categories as $name => $cat)
                    @php $active = request('category') === $name; @endphp
                    <a href="{{ route('stores.show', [$seller->id, 'category' => $name]) }}" class="flex justify-between rounded px-2 py-1.5 {{ $active && ! request('sub') ? 'font-semibold' : '' }}" style="{{ $active && ! request('sub') ? 'color:#fa4e1c;background:#FFF1EB;' : 'color:#44403C;' }}">
                        <span>{{ $name }}</span><span class="text-xs" style="color:#a39890;">{{ $cat['count'] }}</span>
                    </a>
                    @foreach ($cat['subs'] as $sub => $n)
                        <a href="{{ route('stores.show', [$seller->id, 'category' => $name, 'sub' => $sub]) }}" class="ml-3 flex justify-between rounded px-2 py-1 text-xs" style="{{ request('sub') === $sub && $active ? 'color:#fa4e1c;font-weight:600;' : 'color:#78716C;' }}">
                            <span>{{ $sub }}</span><span>{{ $n }}</span>
                        </a>
                    @endforeach
                @endforeach
            </nav>
        </aside>

        <div>
            @if ($products->isEmpty())
                <p class="rounded-lg border border-dashed p-10 text-center text-sm" style="border-color:#E7E5E4;color:#8a7f78;">No products here yet.</p>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach ($products as $p)
                        <x-product-card :product="$p" />
                    @endforeach
                </div>
                <div class="mt-5">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</div>
</x-layout>
