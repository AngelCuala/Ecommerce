<x-seller-layout title="Reviews" active="reviews">
@php $total = $counts->sum(); @endphp
<div class="mx-auto max-w-4xl">
    <div class="mb-5">
        <span class="section-eyebrow">Store</span>
        <h1 class="text-2xl">Reviews</h1>
        <p class="text-sm" style="color:#8a7f78;">What buyers say about your products. You can reply once to each review and edit your reply. Reviews can't be deleted.</p>
    </div>

    @if ($errors->any())<div class="mb-4 rounded-lg px-4 py-2.5 text-sm" style="background:#FEF2F2;color:#B91C1C;">{{ $errors->first() }}</div>@endif

    <div class="mb-4 flex flex-wrap gap-2 text-sm">
        <a href="{{ route('seller.reviews.index') }}" class="rounded-full border px-3 py-1 font-semibold" style="{{ request('stars') ? 'border-color:#E7E5E4;color:#57534E;' : 'border-color:#fa4e1c;color:#fa4e1c;background:#FFF1EB;' }}">All ({{ $total }})</a>
        @for ($s = 5; $s >= 1; $s--)
            <a href="{{ route('seller.reviews.index', ['stars' => $s]) }}" class="rounded-full border px-3 py-1 font-semibold" style="{{ (int) request('stars') === $s ? 'border-color:#fa4e1c;color:#fa4e1c;background:#FFF1EB;' : 'border-color:#E7E5E4;color:#57534E;' }}">{{ $s }} ★ ({{ $counts[$s] ?? 0 }})</a>
        @endfor
    </div>

    <div class="space-y-3">
        @forelse ($reviews as $review)
            <article class="card p-4" data-review="{{ $review->id }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="text-sm font-semibold">{{ $review->product->title ?? 'Removed product' }}@if ($review->variation_name) <span class="font-normal" style="color:#8a7f78;">· {{ $review->variation_name }}</span>@endif</p>
                        <p class="text-xs" style="color:#8a7f78;">{{ $review->user->name ?? 'Buyer' }} · {{ $review->created_at->format('M j, Y') }}@if ($review->edit_count) · edited @endif</p>
                    </div>
                    <span class="text-sm font-bold" style="color:#F59E0B;" aria-label="{{ $review->rating }} stars">{{ str_repeat('★', $review->rating) }}<span style="color:#E7E5E4;">{{ str_repeat('★', 5 - $review->rating) }}</span></span>
                </div>
                @if ($review->comment)<p class="mt-2 whitespace-pre-line text-sm" style="color:#44403C;">{{ $review->comment }}</p>@endif
                @if ($review->photos())<div class="mt-2 flex flex-wrap gap-2">@foreach ($review->photos() as $ph)<img src="{{ asset('storage/' . $ph) }}" alt="Review photo" class="h-20 rounded-md object-cover">@endforeach</div>@endif

                @if ($review->seller_reply !== null)
                    <div class="mt-3 rounded-md px-3 py-2 text-sm" style="background:#F5F2EF;"><strong>Your reply:</strong> {{ $review->seller_reply }}</div>
                    <details class="mt-2">
                        <summary class="cursor-pointer text-xs font-semibold" style="color:#57534E;">Edit reply</summary>
                        <form method="POST" action="{{ route('seller.reviews.reply.update', $review) }}" class="mt-2 flex gap-2">
                            @csrf @method('PUT')
                            <input name="reply" value="{{ $review->seller_reply }}" maxlength="1000" class="input !py-1.5 text-sm" aria-label="Reply">
                            <button class="btn-primary" style="padding:.4rem 1rem;">Save</button>
                        </form>
                    </details>
                @else
                    <form method="POST" action="{{ route('seller.reviews.reply', $review) }}" class="mt-3 flex gap-2">
                        @csrf
                        <input name="reply" maxlength="1000" class="input !py-1.5 text-sm" placeholder="Reply to this review" aria-label="Reply">
                        <button class="btn-primary" style="padding:.4rem 1rem;">Reply</button>
                    </form>
                @endif
            </article>
        @empty
            <div class="card px-6 py-12 text-center text-sm" style="color:#8a7f78;">No reviews{{ request('stars') ? ' with ' . request('stars') . ' stars' : '' }} yet.</div>
        @endforelse
        <div>{{ $reviews->links() }}</div>
    </div>
</div>
</x-seller-layout>
