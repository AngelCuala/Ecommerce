<x-seller-layout title="Reviews" active="reviews">
@php
    $total = $counts->sum();
    $stars = (int) request('stars') ?: null;
    $replied = request('replied');
    $filterUrl = fn (array $over) => route('seller.reviews.index', array_filter(array_merge(['stars' => $stars, 'replied' => $replied], $over)));
    $tabs = [
        ['All', null, $total],
        ['Needs reply', 'no', $needsReply],
        ['Replied', 'yes', $total - $needsReply],
    ];
@endphp
<style>
    .rv-card { border: 1px solid #ECE7E3; border-radius: 1rem; background: #fff; box-shadow: 0 1px 2px rgba(34, 34, 34, .04); }
    .rv-stars { position: relative; display: inline-block; white-space: nowrap; color: #E7E5E4; letter-spacing: .05em; line-height: 1; }
    .rv-stars span { position: absolute; inset: 0 auto 0 0; overflow: hidden; color: #F59E0B; }
    .rv-bar { display: grid; grid-template-columns: 2.25rem 1fr 2rem; align-items: center; gap: .625rem; padding: .2rem .5rem; border-radius: .5rem; font-size: .8125rem; color: #57534E; transition: background .15s; }
    .rv-bar:hover { background: #FAFAF9; }
    .rv-bar.on { background: #FFF1EB; color: #d93d0e; font-weight: 600; }
    .rv-track { height: .5rem; overflow: hidden; border-radius: 9999px; background: #F5F2EF; }
    .rv-track span { display: block; height: 100%; border-radius: 9999px; background: #F59E0B; }
    .rv-tab { display: inline-flex; align-items: center; gap: .4rem; padding: .35rem .85rem; border: 1px solid #E7E5E4; border-radius: 9999px; background: #fff; font-size: .8125rem; font-weight: 600; color: #57534E; transition: border-color .15s; }
    .rv-tab:hover { border-color: #fdb49e; }
    .rv-tab.on { border-color: #fa4e1c; background: #FFF1EB; color: #fa4e1c; }
    .rv-tab b { font-weight: 600; opacity: .7; }
    .rv-avatar { display: flex; flex: none; height: 2.5rem; width: 2.5rem; align-items: center; justify-content: center; border-radius: 9999px; background: #FFF1EB; font-size: .875rem; font-weight: 700; color: #d93d0e; }
    .rv-pending { padding: .1rem .55rem; border-radius: 9999px; background: #FEF3C7; font-size: .6875rem; font-weight: 600; color: #92400E; }
    .rv-reply { border-left: 3px solid #fa4e1c; border-radius: .25rem .5rem .5rem .25rem; background: #FAF8F6; padding: .625rem .875rem; }
    .rv-link { font-size: .8125rem; font-weight: 600; color: #57534E; }
    .rv-link:hover { color: #fa4e1c; text-decoration: underline; }
</style>

<div class="mx-auto max-w-4xl">
    <div class="mb-5">
        <span class="section-eyebrow">Store</span>
        <h1 class="text-2xl">Reviews</h1>
        <p class="text-sm" style="color:#8a7f78;">What buyers say about your products. You can reply once to each review and edit your reply. Reviews can't be deleted.</p>
    </div>

    @if ($errors->any())<div class="mb-4 rounded-lg px-4 py-2.5 text-sm" style="background:#FEF2F2;color:#B91C1C;" role="alert">{{ $errors->first() }}</div>@endif

    @if ($total === 0)
        {{-- Nothing to filter or average yet. --}}
        <div class="rv-card px-6 py-14 text-center">
            <span class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full" style="background:#FFF1EB;color:#fa4e1c;" aria-hidden="true">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.5a.56.56 0 0 1 1.04 0l2.13 5.11a.56.56 0 0 0 .48.35l5.52.44c.5.04.7.66.32.98l-4.2 3.6a.56.56 0 0 0-.19.56l1.29 5.38a.56.56 0 0 1-.84.61l-4.73-2.88a.56.56 0 0 0-.59 0l-4.73 2.88a.56.56 0 0 1-.84-.61l1.29-5.38a.56.56 0 0 0-.18-.56l-4.21-3.6a.56.56 0 0 1 .32-.98l5.52-.44a.56.56 0 0 0 .47-.35l2.13-5.11Z"/></svg>
            </span>
            <h2 class="text-base font-semibold" style="color:#222;">No reviews yet</h2>
            <p class="mx-auto mt-1 max-w-md text-sm" style="color:#8a7f78;">Buyers can review an item within {{ \App\Models\Review::REVIEW_DAYS }} days of their order being completed. Their ratings and comments will show up here, ready for your reply.</p>
            <a href="{{ route('seller.books.index') }}" class="rv-link mt-4 inline-block">View your products &rarr;</a>
        </div>
    @else
        {{-- ── Summary ── --}}
        <section class="rv-card mb-4 grid gap-5 p-5 sm:grid-cols-[10rem_1fr_11rem] sm:items-center" aria-label="Rating summary">
            <div class="text-center">
                <p class="text-4xl font-bold" style="color:#222;">{{ number_format($average, 1) }}</p>
                <p class="rv-stars mt-1 text-lg" aria-label="{{ number_format($average, 1) }} out of 5 stars">★★★★★<span style="width:{{ $average / 5 * 100 }}%;" aria-hidden="true">★★★★★</span></p>
                <p class="mt-1 text-xs" style="color:#8a7f78;">{{ number_format($total) }} {{ \Illuminate\Support\Str::plural('review', $total) }}</p>
            </div>

            <div>
                @for ($s = 5; $s >= 1; $s--)
                    @php $n = (int) ($counts[$s] ?? 0); @endphp
                    <a href="{{ $filterUrl(['stars' => $stars === $s ? null : $s]) }}" class="rv-bar {{ $stars === $s ? 'on' : '' }}"
                       @if ($stars === $s) aria-current="true" @endif aria-label="{{ $s }} stars: {{ $n }} {{ \Illuminate\Support\Str::plural('review', $n) }}">
                        <span>{{ $s }} ★</span>
                        <span class="rv-track"><span style="width:{{ $n / $total * 100 }}%;"></span></span>
                        <span class="text-right">{{ $n }}</span>
                    </a>
                @endfor
            </div>

            <a href="{{ $filterUrl(['replied' => 'no']) }}" class="block rounded-xl p-4 text-center transition sm:text-left" style="background:{{ $needsReply ? '#FFFBEB' : '#F0FDF4' }};">
                <p class="text-2xl font-bold" style="color:{{ $needsReply ? '#92400E' : '#15803D' }};">{{ $needsReply }}</p>
                <p class="text-xs font-medium" style="color:{{ $needsReply ? '#92400E' : '#15803D' }};">{{ $needsReply ? 'waiting for your reply' : 'All reviews answered' }}</p>
            </a>
        </section>

        {{-- ── Filters ── --}}
        <div class="mb-4 flex flex-wrap items-center gap-2">
            @foreach ($tabs as [$label, $value, $n])
                <a href="{{ $filterUrl(['replied' => $value]) }}" class="rv-tab {{ $replied === $value ? 'on' : '' }}" @if ($replied === $value) aria-current="true" @endif>{{ $label }} <b>{{ number_format($n) }}</b></a>
            @endforeach
            @if ($stars)
                <a href="{{ $filterUrl(['stars' => null]) }}" class="rv-link ml-1">{{ $stars }} ★ only &times;</a>
            @endif
        </div>

        <div class="space-y-3">
            @forelse ($reviews as $review)
                @php
                    $product = $review->product;
                    $buyer = $review->user->name ?? 'Buyer';
                    $thumb = $product?->thumb_url;
                @endphp
                <article class="rv-card p-5" data-review="{{ $review->id }}">
                    <div class="flex items-start gap-3">
                        <span class="rv-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($buyer, 0, 1)) }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                <p class="text-sm font-semibold" style="color:#222;">{{ $buyer }}</p>
                                <span class="text-base" style="color:#F59E0B;letter-spacing:.05em;" aria-label="{{ $review->rating }} stars">{{ str_repeat('★', $review->rating) }}<span style="color:#E7E5E4;">{{ str_repeat('★', 5 - $review->rating) }}</span></span>
                            </div>
                            <p class="text-xs" style="color:#8a7f78;">
                                {{ $review->created_at->format('M j, Y') }}@if ($review->edit_count) · edited @endif
                                @if ($review->seller_reply === null)<span class="rv-pending ml-1">Needs reply</span>@endif
                            </p>
                        </div>
                    </div>

                    @if ($review->comment)<p class="mt-3 whitespace-pre-line text-sm leading-relaxed" style="color:#44403C;">{{ $review->comment }}</p>@endif

                    @if ($review->photos())
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($review->photos() as $ph)
                                <a href="{{ asset('storage/' . $ph) }}" target="_blank" rel="noopener" aria-label="Open review photo in a new tab">
                                    <img src="{{ asset('storage/' . $ph) }}" alt="Review photo" loading="lazy" class="h-20 w-20 rounded-lg border object-cover" style="border-color:#ECE7E3;">
                                </a>
                            @endforeach
                        </div>
                    @endif

                    <div class="mt-3 flex items-center gap-2.5 rounded-lg p-2" style="background:#FAFAF9;">
                        @if ($thumb)<img src="{{ $thumb }}" alt="" loading="lazy" class="h-9 w-9 flex-none rounded-md object-cover">@endif
                        <p class="min-w-0 truncate text-xs" style="color:#57534E;">
                            @if ($product && ! $product->trashed())
                                <a href="{{ route('products.show', $product) }}" class="font-medium hover:underline">{{ $product->title }}</a>
                            @else
                                <span class="font-medium">{{ $product->title ?? 'Removed product' }}</span>
                            @endif
                            @if ($review->variation_name)<span style="color:#8a7f78;"> · {{ $review->variation_name }}</span>@endif
                        </p>
                    </div>

                    @if ($review->seller_reply !== null)
                        <div class="mt-3" data-rv-reply>
                            <div class="rv-reply" data-rv-view>
                                <div class="mb-0.5 flex flex-wrap items-baseline justify-between gap-2">
                                    <p class="text-xs font-semibold" style="color:#222;">Your reply @if ($review->seller_replied_at)<span class="font-normal" style="color:#8a7f78;">· {{ $review->seller_replied_at->format('M j, Y') }}</span>@endif</p>
                                    <button type="button" class="rv-link" data-rv-edit>Edit</button>
                                </div>
                                <p class="whitespace-pre-line text-sm" style="color:#44403C;">{{ $review->seller_reply }}</p>
                            </div>
                            <form method="POST" action="{{ route('seller.reviews.reply.update', $review) }}" data-rv-form hidden>
                                @csrf @method('PUT')
                                <textarea name="reply" rows="3" maxlength="1000" required class="input text-sm" aria-label="Edit your reply">{{ $review->seller_reply }}</textarea>
                                <div class="mt-2 flex items-center justify-between gap-3">
                                    <span class="text-xs" style="color:#8a7f78;"><span data-rv-count>{{ mb_strlen($review->seller_reply) }}</span> / 1,000</span>
                                    <span class="flex items-center gap-3">
                                        <button type="button" class="rv-link" data-rv-cancel>Cancel</button>
                                        <button class="btn-primary" style="padding:.4rem 1rem;">Save</button>
                                    </span>
                                </div>
                            </form>
                        </div>
                    @else
                        <form method="POST" action="{{ route('seller.reviews.reply', $review) }}" class="mt-3">
                            @csrf
                            <textarea name="reply" rows="2" maxlength="1000" required class="input text-sm" placeholder="Write a reply. Buyers see it under the review." aria-label="Reply to {{ $buyer }}"></textarea>
                            <div class="mt-2 flex items-center justify-between gap-3">
                                <span class="text-xs" style="color:#8a7f78;"><span data-rv-count>0</span> / 1,000</span>
                                <button class="btn-primary" style="padding:.4rem 1rem;">Reply</button>
                            </div>
                        </form>
                    @endif
                </article>
            @empty
                <div class="rv-card px-6 py-12 text-center">
                    <p class="text-sm" style="color:#8a7f78;">No reviews match {{ $stars ? 'this rating' : 'this filter' }}.</p>
                    <a href="{{ route('seller.reviews.index') }}" class="rv-link mt-2 inline-block">Show all reviews</a>
                </div>
            @endforelse
            <div>{{ $reviews->links() }}</div>
        </div>
    @endif
</div>
<script>
(function () {
    function count(textarea) {
        var el = textarea.form.querySelector('[data-rv-count]');
        if (el) el.textContent = textarea.value.length;
    }
    document.addEventListener('input', function (e) {
        if (e.target.matches('[data-review] textarea')) count(e.target);
    });
    document.addEventListener('click', function (e) {
        var box = e.target.closest('[data-rv-reply]');
        if (!box) return;
        var editing = e.target.matches('[data-rv-edit]');
        if (!editing && !e.target.matches('[data-rv-cancel]')) return;
        var form = box.querySelector('[data-rv-form]'), input = form.querySelector('textarea');
        box.querySelector('[data-rv-view]').hidden = editing;
        form.hidden = !editing;
        if (editing) {
            input.focus();
            input.setSelectionRange(input.value.length, input.value.length);
        } else {
            input.value = input.defaultValue;
            count(input);
        }
    });
})();
</script>
</x-seller-layout>
