<x-admin-layout title="Reviews" active="reviews">

<div class="grid gap-4 sm:grid-cols-2">
    @forelse ($reviews as $review)
        <div class="card p-5">
            <div class="flex items-center justify-between">
                <x-star-rating :rating="$review->rating" />
                <form action="{{ route('admin.reviews.destroy', $review->id) }}" method="POST" onsubmit="return confirm('Remove this review?')">
                    @csrf @method('DELETE')
                    <button class="text-xs font-semibold" style="color:#DC2626;"
                            onmouseover="this.style.textDecoration='underline';" onmouseout="this.style.textDecoration='none';">Remove</button>
                </form>
            </div>
            @if ($review->comment)<p class="mt-2 text-sm" style="color:#555555;">{{ $review->comment }}</p>@endif
            @if ($review->photo_path)<img src="{{ asset('storage/' . $review->photo_path) }}" alt="Review photo" class="mt-2 h-20 w-20 rounded-md object-cover">@endif
            <p class="mt-3 text-xs" style="color:#6b90aa;">{{ $review->user->name ?? 'Buyer' }} on {{ $review->product->title ?? 'a removed product' }}@if ($review->variation_name) ({{ $review->variation_name }})@endif · {{ $review->created_at->format('M j, Y') }}</p>
        </div>
    @empty
        <p class="text-sm" style="color:#6b90aa;">No reviews yet.</p>
    @endforelse
</div>
<div class="mt-4">{{ $reviews->links() }}</div>

</x-admin-layout>
