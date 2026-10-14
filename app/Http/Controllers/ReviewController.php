<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Buyers rate the items of their completed orders (one review per item, editable once). */
class ReviewController extends Controller
{
    private function rules(): array
    {
        return [
            'rating'  => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:1000',
            'photo'   => 'nullable|file|mimes:jpg,jpeg,png|mimetypes:image/jpeg,image/png|max:5120',
        ];
    }

    private function messages(): array
    {
        return [
            'rating.required' => 'Please choose 1 to 5 stars.',
            'rating.between'  => 'Please choose 1 to 5 stars.',
            'photo.mimes'     => 'The photo must be a JPG or PNG image.',
            'photo.mimetypes' => 'The photo must be a real JPG or PNG image.',
            'photo.max'       => 'The photo can be at most 5 MB.',
        ];
    }

    public function store(Request $request, OrderItem $item)
    {
        $order = $item->order;
        abort_unless($order && $order->user_id === auth()->id(), 404);

        if (! $order->reviewWindowOpen()) {
            return back()->with('error', 'You can rate items once the order is completed, within ' . Review::REVIEW_DAYS . ' days.');
        }
        if ($item->review()->exists()) {
            return back()->with('error', 'You already rated this item. You can edit your review instead.');
        }

        $data = $request->validate($this->rules(), $this->messages());

        Review::create([
            'product_id'     => $item->product_id,
            'user_id'        => auth()->id(),
            'order_id'       => $order->id,
            'order_item_id'  => $item->id,
            'rating'         => $data['rating'],
            'comment'        => $data['comment'] ?? null,
            'photo_path'     => $request->file('photo')?->store('reviews', 'public'),
            'variation_name' => $item->variation_name,
        ]);

        return back()->with('success', 'Thanks for your review!');
    }

    public function update(Request $request, Review $review)
    {
        abort_unless($review->user_id === auth()->id(), 404);

        if (! $review->canBeEdited()) {
            return back()->with('error', $review->edit_count >= Review::MAX_EDITS
                ? 'A review can be edited only once.'
                : 'Reviews can be edited only within ' . Review::REVIEW_DAYS . ' days of completing the order.');
        }

        $data = $request->validate($this->rules(), $this->messages());

        if ($request->hasFile('photo')) {
            if ($review->photo_path) {
                Storage::disk('public')->delete($review->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('reviews', 'public');
        }
        unset($data['photo']);

        $review->update($data + ['edit_count' => $review->edit_count + 1]);

        return back()->with('success', 'Your review was updated.');
    }
}
