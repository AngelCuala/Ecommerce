<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

/**
 * Reviews of the seller's products. The seller can reply once and edit that reply,
 * but can't delete a buyer's review (assumption A12).
 */
class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['stars' => 'nullable|integer|between:1,5', 'replied' => 'nullable|in:yes,no']);

        $base = Review::whereHas('product', fn ($q) => $q->withTrashed()->where('seller_id', auth()->id()));
        $counts = (clone $base)->selectRaw('rating, COUNT(*) as n')->groupBy('rating')->pluck('n', 'rating');

        $reviews = (clone $base)->with(['product', 'user'])
            ->when($request->filled('stars'), fn ($q) => $q->where('rating', (int) $request->stars))
            ->when($request->replied === 'yes', fn ($q) => $q->whereNotNull('seller_reply'))
            ->when($request->replied === 'no', fn ($q) => $q->whereNull('seller_reply'))
            ->latest()->paginate(20)->withQueryString();

        return view('seller.reviews.index', compact('reviews', 'counts'));
    }

    /** First (and only) reply. */
    public function reply(Request $request, Review $review)
    {
        $this->authorizeOwn($review);
        if ($review->seller_reply !== null) {
            return back()->with('error', 'You already replied to this review. You can edit your reply instead.');
        }
        $review->update(['seller_reply' => $this->validated($request), 'seller_replied_at' => now()]);
        \App\Models\ActivityLog::note('Replied to a ' . $review->rating . '-star review', $review);

        return back()->with('success', 'Reply posted. Buyers see it under the review.');
    }

    public function updateReply(Request $request, Review $review)
    {
        $this->authorizeOwn($review);
        if ($review->seller_reply === null) {
            return back()->with('error', 'There is no reply to edit yet.');
        }
        $review->update(['seller_reply' => $this->validated($request), 'seller_replied_at' => now()]);

        return back()->with('success', 'Reply updated.');
    }

    private function validated(Request $request): string
    {
        $request->merge(['reply' => trim((string) $request->input('reply'))]);

        return $request->validate(['reply' => 'required|string|max:1000'], ['reply.required' => 'Please write a reply.'])['reply'];
    }

    private function authorizeOwn(Review $review): void
    {
        abort_unless((int) optional($review->product)->seller_id === (int) auth()->id(), 403);
    }
}
