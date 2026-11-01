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
            'rating'   => 'required|integer|between:1,5',
            'comment'  => 'nullable|string|max:' . Review::MAX_TEXT,
            'photo'    => 'nullable|file|mimes:jpg,jpeg,png|mimetypes:image/jpeg,image/png|max:5120',
            'photos'   => 'nullable|array|max:' . Review::MAX_PHOTOS,
            'photos.*' => 'file|mimes:jpg,jpeg,png|mimetypes:image/jpeg,image/png|max:5120',
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
            'comment.max'     => 'A review can be up to ' . Review::MAX_TEXT . ' characters.',
            'photos.max'      => 'You can add up to ' . Review::MAX_PHOTOS . ' photos.',
            'photos.*.mimes'  => 'Each photo must be a JPG or PNG image.',
            'photos.*.mimetypes' => 'Each photo must be a real JPG or PNG image.',
            'photos.*.max'    => 'Each photo can be at most 5 MB.',
        ];
    }

    /** The uploaded photos (the single "photo" field still works), stored. */
    private function storePhotos(Request $request): array
    {
        $files = array_values(array_filter(array_merge((array) $request->file('photos', []), [$request->file('photo')])));
        return array_map(fn ($f) => $f->store('reviews', 'public'), array_slice($files, 0, Review::MAX_PHOTOS));
    }

    public function store(Request $request, OrderItem $item)
    {
        $order = $item->order;
        abort_unless($order && (int) $order->user_id === (int) auth()->id(), 404);

        if (! $order->reviewWindowOpen()) {
            return back()->with('error', 'You can rate items once the order is completed, within ' . Review::REVIEW_DAYS . ' days.');
        }
        if ($item->review()->exists()) {
            return back()->with('error', 'You already rated this item. You can edit your review instead.');
        }

        $data = $request->validate($this->rules(), $this->messages());
        if (count((array) $request->file('photos', [])) + ($request->hasFile('photo') ? 1 : 0) > Review::MAX_PHOTOS) {
            return back()->withErrors(['photos' => 'You can add up to ' . Review::MAX_PHOTOS . ' photos.']);
        }
        $paths = $this->storePhotos($request);

        Review::create([
            'product_id'     => $item->product_id,
            'user_id'        => auth()->id(),
            'order_id'       => $order->id,
            'order_item_id'  => $item->id,
            'rating'         => $data['rating'],
            'comment'        => $data['comment'] ?? null,
            'photo_path'     => $paths[0] ?? null,
            'photo_paths'    => $paths ?: null,
            'variation_name' => $item->variation_name,
        ]);

        return back()->with('success', 'Thanks for your review!');
    }

    public function update(Request $request, Review $review)
    {
        abort_unless((int) $review->user_id === (int) auth()->id(), 404);

        if (! $review->canBeEdited()) {
            return back()->with('error', $review->edit_count >= Review::MAX_EDITS
                ? 'A review can be edited only once.'
                : 'Reviews can be edited only within ' . Review::REVIEW_DAYS . ' days of completing the order.');
        }

        $data = $request->validate($this->rules(), $this->messages());

        if ($request->hasFile('photo') || $request->hasFile('photos')) {
            foreach ($review->photos() as $old) {
                Storage::disk('public')->delete($old);
            }
            $paths = $this->storePhotos($request);
            $data['photo_path']  = $paths[0] ?? null;
            $data['photo_paths'] = $paths ?: null;
        }
        unset($data['photo'], $data['photos']);

        $review->update($data + ['edit_count' => $review->edit_count + 1]);

        return back()->with('success', 'Your review was updated.');
    }
}
