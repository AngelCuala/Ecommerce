<x-layout :title="$term !== '' ? '&quot;' . e($term) . '&quot; — ALVY' : 'Shop — ALVY'">
@php
    $sorts = \App\Support\ProductSearch::SORTS;
    $sort  = request('sort', $term !== '' ? 'relevance' : 'newest');
    $active = request()->hasAny(['min_price', 'max_price', 'rating', 'category', 'location']);
@endphp
<div style="background:#F7F5F3;min-height:80vh;">
    <div class="border-b bg-white" style="border-color:#EFEFEF;">
        <div class="mx-auto max-w-7xl px-4 py-3 text-sm sm:px-6 lg:px-8" style="color:#757575;">
            @if ($suggestion)
                No results for <strong style="color:#222;">"{{ $term }}"</strong>.
                Showing results for <strong style="color:#fa4e1c;">"{{ $suggestion }}"</strong> instead.
            @elseif ($term !== '')
                Search results for <strong style="color:#fa4e1c;">"{{ $term }}"</strong> — {{ $products->total() }} {{ \Illuminate\Support\Str::plural('item', $products->total()) }}
            @else
                All products — {{ $products->total() }} {{ \Illuminate\Support\Str::plural('item', $products->total()) }}
            @endif
        </div>
    </div>

    <div class="mx-auto grid max-w-7xl gap-5 px-4 py-5 sm:px-6 lg:grid-cols-[230px_1fr] lg:px-8">

        {{-- Filters (its own form: product cards have their own forms) --}}
        <form method="GET" action="{{ route('shop.index') }}" class="space-y-5 self-start rounded-lg bg-white p-4 text-sm" style="border:1px solid #EFEFEF;">
            <input type="hidden" name="search" value="{{ $term }}">
            <input type="hidden" name="sort" value="{{ $sort }}">
            <div>
                <p class="mb-2 font-bold" style="color:#222;">Price range</p>
                <div class="flex items-center gap-2">
                    <input type="number" name="min_price" value="{{ request('min_price') }}" min="0" step="0.01" placeholder="₱ Min" class="w-full rounded border px-2 py-1.5 text-xs" style="border-color:#E0E0E0;" aria-label="Minimum price">
                    <span style="color:#999;">–</span>
                    <input type="number" name="max_price" value="{{ request('max_price') }}" min="0" step="0.01" placeholder="₱ Max" class="w-full rounded border px-2 py-1.5 text-xs" style="border-color:#E0E0E0;" aria-label="Maximum price">
                </div>
            </div>
            <div>
                <p class="mb-2 font-bold" style="color:#222;">Rating</p>
                @foreach ([5, 4, 3] as $r)
                    <label class="flex items-center gap-2 py-0.5">
                        <input type="radio" name="rating" value="{{ $r }}" @checked((int) request('rating') === $r) class="accent-orange-600">
                        <span style="color:#F59E0B;">{{ str_repeat('★', $r) }}<span style="color:#E7E5E4;">{{ str_repeat('★', 5 - $r) }}</span></span>
                        <span class="text-xs" style="color:#8a7f78;">{{ $r < 5 ? '& up' : '' }}</span>
                    </label>
                @endforeach
            </div>
            <div>
                <p class="mb-2 font-bold" style="color:#222;">Category</p>
                <select name="category" class="w-full rounded border px-2 py-1.5 text-xs" style="border-color:#E0E0E0;">
                    <option value="">All categories</option>
                    @foreach ($categories as $c)<option value="{{ $c }}" @selected(request('category') === $c)>{{ $c }}</option>@endforeach
                </select>
            </div>
            <div>
                <p class="mb-2 font-bold" style="color:#222;">Shipped from</p>
                <select name="location" class="w-full rounded border px-2 py-1.5 text-xs" style="border-color:#E0E0E0;">
                    <option value="">Anywhere</option>
                    @foreach ($locations as $l)<option value="{{ $l }}" @selected(request('location') === $l)>{{ $l }}</option>@endforeach
                </select>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 rounded px-3 py-2 text-xs font-semibold text-white" style="background:#fa4e1c;">Apply</button>
                @if ($active)
                    <a href="{{ route('shop.index', array_filter(['search' => $term, 'sort' => request('sort')])) }}" class="rounded border px-3 py-2 text-xs font-semibold" style="border-color:#E0E0E0;color:#555;">Clear</a>
                @endif
            </div>
        </form>

        <div>
            {{-- Sort --}}
            <div class="mb-4 flex flex-wrap items-center gap-2 rounded-lg bg-white px-4 py-3" style="border:1px solid #EFEFEF;">
                <span class="text-sm font-medium" style="color:#555;">Sort by</span>
                @foreach ($sorts as $value => $label)
                    @continue($value === 'relevance' && $term === '')
                    <a href="{{ request()->fullUrlWithQuery(['sort' => $value, 'page' => null]) }}" class="rounded px-3 py-1.5 text-sm"
                       style="{{ $sort === $value ? 'background:#fa4e1c;color:#fff;' : 'background:#fff;color:#555;border:1px solid #E0E0E0;' }}">{{ $label }}</a>
                @endforeach
            </div>

            @foreach ($notes as $note)
                <p class="mb-3 rounded-md px-3 py-2 text-xs" style="background:#FFFBEB;color:#92400E;">{{ $note }}</p>
            @endforeach

            @if ($products->isEmpty())
                <div class="flex flex-col items-center gap-3 rounded-lg bg-white py-20 text-center" style="border:1px solid #EFEFEF;">
                    <span class="text-5xl">🔍</span>
                    <p class="text-lg font-bold" style="color:#222;">No results{{ $term !== '' ? ' for "' . $term . '"' : '' }}</p>
                    <p class="text-sm" style="color:#999;">Try other words, check the spelling, or clear the filters.</p>
                    <a href="{{ route('categories.page') }}" class="mt-2 rounded px-6 py-2.5 text-sm font-semibold text-white" style="background:#002b4d;">Browse categories</a>
                </div>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    @foreach ($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
                <div class="mt-5">{{ $products->links() }}</div>
            @endif
        </div>
    </div>
</div>
</x-layout>
