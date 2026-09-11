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
            <p class="mt-2 text-sm font-semibold" style="color:#222222;">{{ $review->title }}</p>
            <p class="mt-1 text-sm" style="color:#555555;">{{ $review->comment }}</p>
            <p class="mt-3 text-xs" style="color:#6b90aa;">{{ $review->user->name }} on {{ $review->product->title }}</p>
        </div>
    @empty
        <p class="text-sm" style="color:#6b90aa;">No reviews yet.</p>
    @endforelse
</div>

</x-admin-layout>